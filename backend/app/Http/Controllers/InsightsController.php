<?php

namespace App\Http\Controllers;

use App\Models\ReviewLog;
use App\Models\UserProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Weekly insights: the learner's last 7 days in numbers. Recall rate is
 * good+easy over all grades - the honest measure of how much is sticking.
 */
class InsightsController extends Controller
{
    public function week(Request $request): JsonResponse
    {
        $user = $request->user();
        $since = now()->subDays(7);

        $logs = ReviewLog::where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->get(['grade', 'kind', 'created_at']);

        $total = $logs->count();
        $recalled = $logs->whereIn('grade', ['good', 'easy'])->count();

        $byDay = $logs->groupBy(fn ($log) => $log->created_at->toDateString())
            ->map->count()
            ->sortKeys();

        $newStarted = $user->progress()
            ->where('created_at', '>=', $since)
            ->count();

        return response()->json([
            'reviews' => $total,
            'recall_pct' => $total ? (int) round($recalled / $total * 100) : null,
            'word_reviews' => $logs->where('kind', 'word')->count(),
            'sentence_reviews' => $logs->where('kind', 'sentence')->count(),
            'new_sentences' => $newStarted,
            'active_days' => $byDay->count(),
            'best_day' => $byDay->isEmpty() ? null : [
                'date' => $byDay->sortDesc()->keys()->first(),
                'count' => $byDay->max(),
            ],
            'by_day' => $byDay->map(fn ($count, $date) => ['date' => $date, 'count' => $count])->values(),
            'mastered_total' => $user->progress()->where('status', UserProgress::STATUS_MASTERED)->count(),
        ]);
    }
}
