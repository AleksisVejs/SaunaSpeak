<?php

namespace Tests\Feature;

use App\Models\AiCorrection;
use App\Models\Lesson;
use App\Models\Sentence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Streak freezes, per-item SRS ease, and the AI-correction cache. */
class RetentionFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Sentence $sentence;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('password'),
        ]);

        $lesson = Lesson::create(['title' => 'Testi', 'level' => 'A0', 'order_index' => 1]);
        $this->sentence = $lesson->sentences()->create([
            'finnish_text' => 'Emmä tiiä.',
            'english_text' => "I don't know.",
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_freeze_saves_streak_after_one_missed_day(): void
    {
        $this->user->update(['streak' => 7, 'streak_freezes' => 1, 'last_active_date' => today()->subDays(2)]);

        $this->user->syncStreak();
        $this->user->refresh();

        $this->assertSame(7, $this->user->streak);
        $this->assertSame(0, $this->user->streak_freezes);

        // The bumped last_active_date lets today's session continue the streak.
        $this->postJson('/api/session/complete')->assertOk();
        $this->assertSame(8, $this->user->fresh()->streak);
    }

    public function test_streak_still_resets_without_a_freeze_or_after_two_missed_days(): void
    {
        $this->user->update(['streak' => 7, 'streak_freezes' => 0, 'last_active_date' => today()->subDays(2)]);
        $this->user->syncStreak();
        $this->assertSame(0, $this->user->fresh()->streak);

        $this->user->update(['streak' => 7, 'streak_freezes' => 2, 'last_active_date' => today()->subDays(3)]);
        $this->user->syncStreak();
        $this->assertSame(0, $this->user->fresh()->streak);
        $this->assertSame(2, $this->user->fresh()->streak_freezes); // not wasted on a lost cause
    }

    public function test_completing_a_seventh_streak_day_earns_a_freeze(): void
    {
        $this->user->update(['streak' => 6, 'streak_freezes' => 0, 'last_active_date' => today()->subDay()]);

        $this->postJson('/api/session/complete')->assertOk();

        $this->user->refresh();
        $this->assertSame(7, $this->user->streak);
        $this->assertSame(1, $this->user->streak_freezes);
    }

    public function test_breaking_a_streak_records_it_for_repair(): void
    {
        $this->user->update(['streak' => 12, 'streak_freezes' => 0, 'last_active_date' => today()->subDays(3)]);

        $this->user->syncStreak();
        $this->user->refresh();

        $this->assertSame(0, $this->user->streak);
        $this->assertSame(12, $this->user->broken_streak);
        $this->assertTrue($this->user->streak_repairable);
    }

    public function test_repair_restores_the_streak_costs_xp_and_reconnects_the_chain(): void
    {
        $this->user->update(['xp' => 500, 'streak' => 12, 'streak_freezes' => 0, 'last_active_date' => today()->subDays(3)]);
        $this->user->syncStreak();

        $this->postJson('/api/streak/repair')->assertOk();
        $this->user->refresh();

        $this->assertSame(12, $this->user->streak);
        $this->assertSame(300, $this->user->xp);
        $this->assertSame(0, $this->user->broken_streak);
        $this->assertFalse($this->user->streak_repairable);

        // The bumped last_active_date lets today's session continue the streak.
        $this->postJson('/api/session/complete')->assertOk();
        $this->assertSame(13, $this->user->fresh()->streak);
    }

    public function test_repair_stacks_on_days_already_practiced_since_the_break(): void
    {
        $this->user->update(['xp' => 200, 'streak' => 12, 'streak_freezes' => 0, 'last_active_date' => today()->subDays(3)]);
        $this->user->syncStreak();

        // Came back and did a session first (streak restarts at 1), then repaired.
        $this->postJson('/api/session/complete')->assertOk();
        $this->postJson('/api/streak/repair')->assertOk();

        $this->assertSame(13, $this->user->fresh()->streak);
    }

    public function test_repair_requires_xp_and_a_recent_break(): void
    {
        $this->user->update(['xp' => 100, 'streak' => 12, 'streak_freezes' => 0, 'last_active_date' => today()->subDays(3)]);
        $this->user->syncStreak();

        // Too poor.
        $this->postJson('/api/streak/repair')->assertStatus(422);

        // Rich, but the break is too old.
        $this->user->update(['xp' => 500, 'streak_broken_date' => today()->subDays(4)]);
        $this->postJson('/api/streak/repair')->assertStatus(422);

        $this->assertSame(0, $this->user->fresh()->streak);
        $this->assertSame(500, $this->user->fresh()->xp);
    }

    public function test_lapses_shrink_ease_and_shorten_the_next_long_interval(): void
    {
        // Three lapses from review status pull ease down from 2.5.
        $progress = $this->user->progress()->create([
            'sentence_id' => $this->sentence->id,
            'status' => 'review',
            'next_review_at' => now(),
        ]);

        foreach (range(1, 3) as $i) {
            $this->postJson('/api/progress/complete', [
                'sentence_id' => $this->sentence->id, 'grade' => 'again',
            ])->assertOk();
        }

        $this->assertEqualsWithDelta(1.9, $progress->fresh()->ease, 0.001);

        // Advancing learning → review (base 3 days) now lands closer than 3 days out.
        $this->postJson('/api/progress/complete', [
            'sentence_id' => $this->sentence->id, 'grade' => 'good',
        ])->assertOk();
        $this->assertTrue($progress->fresh()->next_review_at->lt(now()->addDays(3)));
    }

    public function test_identical_correction_requests_are_served_from_cache(): void
    {
        AiCorrection::create([
            'hash' => AiCorrection::keyFor('Emmä tiiä.', 'Ma en tiedä'),
            'expected_sentence' => 'Emmä tiiä.',
            'user_sentence' => 'Ma en tiedä',
            'corrected' => 'Emmä tiiä.',
            'explanation' => 'Close! The spoken form squeezes it all together.',
        ]);

        // Case and whitespace variants hit the same entry; no LLM key needed.
        $this->postJson('/api/ai/correct', [
            'user_sentence' => '  MA EN TIEDÄ ',
            'expected_sentence' => 'Emmä tiiä.',
        ])->assertOk()->assertJson(['source' => 'cache', 'corrected' => 'Emmä tiiä.']);

        $this->assertSame(1, AiCorrection::first()->hits);
    }
}
