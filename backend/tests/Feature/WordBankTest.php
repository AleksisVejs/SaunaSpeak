<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WordBankTest extends TestCase
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

    public function test_saving_a_word_is_idempotent(): void
    {
        $this->postJson('/api/words', ['word' => 'Löylyä', 'gloss' => 'steam'])->assertCreated();
        $this->postJson('/api/words', ['word' => 'löylyä', 'gloss' => 'steam'])->assertOk();

        $this->assertSame(1, $this->user->words()->count());
        $this->assertSame('löylyä', $this->user->words()->first()->word);
    }

    public function test_word_bank_lists_and_deletes_own_words_only(): void
    {
        $this->postJson('/api/words', ['word' => 'kiuas']);

        $other = User::create([
            'name' => 'Toinen',
            'email' => 'toinen@example.com',
            'password' => bcrypt('password'),
        ]);
        $foreign = $other->words()->create(['word' => 'sauna']);

        $this->getJson('/api/words')
            ->assertOk()
            ->assertJsonCount(1, 'words')
            ->assertJsonPath('words.0.word', 'kiuas');

        // Deleting another user's word must be a silent no-op.
        $this->deleteJson("/api/words/{$foreign->id}")->assertOk();
        $this->assertSame(1, $other->words()->count());

        $mine = $this->user->words()->first();
        $this->deleteJson("/api/words/{$mine->id}")->assertOk();
        $this->assertSame(0, $this->user->words()->count());
    }
}
