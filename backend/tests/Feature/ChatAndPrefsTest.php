<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatAndPrefsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('password'),
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_chat_replies_without_an_api_key_via_mock(): void
    {
        config(['services.ai.key' => null, 'services.ai.gemini_key' => null, 'services.ai.openrouter_key' => null]);

        $this->postJson('/api/chat', [
            'messages' => [['role' => 'user', 'content' => 'Moi Aino!']],
        ])
            ->assertOk()
            ->assertJsonPath('source', 'mock')
            ->assertJsonStructure(['reply', 'translation', 'correction']);
    }

    public function test_chat_validates_message_shape(): void
    {
        $this->postJson('/api/chat', ['messages' => [['role' => 'system', 'content' => 'hax']]])
            ->assertStatus(422);
    }

    public function test_preferences_round_trip(): void
    {
        $this->postJson('/api/preferences', [
            'preferences' => ['goal' => 'move', 'minutes' => 5, 'dailyGoal' => 6],
        ])
            ->assertOk()
            ->assertJsonPath('preferences.goal', 'move');

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.preferences.minutes', 5);
    }

    /** Seed five lessons (four A0 + one A1), eight sentences each. */
    private function seedIntroLessons(): void
    {
        foreach (range(1, 5) as $i) {
            $lesson = Lesson::create([
                'title' => "L$i",
                'level' => $i <= 4 ? 'A0' : 'A1',
                'order_index' => $i,
            ]);
            foreach (range(1, 8) as $j) {
                $lesson->sentences()->create(['finnish_text' => "fi $i-$j", 'english_text' => "en $i-$j"]);
            }
        }
    }

    public function test_rusty_placement_seeds_the_a0_block_as_spaced_reviews(): void
    {
        $this->seedIntroLessons();

        $this->postJson('/api/preferences', [
            'preferences' => ['level' => 'rusty', 'minutes' => 5, 'dailyGoal' => 6],
        ])->assertOk();

        // "Brushing up" skips the whole A0 block: 4 lessons x 8 = 32 sentences,
        // all seeded as due reviews (not taught fresh).
        $this->assertSame(32, $this->user->progress()->count());
        $this->assertSame(32, $this->user->progress()->where('status', UserProgress::STATUS_REVIEW)->count());

        // Load-aware stagger at 6/day: the earliest day holds no more than the
        // daily pace, so a deeper skip never lands as one review lump.
        $this->assertSame(6, $this->user->progress()->where('interval_days', 2)->count());
        $this->assertSame(2, $this->user->progress()->where('interval_days', 7)->count());
    }

    public function test_a_few_words_placement_skips_two_lessons(): void
    {
        $this->seedIntroLessons();

        $this->postJson('/api/preferences', [
            'preferences' => ['level' => 'some', 'dailyGoal' => 6],
        ])->assertOk();

        $this->assertSame(16, $this->user->progress()->count());
    }

    public function test_apply_placement_false_defers_the_seed_until_applied(): void
    {
        $this->seedIntroLessons();

        // The intake defers the seed (the placement test is the accurate placement).
        $this->postJson('/api/preferences', [
            'preferences' => ['level' => 'rusty', 'dailyGoal' => 6],
            'apply_placement' => false,
        ])->assertOk();
        $this->assertSame(0, $this->user->progress()->count());

        // The "start from the beginning" fallback applies it later, as normal.
        $this->postJson('/api/preferences', [
            'preferences' => ['level' => 'rusty', 'dailyGoal' => 6],
            'apply_placement' => true,
        ])->assertOk();
        $this->assertSame(32, $this->user->progress()->count());
    }

    public function test_absolute_beginner_gets_no_placement(): void
    {
        $this->seedIntroLessons();

        $this->postJson('/api/preferences', [
            'preferences' => ['level' => 'none', 'dailyGoal' => 6],
        ])->assertOk();

        $this->assertSame(0, $this->user->progress()->count());
    }

    public function test_placement_never_overwrites_existing_progress(): void
    {
        $this->seedIntroLessons();
        $existing = \App\Models\Sentence::first();
        UserProgress::create([
            'user_id' => $this->user->id,
            'sentence_id' => $existing->id,
            'status' => UserProgress::STATUS_NEW,
            'next_review_at' => now(),
        ]);

        $this->postJson('/api/preferences', [
            'preferences' => ['level' => 'rusty', 'dailyGoal' => 6],
        ])->assertOk();

        // Placement bails on any prior progress - only the one existing row remains.
        $this->assertSame(1, $this->user->progress()->count());
    }

    public function test_user_stats_include_seven_day_forecast(): void
    {
        $lesson = Lesson::create(['title' => 'Testi', 'level' => 'A0', 'order_index' => 1]);
        $s1 = $lesson->sentences()->create(['finnish_text' => 'Moi.', 'english_text' => 'Hi.']);
        $s2 = $lesson->sentences()->create(['finnish_text' => 'Hei.', 'english_text' => 'Hey.']);

        // Due in 2 days (in forecast) and in 9 days (outside the window).
        UserProgress::create(['user_id' => $this->user->id, 'sentence_id' => $s1->id,
            'status' => UserProgress::STATUS_REVIEW, 'next_review_at' => now()->addDays(2)]);
        UserProgress::create(['user_id' => $this->user->id, 'sentence_id' => $s2->id,
            'status' => UserProgress::STATUS_MASTERED, 'next_review_at' => now()->addDays(9)]);

        $res = $this->getJson('/api/user')->assertOk();
        $forecast = $res->json('stats.forecast');

        $this->assertCount(1, $forecast);
        $this->assertSame(now()->addDays(2)->toDateString(), $forecast[0]['date']);
        $this->assertSame(1, $forecast[0]['count']);
    }
}
