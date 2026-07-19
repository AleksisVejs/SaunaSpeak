<?php

namespace App\Http\Controllers;

use App\Models\ChatDay;
use App\Models\Lesson;
use App\Models\ReviewLog;
use App\Models\Sentence;
use App\Models\User;
use App\Models\UserProgress;
use App\Support\Listening;
use App\Support\Transforms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Admin panel API. Read-heavy: platform stats, 30-day trends and the user
 * list, plus three write actions - comping Löyly+, granting recording
 * rights, and confirming an email by hand. Admins are promoted only via
 * `php artisan user:promote <email>`.
 */
class AdminController extends Controller
{
    public function stats(): JsonResponse
    {
        $now = now();

        $manifest = File::exists(public_path('audio/words.json'))
            ? (json_decode(File::get(public_path('audio/words.json')), true) ?: [])
            : [];
        $wordsHuman = count(array_filter($manifest, fn ($url) => str_starts_with((string) $url, '/audio/human/')));
        $phrases = Transforms::ownPhrases();

        return response()->json([
            'users_total' => User::count(),
            'users_new_7d' => User::where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'users_active_today' => User::whereDate('last_active_date', today())->count(),
            'users_active_7d' => User::where('last_active_date', '>=', today()->subDays(7))->count(),
            'users_active_30d' => User::where('last_active_date', '>=', today()->subDays(30))->count(),
            'users_verified' => User::whereNotNull('email_verified_at')->count(),
            // Mirrors User::isPremium()'s 2-day renewal grace so this count
            // always matches the Löyly+ badges in the users list below.
            'premium_count' => User::where('premium_until', '>', $now->copy()->subDays(2))->count(),
            'reviews_today' => ReviewLog::whereDate('created_at', today())->count(),
            'reviews_7d' => ReviewLog::where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'sentences_mastered_total' => UserProgress::where('status', UserProgress::STATUS_MASTERED)->count(),
            'content' => [
                'lessons' => Lesson::count(),
                'sentences' => Sentence::count(),
            ],
            // Per-level shape of the course - spot thin levels at a glance.
            // Ordered in PHP: FIELD() is MySQL-only and tests run on SQLite.
            'levels' => Lesson::join('sentences', 'sentences.lesson_id', '=', 'lessons.id')
                ->selectRaw('lessons.level, count(distinct lessons.id) as lessons, count(sentences.id) as sentences')
                ->groupBy('lessons.level')
                ->get()
                ->sortBy(fn ($row) => array_search($row->level, ['A0', 'A1', 'A2', 'B1', 'B2', 'C1']))
                ->values(),
            // The road from robot to human voice, in numbers.
            'audio' => [
                'sentences_total' => Sentence::count(),
                'sentences_human' => Sentence::where('audio_url', 'like', '/audio/human/%')->count(),
                'words_total' => count($manifest),
                'words_human' => $wordsHuman,
                // The middle tier: ElevenLabs coverage is partial by design
                // (credits), so this is a mix to watch, not a bar to fill.
                'sentences_eleven' => Sentence::where('audio_url', 'like', '/audio/eleven/%')->count(),
                'words_eleven' => count(array_filter(
                    $manifest,
                    fn ($url) => str_starts_with((string) $url, '/audio/eleven/'),
                )),
                // Conversation lines live in JSON, not the sentences table.
                'listening_total' => array_sum(array_map(
                    fn (array $s) => count($s['lines']),
                    Listening::all(),
                )),
                // Taivutus phrases the course doesn't already say. Counted with
                // the sentences in the panel - same job to record.
                'phrases_total' => count($phrases),
                'phrases_human' => count(array_filter(
                    $phrases,
                    fn (array $p) => str_starts_with((string) $p['audio_url'], '/audio/human/'),
                )),
            ],
        ]);
    }

    /**
     * GET /api/admin/trends - the last 30 days, zero-filled: signups,
     * reviews done, and distinct learners who reviewed. Three single-series
     * strips on the panel; the numbers behind "is this growing?".
     */
    public function trends(): JsonResponse
    {
        $from = today()->subDays(29);

        $signups = User::where('created_at', '>=', $from)
            ->get(['created_at'])
            ->countBy(fn ($u) => $u->created_at->toDateString());

        $logs = ReviewLog::where('created_at', '>=', $from)->get(['user_id', 'created_at']);
        $reviews = $logs->countBy(fn ($r) => $r->created_at->toDateString());
        $actives = $logs
            ->groupBy(fn ($r) => $r->created_at->toDateString())
            ->map(fn ($rows) => $rows->pluck('user_id')->unique()->count());

        $days = collect(range(29, 0))->map(function ($daysAgo) use ($signups, $reviews, $actives) {
            $date = today()->subDays($daysAgo)->toDateString();

            return [
                'date' => $date,
                'signups' => (int) ($signups[$date] ?? 0),
                'reviews' => (int) ($reviews[$date] ?? 0),
                'actives' => (int) ($actives[$date] ?? 0),
            ];
        });

        return response()->json(['days' => $days->values()]);
    }

    /**
     * GET /api/admin/activity?days=&search=&page= - the retention matrix:
     * one row per user, one [reviews, chat messages] cell per day. Built
     * from review_logs and chat_days, the two per-day activity streams the
     * app records - "was this learner here on Tuesday?" answered in one
     * glance. Ordered by last active so the live learners come first.
     */
    public function activity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'days' => ['sometimes', 'integer', 'min:7', 'max:60'],
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $window = (int) ($data['days'] ?? 30);
        $from = today()->subDays($window - 1);

        $users = User::query()
            ->when($data['search'] ?? null, function ($query, $search) {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderByDesc('last_active_date')
            ->orderByDesc('id')
            ->paginate(50, ['id', 'name', 'email', 'streak', 'last_active_date', 'premium_until', 'created_at']);

        $ids = $users->getCollection()->pluck('id');

        $reviewsByUser = ReviewLog::whereIn('user_id', $ids)
            ->where('created_at', '>=', $from->copy()->startOfDay())
            ->get(['user_id', 'created_at'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->countBy(fn ($r) => $r->created_at->toDateString()));

        $chatByUser = ChatDay::whereIn('user_id', $ids)
            ->where('date', '>=', $from->toDateString())
            ->get(['user_id', 'date', 'messages'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->mapWithKeys(
                fn ($c) => [substr((string) $c->date, 0, 10) => (int) $c->messages]
            ));

        $dates = collect(range($window - 1, 0))->map(fn ($d) => today()->subDays($d)->toDateString());

        $rows = $users->getCollection()->map(function (User $u) use ($reviewsByUser, $chatByUser, $dates) {
            $reviews = $reviewsByUser->get($u->id) ?? collect();
            $chat = $chatByUser->get($u->id) ?? collect();
            $cells = $dates->map(fn ($date) => [(int) ($reviews[$date] ?? 0), (int) ($chat[$date] ?? 0)]);

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'streak' => $u->streak,
                'is_premium' => $u->isPremium(),
                'created_at' => $u->created_at->toDateString(),
                'last_active_date' => $u->last_active_date?->toDateString(),
                'active_days' => $cells->filter(fn ($c) => $c[0] + $c[1] > 0)->count(),
                'cells' => $cells,
            ];
        });

        return response()->json([
            'dates' => $dates,
            'users' => $rows->values(),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'total' => $users->total(),
        ]);
    }

    /**
     * GET /api/admin/retention - weekly signup cohorts: of the people who
     * joined in week X, how many came back in week X+1, X+2, ...? The
     * "who stayed" question in one table. Activity = reviewed or chatted
     * (the same day-level streams as the activity matrix).
     */
    public function retention(): JsonResponse
    {
        $start = today()->startOfWeek()->subWeeks(7);

        $members = User::where('created_at', '>=', $start)->get(['id', 'created_at']);

        // Distinct activity dates per user across both streams.
        $activeDates = ReviewLog::where('created_at', '>=', $start)
            ->get(['user_id', 'created_at'])
            ->map(fn ($r) => ['user_id' => $r->user_id, 'date' => $r->created_at->toDateString()])
            ->concat(ChatDay::where('date', '>=', $start->toDateString())
                ->get(['user_id', 'date'])
                ->map(fn ($c) => ['user_id' => $c->user_id, 'date' => substr((string) $c->date, 0, 10)]))
            ->groupBy('user_id')
            ->map(fn ($rows) => collect($rows)->pluck('date')->unique());

        $cohorts = $members
            ->groupBy(fn ($u) => $u->created_at->copy()->startOfWeek()->toDateString())
            ->sortKeys()
            ->map(function ($cohort, $weekStart) use ($activeDates) {
                $week0 = Carbon::parse($weekStart);
                $active = [];
                for ($i = 0; $week0->copy()->addWeeks($i)->lte(today()); $i++) {
                    $lo = $week0->copy()->addWeeks($i)->toDateString();
                    $hi = $week0->copy()->addWeeks($i + 1)->toDateString();
                    $active[] = $cohort->filter(fn ($u) => ($activeDates->get($u->id) ?? collect())
                        ->contains(fn ($d) => $d >= $lo && $d < $hi))->count();
                }

                return ['week' => $weekStart, 'size' => $cohort->count(), 'active' => $active];
            })
            ->values();

        return response()->json(['cohorts' => $cohorts]);
    }

    public function users(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $users = User::query()
            ->when($data['search'] ?? null, function ($query, $search) {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderByDesc('last_active_date')
            ->orderByDesc('id')
            ->paginate(25, ['id', 'name', 'email', 'email_verified_at', 'xp', 'streak', 'last_active_date', 'premium_until', 'is_admin', 'is_recorder', 'created_at']);

        // Statement body on purpose: an arrow-fn assignment returns the
        // assigned value, and each() aborts on the first false.
        $users->getCollection()->each(function (User $u) {
            $u->is_premium = $u->isPremium();
        });

        return response()->json($users);
    }

    /** Toggle a manual 30-day Löyly+ comp (or clear premium entirely). */
    public function togglePremium(Request $request, User $user): JsonResponse
    {
        $active = $user->premium_until !== null && $user->premium_until->isFuture();

        $user->update(['premium_until' => $active ? null : now()->addDays(30)]);

        return response()->json([
            'id' => $user->id,
            'premium_until' => $user->fresh()->premium_until,
        ]);
    }

    /**
     * Confirm an email by hand - for learners whose verification mail never
     * arrived (spam filters, corporate relays). One-way on purpose: there is
     * no legitimate admin reason to un-verify an address. Unblocks Löyly+
     * checkout, which requires a confirmed inbox.
     */
    public function verifyEmail(Request $request, User $user): JsonResponse
    {
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return response()->json([
            'id' => $user->id,
            'email_verified_at' => $user->fresh()->email_verified_at,
        ]);
    }

    /** Toggle recording-studio access (same effect as `php artisan user:recorder`). */
    public function toggleRecorder(Request $request, User $user): JsonResponse
    {
        // forceFill: is_recorder is intentionally not mass-assignable (see User).
        $user->forceFill(['is_recorder' => ! $user->is_recorder])->save();

        return response()->json([
            'id' => $user->id,
            'is_recorder' => $user->fresh()->is_recorder,
        ]);
    }
}
