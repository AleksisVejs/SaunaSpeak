<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->lesson = Lesson::create(['title' => 'Testilektio', 'level' => 'A0', 'order_index' => 1]);

        Sanctum::actingAs($this->user);
    }

    private function makeSentences(int $count): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $ids[] = $this->lesson->sentences()->create([
                'finnish_text' => "Lause {$i}",
                'english_text' => "Sentence {$i}",
            ])->id;
        }

        return $ids;
    }

    public function test_session_size_follows_request_within_limits(): void
    {
        $this->makeSentences(15);

        $this->getJson('/api/today-session?size=5')->assertOk()->assertJsonCount(5, 'sentences');
        // Clamped to the 3..12 window.
        $this->getJson('/api/today-session?size=1')->assertOk()->assertJsonCount(3, 'sentences');
        $this->getJson('/api/today-session?size=99')->assertOk()->assertJsonCount(12, 'sentences');
    }

    public function test_due_reviews_are_interleaved_with_new_sentences(): void
    {
        $ids = $this->makeSentences(4);

        // First two sentences are due reviews; the last two are unseen.
        foreach ([$ids[0], $ids[1]] as $id) {
            UserProgress::create([
                'user_id' => $this->user->id,
                'sentence_id' => $id,
                'status' => UserProgress::STATUS_LEARNING,
                'next_review_at' => now()->subHour(),
            ]);
        }

        $res = $this->getJson('/api/today-session?size=4')->assertOk()->assertJsonPath('due_count', 2);
        $statuses = collect($res->json('sentences'))->pluck('status')->all();

        // Weave, not blocks: review, new, review, new.
        $this->assertSame(['learning', 'new', 'learning', 'new'], $statuses);
    }

    public function test_all_due_and_no_fresh_still_works(): void
    {
        $ids = $this->makeSentences(2);

        foreach ($ids as $id) {
            UserProgress::create([
                'user_id' => $this->user->id,
                'sentence_id' => $id,
                'status' => UserProgress::STATUS_REVIEW,
                'next_review_at' => now()->subDay(),
            ]);
        }

        $this->getJson('/api/today-session')
            ->assertOk()
            ->assertJsonCount(2, 'sentences')
            ->assertJsonPath('due_count', 2);
    }
}
