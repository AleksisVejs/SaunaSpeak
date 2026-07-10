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
        config(['services.ai.key' => null, 'services.ai.gemini_key' => null]);

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
