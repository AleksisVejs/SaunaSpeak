<?php

namespace Tests\Feature;

use App\Models\ChatDay;
use App\Models\ReviewLog;
use App\Models\User;
use App\Models\UserMistake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The error-driven SRS loop: corrections handed out in Sauna Chat become
 * flashcards (user_mistakes), reviewed on the shared ladder, and surfaced
 * in weekly insights alongside chat volume (chat_days).
 */
class ChatMistakeTest extends TestCase
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

    /** One chat round against a faked model that answers with $correction. */
    private function chat(?string $correction, ?string $scenario = null, string $message = 'Mä olen menee kauppa'): void
    {
        config(['services.ai.key' => 'sk-test']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['text' => json_encode([
                'reply' => 'Selvä homma!',
                'translation' => 'Alright!',
                'correction' => $correction,
                'goal_reached' => false,
            ])]],
        ])]);

        $body = ['messages' => [['role' => 'user', 'content' => $message]]];
        if ($scenario !== null) {
            $body['scenario'] = $scenario;
        }

        $this->postJson('/api/chat', $body)->assertOk();
    }

    public function test_a_correction_becomes_a_review_card(): void
    {
        $this->chat('Mä meen kauppaan');

        $mistake = $this->user->mistakes()->sole();
        $this->assertSame('Mä olen menee kauppa', $mistake->attempt);
        $this->assertSame('Mä meen kauppaan', $mistake->corrected);
        $this->assertNull($mistake->source);
        $this->assertSame(UserMistake::STATUS_NEW, $mistake->status);
        $this->assertNull($mistake->next_review_at); // due right away

        // The message itself was counted (a number, never the content).
        $this->assertSame(1, ChatDay::where('user_id', $this->user->id)->sole()->messages);
    }

    public function test_scenario_corrections_remember_their_scene(): void
    {
        $this->chat('Mä meen kauppaan', 'kauppa');

        $this->assertSame('kauppa', $this->user->mistakes()->sole()->source);
    }

    public function test_identical_corrections_are_not_stored(): void
    {
        // Case/punctuation variants of the learner's own sentence = praise,
        // not a mistake worth a card.
        $this->chat('mä olen menee kauppa!', message: 'Mä olen menee kauppa');

        $this->assertSame(0, $this->user->mistakes()->count());
    }

    public function test_repeating_a_mistake_reopens_the_card(): void
    {
        UserMistake::create([
            'user_id' => $this->user->id,
            'attempt' => 'vanha yritys',
            'corrected' => 'Mä meen kauppaan',
            'hash' => UserMistake::keyFor('Mä meen kauppaan'),
            'status' => UserMistake::STATUS_MASTERED,
            'next_review_at' => now()->addDays(30),
        ]);

        $this->chat('Mä meen kauppaan');

        $mistake = $this->user->mistakes()->sole(); // reopened, not duplicated
        $this->assertSame(UserMistake::STATUS_LEARNING, $mistake->status);
        $this->assertNull($mistake->next_review_at);
        $this->assertSame('Mä olen menee kauppa', $mistake->attempt);
    }

    public function test_mock_chat_counts_the_message_but_stores_no_mistake(): void
    {
        config(['services.ai.key' => null, 'services.ai.gemini_key' => null, 'services.ai.openrouter_key' => null]);

        $this->postJson('/api/chat', ['messages' => [['role' => 'user', 'content' => 'Moi!']]])
            ->assertOk()
            ->assertJsonPath('source', 'mock');

        $this->assertSame(0, $this->user->mistakes()->count());
        $this->assertSame(1, ChatDay::where('user_id', $this->user->id)->sole()->messages);
    }

    private function makeMistake(array $overrides = []): UserMistake
    {
        return UserMistake::create(array_merge([
            'user_id' => $this->user->id,
            'attempt' => 'Mä olen menee kauppa',
            'corrected' => 'Mä meen kauppaan',
            'hash' => UserMistake::keyFor('Mä meen kauppaan'),
        ], $overrides));
    }

    public function test_review_serves_due_cards_only(): void
    {
        $due = $this->makeMistake();
        $this->makeMistake([
            'corrected' => 'Emmä tiiä',
            'hash' => UserMistake::keyFor('Emmä tiiä'),
            'status' => UserMistake::STATUS_REVIEW,
            'next_review_at' => now()->addDays(3), // scheduled - not due
        ]);

        $res = $this->getJson('/api/mistakes/review')->assertOk();

        $this->assertSame([$due->id], array_column($res->json('cards'), 'id'));
        $this->assertSame(1, $res->json('due_count'));
    }

    public function test_grading_walks_the_ladder_and_logs_the_review(): void
    {
        $mistake = $this->makeMistake();

        $this->postJson("/api/mistakes/{$mistake->id}/grade", ['grade' => 'good'])
            ->assertOk()
            ->assertJsonPath('mistake.status', UserMistake::STATUS_LEARNING)
            ->assertJsonPath('due_count', 0);

        $this->assertTrue($mistake->fresh()->next_review_at->isFuture());
        $this->assertSame(1, ReviewLog::where('user_id', $this->user->id)->where('kind', 'mistake')->count());

        // "again" lapses the card back and keeps it due now.
        $this->postJson("/api/mistakes/{$mistake->id}/grade", ['grade' => 'again'])
            ->assertOk()
            ->assertJsonPath('mistake.status', UserMistake::STATUS_LEARNING)
            ->assertJsonPath('due_count', 1);
    }

    public function test_cannot_touch_another_users_mistakes(): void
    {
        $other = User::create(['name' => 'Muu', 'email' => 'muu@example.com', 'password' => bcrypt('password')]);
        $theirs = UserMistake::create([
            'user_id' => $other->id,
            'attempt' => 'x',
            'corrected' => 'Onks näin?',
            'hash' => UserMistake::keyFor('Onks näin?'),
        ]);

        $this->postJson("/api/mistakes/{$theirs->id}/grade", ['grade' => 'good'])->assertNotFound();

        $this->deleteJson("/api/mistakes/{$theirs->id}")->assertOk(); // scoped no-op
        $this->assertNotNull($theirs->fresh());
    }

    public function test_deleting_a_card_removes_it(): void
    {
        $mistake = $this->makeMistake();

        $this->deleteJson("/api/mistakes/{$mistake->id}")->assertOk();

        $this->assertNull($mistake->fresh());
    }

    public function test_dashboard_stats_report_due_mistakes(): void
    {
        $this->makeMistake();

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('stats.mistakes_due', 1);
    }

    public function test_insights_include_the_loyly_plus_week(): void
    {
        $this->makeMistake(); // caught this week, still due
        ChatDay::create(['user_id' => $this->user->id, 'date' => today()->toDateString(), 'messages' => 12]);
        ChatDay::create(['user_id' => $this->user->id, 'date' => today()->subDays(10)->toDateString(), 'messages' => 99]); // outside the window
        ReviewLog::create(['user_id' => $this->user->id, 'kind' => 'mistake', 'grade' => 'good', 'created_at' => now()]);

        $this->getJson('/api/insights/week')
            ->assertOk()
            ->assertJsonPath('chat_messages', 12)
            ->assertJsonPath('mistakes_caught', 1)
            ->assertJsonPath('mistakes_cleared', 1)
            ->assertJsonPath('mistakes_due', 1);
    }
}
