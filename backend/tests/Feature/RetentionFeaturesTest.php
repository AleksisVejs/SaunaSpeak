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
