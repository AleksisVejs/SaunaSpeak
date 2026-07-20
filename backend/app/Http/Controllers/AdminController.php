<?php

namespace App\Http\Controllers;

use App\Models\ChatDay;
use App\Models\Lesson;
use App\Models\ReviewLog;
use App\Models\Sentence;
use App\Models\User;
use App\Models\UserProgress;
use App\Support\Listening;
use App\Support\MinimalPairs;
use App\Support\Transforms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        $pairClips = MinimalPairs::wordClips();

        // One definition of "has Löyly+ right now", reused by the four counts
        // below so the breakdown always sums to the headline number.
        $premium = User::where('premium_until', '>', $now->copy()->subDays(2));

        return response()->json([
            'users_total' => User::count(),
            'users_new_7d' => User::where('created_at', '>=', $now->copy()->subDays(7))->count(),
            // Counted from what learners actually did, NOT from
            // last_active_date: that column is the streak anchor and is only
            // written when a session is *completed*, so anyone who reviews a
            // few cards and closes the tab never sets it. See activeUserIds().
            'users_active_today' => $this->activeUserIds(today())->count(),
            'users_active_7d' => $this->activeUserIds(today()->subDays(7))->count(),
            'users_active_30d' => $this->activeUserIds(today()->subDays(30))->count(),
            'users_verified' => User::whereNotNull('email_verified_at')->count(),
            // Mirrors User::isPremium()'s 2-day renewal grace so this count
            // always matches the Löyly+ badges in the users list below.
            'premium_count' => $premium->clone()->count(),
            // ...and the same total split by where the access came from, so a
            // handful of comps and trials never reads as revenue. No Stripe
            // subscription means it was granted by hand (togglePremium below).
            'premium_paying' => $premium->clone()
                ->whereNotNull('stripe_subscription_id')
                ->where(fn ($q) => $q->where('stripe_status', '!=', 'trialing')->orWhereNull('stripe_status'))
                ->count(),
            'premium_trialing' => $premium->clone()
                ->whereNotNull('stripe_subscription_id')
                ->where('stripe_status', 'trialing')
                ->count(),
            'premium_comped' => $premium->clone()->whereNull('stripe_subscription_id')->count(),
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
                // Kuulo drill words: their own section - the drill teaches a
                // vowel contrast, so human coverage here matters most of all.
                'pairs_total' => count($pairClips),
                'pairs_human' => count(array_filter(
                    $pairClips,
                    fn (?string $url) => str_starts_with((string) $url, '/audio/human/'),
                )),
                'pairs_eleven' => count(array_filter(
                    $pairClips,
                    fn (?string $url) => str_starts_with((string) $url, '/audio/eleven/'),
                )),
            ],
        ]);
    }

    /**
     * Distinct users who reviewed or chatted on/after $from - the two per-day
     * activity streams the app records.
     *
     * Deliberately not users.last_active_date: that is only written by
     * SessionController::completeSession(), so it means "last day they
     * finished a whole session", not "last day they were here". Anyone who
     * grades a few cards and leaves mid-session never sets it, and would
     * otherwise be counted as having never shown up at all.
     */
    private function activeUserIds(Carbon $from): Collection
    {
        return ReviewLog::where('created_at', '>=', $from->copy()->startOfDay())
            ->distinct()
            ->pluck('user_id')
            ->concat(ChatDay::where('date', '>=', $from->toDateString())->distinct()->pluck('user_id'))
            ->unique();
    }

    /**
     * Last day each of $ids actually did something, across both streams.
     * Returns [user_id => 'Y-m-d'], missing entries meaning "never".
     */
    private function lastActivityDates(Collection $ids): Collection
    {
        $reviews = ReviewLog::whereIn('user_id', $ids)
            ->selectRaw('user_id, max(created_at) as last_at')
            ->groupBy('user_id')
            ->pluck('last_at', 'user_id')
            ->map(fn ($at) => Carbon::parse($at)->toDateString());

        $chat = ChatDay::whereIn('user_id', $ids)
            ->selectRaw('user_id, max(date) as last_date')
            ->groupBy('user_id')
            ->pluck('last_date', 'user_id')
            ->map(fn ($d) => substr((string) $d, 0, 10));

        return $ids->mapWithKeys(function ($id) use ($reviews, $chat) {
            $latest = collect([$reviews[$id] ?? null, $chat[$id] ?? null])->filter()->max();

            return [$id => $latest];
        })->filter();
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
        // Looks at all of history, not just the window, so the "quiet Nd" chip
        // stays right for someone whose last visit predates the grid.
        $lastActivity = $this->lastActivityDates($ids);

        $rows = $users->getCollection()->map(function (User $u) use ($reviewsByUser, $chatByUser, $dates, $lastActivity) {
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
                // Streak anchor (last *completed* session) - kept for context.
                'last_active_date' => $u->last_active_date?->toDateString(),
                // What the status chip must use: last day they did anything.
                'last_activity_date' => $lastActivity[$u->id] ?? null,
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

    /**
     * GET /api/admin/export - the whole panel as one unpaginated JSON
     * snapshot, for offline analysis. Deliberately excludes names and email
     * addresses: nothing in the analysis needs them, and the file is meant
     * to be handed around. Users are identified by their panel id, so a row
     * worth chasing can still be looked up in the Users tab.
     *
     * Carries its own caveats in `notes` so the file can be read months
     * later, or by someone who wasn't here, without repeating our mistakes.
     */
    public function export(): JsonResponse
    {
        $now = now();

        // Per-user activity, aggregated in three queries rather than per row.
        $reviewStats = ReviewLog::selectRaw('user_id, count(*) as total, min(created_at) as first_at, max(created_at) as last_at')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');
        $reviewDays = ReviewLog::get(['user_id', 'created_at'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => $r->created_at->toDateString())->unique()->count());
        $chatStats = ChatDay::selectRaw('user_id, count(*) as days, sum(messages) as messages, min(date) as first_date')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $users = User::orderBy('id')
            ->get(['id', 'email_verified_at', 'xp', 'streak', 'last_active_date', 'premium_until',
                'stripe_subscription_id', 'stripe_status', 'review_emails', 'checkpoints', 'scenarios_done',
                'listening_done', 'transforms_done', 'is_admin', 'is_recorder', 'created_at'])
            ->map(function (User $u) use ($reviewStats, $reviewDays, $chatStats) {
                $reviews = $reviewStats->get($u->id);
                $chat = $chatStats->get($u->id);
                $firstReview = $reviews?->first_at ? Carbon::parse($reviews->first_at) : null;
                $firstChat = $chat?->first_date ? Carbon::parse($chat->first_date) : null;

                // Checkpoints, listening scenes, transform drills and scenarios
                // are stored as {id => ISO timestamp} maps on the user and write
                // no ReviewLog at all. Counting only reviews and chat marked
                // people who passed a checkpoint as having never shown up.
                $maps = [
                    'checkpoints' => $u->checkpoints ?? [],
                    'listening_done' => $u->listening_done ?? [],
                    'transforms_done' => $u->transforms_done ?? [],
                    'scenarios_done' => $u->scenarios_done ?? [],
                ];
                $mapTimes = collect($maps)
                    ->flatMap(fn (array $m) => array_values($m))
                    ->filter()
                    ->map(fn ($t) => Carbon::parse($t));

                $firstSeen = collect([$firstReview, $firstChat])->concat($mapTimes)->filter()->min();

                return [
                    'id' => $u->id,
                    'created_at' => $u->created_at?->toIso8601String(),
                    'signup_week' => $u->created_at?->copy()->startOfWeek()->toDateString(),
                    'verified' => $u->email_verified_at !== null,
                    'verified_at' => $u->email_verified_at?->toIso8601String(),
                    // The headline question: did this account ever do anything?
                    'activated' => $firstSeen !== null,
                    'first_activity_at' => $firstSeen?->toIso8601String(),
                    // Null when never activated; 0 means same-day as signup.
                    'days_to_first_activity' => $firstSeen && $u->created_at
                        ? $u->created_at->copy()->startOfDay()->diffInDays($firstSeen->copy()->startOfDay())
                        : null,
                    'last_active_date' => $u->last_active_date?->toDateString(),
                    'reviews_total' => (int) ($reviews->total ?? 0),
                    'review_days' => (int) ($reviewDays[$u->id] ?? 0),
                    'chat_days' => (int) ($chat->days ?? 0),
                    'chat_messages' => (int) ($chat->messages ?? 0),
                    'checkpoints_passed' => count($maps['checkpoints']),
                    'listening_done' => count($maps['listening_done']),
                    'transforms_done' => count($maps['transforms_done']),
                    'scenarios_done' => count($maps['scenarios_done']),
                    'xp' => $u->xp,
                    'streak' => $u->streak,
                    'premium_source' => $this->premiumSource($u),
                    'premium_until' => $u->premium_until?->toIso8601String(),
                    // Opted out of mail. Defaults true, so false means the
                    // learner actively turned it off - do not email them.
                    'review_emails' => (bool) $u->review_emails,
                    // Staff and test accounts - exclude these before drawing
                    // conclusions about real learners.
                    'is_admin' => (bool) $u->is_admin,
                    'is_recorder' => (bool) $u->is_recorder,
                ];
            });

        return response()->json([
            'meta' => [
                'generated_at' => $now->toIso8601String(),
                'timezone' => config('app.timezone'),
                'today' => today()->toDateString(),
                'week_started' => today()->startOfWeek()->toDateString(),
                'schema_version' => 1,
            ],
            'notes' => [
                'identity' => 'Names and emails are deliberately omitted. `id` matches the Users tab.',
                'partial_week' => 'The current week is incomplete. In `retention.cohorts`, the last entry of every `active` array covers only '.(today()->dayOfWeekIso).' of 7 days, so it always understates. Compare finished weeks only.',
                'grace' => 'premium_count and premium_source use User::isPremium()\'s 2-day renewal grace, so a subscriber mid-renewal still counts as premium.',
                'legacy_status' => 'stripe_status was added 2026-07-20. Subscribers from before that have a null status and are reported as paying until their next webhook.',
                'analytics_gap' => 'Umami register events undercount signups (adblockers, in-app browsers, privacy tooling). This DB export is ground truth; do not compute activation from Umami denominators.',
                'activity_streams' => 'activated = ever logged a review, a chat day, a checkpoint pass, a listening scene, a transform set or a scenario. Opening a lesson without grading anything still leaves no trace.',
                'last_active_vs_activity' => 'last_active_date is the STREAK ANCHOR - written only when a session is completed, so it is null for people who reviewed but never finished one. It is not "last seen"; users_active_* and the panel status chip are counted from the activity streams instead.',
            ],
            'stats' => $this->stats()->getData(true),
            'trends' => $this->trends()->getData(true),
            'retention' => $this->retention()->getData(true),
            'users' => $users,
        ]);
    }

    /**
     * Where a user's Löyly+ came from - the export's revenue ground truth.
     *
     * Deliberately does NOT call isPremium(): that returns true for everyone
     * while billing is unconfigured (the paywall-off switch), which would
     * report every account as comped on any instance without Stripe keys.
     * Mirrors the same date window the premium_* counts use instead, so the
     * export and the Pulse tiles can never disagree.
     */
    private function premiumSource(User $user): string
    {
        if ($user->premium_until === null || $user->premium_until->copy()->addDays(2)->isPast()) {
            return 'none';
        }
        if ($user->stripe_subscription_id === null) {
            return 'comped';
        }

        return $user->stripe_status === 'trialing' ? 'trial' : 'paying';
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
            ->paginate(25, ['id', 'name', 'email', 'email_verified_at', 'xp', 'streak', 'last_active_date', 'premium_until', 'review_emails', 'is_admin', 'is_recorder', 'created_at']);

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
