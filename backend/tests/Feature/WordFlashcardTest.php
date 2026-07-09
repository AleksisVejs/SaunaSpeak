<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserWord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WordFlashcardTest extends TestCase
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

    public function test_review_returns_new_and_due_cards_but_not_scheduled_ones(): void
    {
        // brand-new word (no next_review_at) → due
        $this->user->words()->create(['word' => 'kiuas']);
        // scheduled in the future → not due
        $this->user->words()->create([
            'word' => 'löyly',
            'status' => UserWord::STATUS_REVIEW,
            'next_review_at' => now()->addDays(5),
        ]);

        $this->getJson('/api/words/review')
            ->assertOk()
            ->assertJsonCount(1, 'cards')
            ->assertJsonPath('cards.0.word', 'kiuas')
            ->assertJsonPath('due_count', 1);
    }

    public function test_grading_good_advances_status_and_schedules_next_review(): void
    {
        $word = $this->user->words()->create(['word' => 'sauna']);

        $this->postJson("/api/words/{$word->id}/grade", ['grade' => 'good'])
            ->assertOk()
            ->assertJsonPath('word.status', UserWord::STATUS_LEARNING)
            ->assertJsonPath('due_count', 0);

        $word->refresh();
        $this->assertSame(1, $word->reviews);
        $this->assertNotNull($word->next_review_at);
        $this->assertTrue($word->next_review_at->isFuture());
    }

    public function test_grading_again_keeps_card_due_immediately(): void
    {
        $word = $this->user->words()->create([
            'word' => 'pulla',
            'status' => UserWord::STATUS_REVIEW,
            'next_review_at' => now()->subDay(),
        ]);

        $this->postJson("/api/words/{$word->id}/grade", ['grade' => 'again'])
            ->assertOk()
            ->assertJsonPath('word.status', UserWord::STATUS_LEARNING)
            ->assertJsonPath('due_count', 1);
    }

    public function test_cannot_grade_another_users_word(): void
    {
        $other = User::create([
            'name' => 'Toinen',
            'email' => 'toinen@example.com',
            'password' => bcrypt('password'),
        ]);
        $foreign = $other->words()->create(['word' => 'metsä']);

        $this->postJson("/api/words/{$foreign->id}/grade", ['grade' => 'good'])->assertNotFound();
    }
}
