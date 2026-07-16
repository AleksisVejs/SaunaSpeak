<?php

namespace App\Http\Controllers;

use App\Models\ReviewLog;
use App\Models\UserProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Weekly insights: the learner's last 7 days in numbers. Recall rate is
 * good+easy over all grades - the honest measure of how much is sticking -
 * with a delta against the week before so progress reads as a trend, not a
 * snapshot. The Löyly+ block (chat volume, Situations, mistakes caught and
 * cleared) makes the paid layer's work visible in the same card.
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
        $recallPct = $total ? (int) round($recalled / $total * 100) : null;

        // The week before, same math - a recall trend beats a bare number.
        $prev = ReviewLog::where('user_id', $user->id)
            ->whereBetween('created_at', [now()->subDays(14), $since])
            ->get(['grade']);
        $prevPct = $prev->count()
            ? (int) round($prev->whereIn('grade', ['good', 'easy'])->count() / $prev->count() * 100)
            : null;

        $byDay = $logs->groupBy(fn ($log) => $log->created_at->toDateString())
            ->map->count()
            ->sortKeys();

        $newStarted = $user->progress()
            ->where('created_at', '>=', $since)
            ->count();

        // Löyly+ activity in the same window. Situations timestamps live in
        // users.scenarios_done (id => ISO of first completion); chat volume
        // in chat_days; mistakes in user_mistakes + their review logs.
        $scenariosWeek = collect($user->scenarios_done ?? [])
            ->filter(fn ($ts) => Carbon::parse($ts)->gte($since))
            ->count();

        return response()->json([
            'reviews' => $total,
            'recall_pct' => $recallPct,
            'recall_delta' => ($recallPct !== null && $prevPct !== null) ? $recallPct - $prevPct : null,
            'word_reviews' => $logs->where('kind', 'word')->count(),
            'sentence_reviews' => $logs->where('kind', 'sentence')->count(),
            'new_sentences' => $newStarted,
            'scenarios_week' => $scenariosWeek,
            'chat_messages' => (int) $user->chatDays()->where('date', '>=', $since->toDateString())->sum('messages'),
            'mistakes_caught' => $user->mistakes()->where('created_at', '>=', $since)->count(),
            'mistakes_cleared' => $logs->where('kind', 'mistake')->whereIn('grade', ['good', 'easy'])->count(),
            'mistakes_due' => $user->mistakes()->due()->count(),
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
