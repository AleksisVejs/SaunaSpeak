<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_lessons_report_started_and_mastered_counts(): void
    {
        $user = User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('password'),
        ]);
        Sanctum::actingAs($user);

        $lesson = Lesson::create(['title' => 'Eka', 'level' => 'A0', 'order_index' => 1]);
        $a = $lesson->sentences()->create(['finnish_text' => 'Moi.', 'english_text' => 'Hi.']);
        $b = $lesson->sentences()->create(['finnish_text' => 'Kiitos.', 'english_text' => 'Thanks.']);
        $lesson->sentences()->create(['finnish_text' => 'Nähään.', 'english_text' => 'See you.']);

        // One sentence merely started, one mastered, one untouched.
        UserProgress::create([
            'user_id' => $user->id,
            'sentence_id' => $a->id,
            'status' => UserProgress::STATUS_LEARNING,
            'next_review_at' => now()->addDay(),
        ]);
        UserProgress::create([
            'user_id' => $user->id,
            'sentence_id' => $b->id,
            'status' => UserProgress::STATUS_MASTERED,
            'next_review_at' => now()->addDays(30),
        ]);

        $this->getJson('/api/lessons')
            ->assertOk()
            ->assertJsonPath('lessons.0.started_count', 2)
            ->assertJsonPath('lessons.0.mastered_count', 1)
            ->assertJsonPath('lessons.0.sentences_count', 3);
    }
}
