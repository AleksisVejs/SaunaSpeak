<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckpointTest extends TestCase
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

    private function studySentences(string $level, int $count): void
    {
        $lesson = Lesson::create(['title' => "Lektio {$level}", 'level' => $level, 'order_index' => 1]);

        for ($i = 1; $i <= $count; $i++) {
            $sentence = $lesson->sentences()->create([
                'finnish_text' => "Lause {$i}",
                'english_text' => "Sentence {$i}",
            ]);
            UserProgress::create([
                'user_id' => $this->user->id,
                'sentence_id' => $sentence->id,
                'status' => UserProgress::STATUS_LEARNING,
                'next_review_at' => now()->addDay(),
            ]);
        }
    }

    public function test_few_studied_sentences_get_a_placement_quiz(): void
    {
        $this->studySentences('A0', 3);

        $this->getJson('/api/checkpoint/A0')
            ->assertOk()
            ->assertJsonPath('ready', true)
            ->assertJsonPath('placement', true);
    }

    public function test_checkpoint_not_ready_when_the_level_has_no_sentences(): void
    {
        $this->getJson('/api/checkpoint/A0')
            ->assertOk()
            ->assertJsonPath('ready', false)
            ->assertJsonPath('needed', 5);
    }

    public function test_checkpoint_serves_only_studied_sentences_of_the_level(): void
    {
        $this->studySentences('A0', 6);
        // A1 sentences exist but are not part of an A0 checkpoint.
        Lesson::create(['title' => 'Muu', 'level' => 'A1', 'order_index' => 2])
            ->sentences()->create(['finnish_text' => 'Muu lause', 'english_text' => 'Other']);

        $res = $this->getJson('/api/checkpoint/A0')->assertOk()->assertJsonPath('ready', true);
        $this->assertCount(6, $res->json('sentences'));
    }

    public function test_passing_awards_badge_and_bonus_once(): void
    {
        $this->studySentences('A0', 6);

        $this->postJson('/api/checkpoint/A0', ['correct' => 9, 'total' => 10])
            ->assertOk()
            ->assertJsonPath('passed', true)
            ->assertJsonPath('xp_gained', 100);

        $this->assertNotNull($this->user->fresh()->checkpoints['A0'] ?? null);

        // Retaking a passed checkpoint is allowed but earns no second bonus.
        $this->postJson('/api/checkpoint/A0', ['correct' => 10, 'total' => 10])
            ->assertOk()
            ->assertJsonPath('passed', true)
            ->assertJsonPath('xp_gained', 0);
    }

    public function test_failing_records_no_badge(): void
    {
        $this->studySentences('A0', 6);

        $this->postJson('/api/checkpoint/A0', ['correct' => 5, 'total' => 10])
            ->assertOk()
            ->assertJsonPath('passed', false)
            ->assertJsonPath('xp_gained', 0);

        $this->assertNull($this->user->fresh()->checkpoints['A0'] ?? null);
    }

    public function test_unknown_level_is_rejected(): void
    {
        $this->getJson('/api/checkpoint/Z9')->assertStatus(422);
    }
}
