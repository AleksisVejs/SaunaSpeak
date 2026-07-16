<?php

namespace App\Http\Controllers;

use App\Models\ReviewLog;
use App\Models\Sentence;
use App\Models\UserProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /** Default sentences per Sauna Session; the learner's daily goal can override within limits. */
    private const SESSION_SIZE = 8;
    private const SESSION_MIN = 3;
    private const SESSION_MAX = 12;

    private const XP_PER_SENTENCE = 10;
    private const XP_AGAIN = 2;
    private const XP_SESSION_BONUS = 50;

    /** Mirrored in the frontend (DashboardPage REPAIR_COST). */
    private const STREAK_REPAIR_COST = 200;

    /**
     * GET /api/today-session?size=N
     * Due reviews (oldest first) interleaved among brand-new sentences.
     *
     * Interleaving reviews between new items aids discrimination and retention;
     * the new sentences themselves stay in lesson order (blocked), which research
     * shows works better for beginners building initial declarative knowledge.
     */
    public function today(Request $request): JsonResponse
    {
        $data = $request->validate([
            'size' => ['sometimes', 'integer'],
        ]);

        $size = min(self::SESSION_MAX, max(self::SESSION_MIN, $data['size'] ?? self::SESSION_SIZE));
        $user = $request->user();

        $due = $user->progress()
            ->with('sentence')
            ->where('next_review_at', '<=', now())
            ->orderBy('next_review_at')
            ->limit($size)
            ->get();

        $reviews = $due->map(function (UserProgress $progress) {
            $sentence = $progress->sentence;
            $sentence->status = $progress->status;

            return $sentence;
        });

        $slotsLeft = $size - $reviews->count();
        $fresh = collect();

        if ($slotsLeft > 0) {
            $seenIds = $user->progress()->pluck('sentence_id');
            // Levels tested out of (passed checkpoint) never feed fresh
            // sentences - a placed B1 learner starts at B1, not "Moi!".
            $passedLevels = array_keys($user->checkpoints ?? []);

            $fresh = Sentence::whereNotIn('sentences.id', $seenIds)
                ->join('lessons', 'lessons.id', '=', 'sentences.lesson_id')
                ->when($passedLevels !== [], fn ($q) => $q->whereNotIn('lessons.level', $passedLevels))
                ->orderBy('lessons.order_index')
                ->orderBy('sentences.id')
                ->limit($slotsLeft)
                ->get(['sentences.*']);

            // Statement body on purpose: each() aborts if the callback
            // returns false, and an arrow-fn assignment returns the value.
            $fresh->each(function (Sentence $sentence) {
                $sentence->status = UserProgress::STATUS_NEW;
            });
        }

        return response()->json([
            'sentences' => $this->interleave($reviews, $fresh)->values(),
            'due_count' => $due->count(),
        ]);
    }

    /**
     * Evenly weave due reviews among new sentences, keeping each list's own
     * order. Starts with a review when one is due - a warm-up on familiar
     * material before the first new sentence.
     */
    private function interleave($reviews, $fresh)
    {
        $r = $reviews->count();
        $f = $fresh->count();
        $merged = [];
        $ri = 0;
        $fi = 0;

        for ($i = 0; $i < $r + $f; $i++) {
            $reviewPace = $r > 0 ? $ri / $r : 1;
            $freshPace = $f > 0 ? $fi / $f : 1;

            if ($ri < $r && ($fi >= $f || $reviewPace <= $freshPace)) {
                $merged[] = $reviews[$ri++];
            } else {
                $merged[] = $fresh[$fi++];
            }
        }

        return collect($merged);
    }

    /**
     * POST /api/progress/complete
     * Grades one sentence: the self-assessed grade drives the spaced-repetition
     * schedule (expanding intervals; a lapse sends the sentence back to learning).
     */
    public function completeSentence(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sentence_id' => ['required', 'integer', 'exists:sentences,id'],
            'grade' => ['sometimes', 'in:again,good,easy'],
        ]);

        $grade = $data['grade'] ?? 'good';
        $user = $request->user();

        $progress = $user->progress()->firstOrNew(['sentence_id' => $data['sentence_id']]);
        $current = $progress->status ?? UserProgress::STATUS_NEW;

        [$nextStatus, $intervalDays] = match ($grade) {
            // Lapse: back to learning, due again right away.
            'again' => [UserProgress::STATUS_LEARNING, 0],
            // Easy skips a stage with a longer interval.
            'easy' => match ($current) {
                UserProgress::STATUS_NEW => [UserProgress::STATUS_REVIEW, 3],
                UserProgress::STATUS_LEARNING => [UserProgress::STATUS_MASTERED, 14],
                UserProgress::STATUS_REVIEW => [UserProgress::STATUS_MASTERED, 30],
                default => [UserProgress::STATUS_MASTERED, 60],
            },
            // Good advances one stage on the expanding schedule.
            default => match ($current) {
                UserProgress::STATUS_NEW => [UserProgress::STATUS_LEARNING, 1],
                UserProgress::STATUS_LEARNING => [UserProgress::STATUS_REVIEW, 3],
                UserProgress::STATUS_REVIEW => [UserProgress::STATUS_MASTERED, 14],
                default => [UserProgress::STATUS_MASTERED, 30],
            },
        };

        // SM-2-lite: each sentence carries its own ease (2.5 = neutral).
        // Lapses shrink it, "easy" grows it, and long intervals scale by it -
        // so leeches come back sooner and solid items stretch further.
        $ease = $progress->ease ?? 2.5;
        $ease = match ($grade) {
            'again' => max(1.3, $ease - 0.2),
            'easy' => min(3.0, $ease + 0.15),
            default => $ease,
        };

        // Mastered items compound (last interval x ease) instead of being
        // pinned to a fixed ceiling - otherwise every sentence ever learned
        // returns every ~30 days forever and the review load only grows.
        // A year is the honest maximum for a language you're still learning.
        if ($current === UserProgress::STATUS_MASTERED && $grade !== 'again') {
            $previous = $progress->interval_days ?? $intervalDays;
            $intervalDays = max($intervalDays, (int) round($previous * $ease));
        } elseif ($intervalDays >= 3) {
            $intervalDays = max(2, (int) round($intervalDays * $ease / 2.5));
        }

        // +-15% fuzz so items studied together don't stay due together -
        // spreads review load across days instead of lumping it.
        if ($intervalDays >= 3) {
            $intervalDays = max(2, (int) round($intervalDays * (mt_rand(85, 115) / 100)));
        }
        $intervalDays = min(365, $intervalDays);

        $progress->fill([
            'status' => $nextStatus,
            'ease' => $ease,
            'interval_days' => $intervalDays,
            'next_review_at' => now()->addDays($intervalDays),
        ])->save();

        ReviewLog::create(['user_id' => $user->id, 'kind' => 'sentence', 'grade' => $grade, 'created_at' => now()]);

        $xp = $grade === 'again' ? self::XP_AGAIN : self::XP_PER_SENTENCE;
        $user->increment('xp', $xp);

        return response()->json([
            'xp_gained' => $xp,
            'status' => $nextStatus,
            'next_review_at' => $progress->next_review_at,
            'user' => $user->fresh(),
        ]);
    }

    /**
     * POST /api/session/complete
     * Awards the daily bonus and updates the streak (once per day).
     */
    public function completeSession(Request $request): JsonResponse
    {
        $user = $request->user();
        // The learner's calendar day, not the server's - a 23:30 session in
        // Helsinki must not count as tomorrow on a UTC box.
        $today = $user->localToday();

        $alreadyToday = $user->last_active_date !== null && $user->last_active_date->isSameDay($today);

        $bonus = 0;

        if (! $alreadyToday) {
            $continuesStreak = $user->last_active_date !== null
                && $user->last_active_date->isSameDay($today->copy()->subDay());

            $newStreak = $continuesStreak ? $user->streak + 1 : 1;

            // Every full week of streak earns a freeze (max 3 banked) -
            // insurance against the one bad day that would erase it all.
            $freezes = $user->streak_freezes;
            if ($newStreak > 0 && $newStreak % 7 === 0) {
                $freezes = min(3, $freezes + 1);
            }

            $user->update([
                'streak' => $newStreak,
                'streak_freezes' => $freezes,
                'last_active_date' => $today,
            ]);

            $bonus = self::XP_SESSION_BONUS;
            $user->increment('xp', $bonus);
        }

        return response()->json([
            'xp_gained' => $bonus,
            'streak' => $user->fresh()->streak,
            'user' => $user->fresh(),
        ]);
    }

    /**
     * POST /api/streak/repair
     * Relight a recently broken streak for XP. Days practiced since the break
     * stack on top of the restored count, so repairing never loses progress.
     */
    public function repairStreak(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->streak_repairable) {
            return response()->json(['message' => 'No streak to repair.'], 422);
        }

        if ($user->xp < self::STREAK_REPAIR_COST) {
            return response()->json(['message' => 'Not enough XP - you need '.self::STREAK_REPAIR_COST.'.'], 422);
        }

        $today = $user->localToday();

        $updates = [
            'xp' => $user->xp - self::STREAK_REPAIR_COST,
            'streak' => $user->broken_streak + $user->streak,
            'broken_streak' => 0,
            'streak_broken_date' => null,
        ];

        // Not practiced since the break: reconnect the chain so today's
        // session continues the restored streak instead of restarting at 1.
        if ($user->last_active_date === null
            || $user->last_active_date->format('Y-m-d') < $today->copy()->subDay()->format('Y-m-d')) {
            $updates['last_active_date'] = $today->copy()->subDay();
        }

        $user->update($updates);

        return response()->json(['user' => $user->fresh()]);
    }
}
