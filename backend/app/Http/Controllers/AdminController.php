<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\ReviewLog;
use App\Models\Sentence;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json([
            'users_total' => User::count(),
            'users_new_7d' => User::where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'users_active_today' => User::whereDate('last_active_date', today())->count(),
            'users_active_7d' => User::where('last_active_date', '>=', today()->subDays(7))->count(),
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

        $users->getCollection()->each(fn (User $u) => $u->is_premium = $u->isPremium());

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
        $user->update(['is_recorder' => ! $user->is_recorder]);

        return response()->json([
            'id' => $user->id,
            'is_recorder' => $user->fresh()->is_recorder,
        ]);
    }
}
