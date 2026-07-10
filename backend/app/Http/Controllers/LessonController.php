<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\UserProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $masteredBySentence = $user->progress()
            ->where('status', UserProgress::STATUS_MASTERED)
            ->pluck('sentence_id');

        // Sentences the learner has touched at all (any SRS status), per lesson.
        // The path uses this to unlock the next lesson as soon as the daily
        // session starts serving it — locks must match what sessions do.
        $startedByLesson = $user->progress()
            ->join('sentences', 'sentences.id', '=', 'user_progress.sentence_id')
            ->selectRaw('sentences.lesson_id, count(*) as started')
            ->groupBy('sentences.lesson_id')
            ->pluck('started', 'sentences.lesson_id');

        $lessons = Lesson::withCount('sentences')
            ->orderBy('order_index')
            ->get()
            ->map(function (Lesson $lesson) use ($masteredBySentence, $startedByLesson) {
                $lesson->mastered_count = (int) $lesson->sentences()
                    ->whereIn('id', $masteredBySentence)
                    ->count();
                $lesson->started_count = (int) ($startedByLesson[$lesson->id] ?? 0);
                // withCount aggregates arrive as strings on hosts without
                // mysqlnd native types — the frontend does arithmetic on this.
                $lesson->sentences_count = (int) $lesson->sentences_count;

                return $lesson;
            });

        return response()->json(['lessons' => $lessons]);
    }

    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        $statuses = $request->user()->progress()
            ->whereIn('sentence_id', $lesson->sentences()->pluck('id'))
            ->pluck('status', 'sentence_id');

        $lesson->load('sentences', 'pattern');
        $lesson->sentences->each(function ($sentence) use ($statuses) {
            $sentence->status = $statuses[$sentence->id] ?? 'new';
        });

        return response()->json(['lesson' => $lesson]);
    }
}
