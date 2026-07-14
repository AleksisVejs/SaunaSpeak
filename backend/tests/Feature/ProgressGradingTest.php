<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Sentence;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgressGradingTest extends TestCase
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
            'finnish_text' => 'Mä oon Anna.',
            'written_text' => 'Minä olen Anna.',
            'english_text' => "I'm Anna.",
        ]);

        Sanctum::actingAs($this->user);
    }

    private function complete(string $grade): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/progress/complete', [
            'sentence_id' => $this->sentence->id,
            'grade' => $grade,
        ]);
    }

    public function test_good_advances_one_stage_with_expanding_intervals(): void
    {
        $this->complete('good')->assertOk()->assertJsonPath('status', UserProgress::STATUS_LEARNING);
        $this->complete('good')->assertOk()->assertJsonPath('status', UserProgress::STATUS_REVIEW);
        $this->complete('good')->assertOk()->assertJsonPath('status', UserProgress::STATUS_MASTERED);

        $progress = $this->user->progress()->first();
        // Base interval is 14 days; the +-15% anti-pileup fuzz makes the
        // scheduled gap land anywhere in 12-16 days.
        $this->assertTrue($progress->next_review_at->greaterThan(now()->addDays(11)));
        $this->assertTrue($progress->next_review_at->lessThan(now()->addDays(17)));
        $this->assertSame('mastered', $progress->status);
    }

    public function test_mastered_intervals_compound_instead_of_capping(): void
    {
        // A mastered item with a 100-day history graded "good" again must
        // stretch further than the old fixed 30-day ceiling (100 x ease 2.5,
        // capped at 365).
        $this->user->progress()->create([
            'sentence_id' => $this->sentence->id,
            'status' => UserProgress::STATUS_MASTERED,
            'ease' => 2.5,
            'interval_days' => 100,
            'next_review_at' => now(),
        ]);

        $this->complete('good')->assertOk()->assertJsonPath('status', UserProgress::STATUS_MASTERED);

        $progress = $this->user->progress()->first();
        $this->assertGreaterThan(100, $progress->interval_days);
        $this->assertLessThanOrEqual(365, $progress->interval_days);
    }

    public function test_again_lapses_back_to_learning_and_is_due_immediately(): void
    {
        $this->complete('good');
        $this->complete('good'); // now in review

        $response = $this->complete('again');
        $response->assertOk()->assertJsonPath('status', UserProgress::STATUS_LEARNING);
        $this->assertSame(2, $response->json('xp_gained'));

        $progress = $this->user->progress()->first();
        $this->assertTrue($progress->next_review_at->lessThanOrEqualTo(now()));
    }

    public function test_easy_skips_a_stage(): void
    {
        $this->complete('easy')->assertOk()->assertJsonPath('status', UserProgress::STATUS_REVIEW);
    }

    public function test_grade_defaults_to_good_and_awards_full_xp(): void
    {
        $response = $this->postJson('/api/progress/complete', ['sentence_id' => $this->sentence->id]);

        $response->assertOk()
            ->assertJsonPath('status', UserProgress::STATUS_LEARNING)
            ->assertJsonPath('xp_gained', 10);
    }

    public function test_invalid_grade_is_rejected(): void
    {
        $this->complete('perfect')->assertStatus(422);
    }
}
