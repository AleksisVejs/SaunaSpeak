<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\ReviewLog;
use App\Models\Sentence;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin panel API. Read-heavy: platform stats and the user list, plus one
 * write action — comping/revoking Löyly+ manually (support cases, friends).
 * Admins are promoted only via `php artisan user:promote <email>`.
 */
class AdminController extends Controller
{
    public function stats(): JsonResponse
    {
        $now = now();

        return response()->json([
            'users_total' => User::count(),
            'users_new_7d' => User::where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'users_active_today' => User::whereDate('last_active_date', today())->count(),
            'users_active_7d' => User::where('last_active_date', '>=', today()->subDays(7))->count(),
            'premium_count' => User::where('premium_until', '>', $now)->count(),
            'reviews_today' => ReviewLog::whereDate('created_at', today())->count(),
            'reviews_7d' => ReviewLog::where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'sentences_mastered_total' => UserProgress::where('status', UserProgress::STATUS_MASTERED)->count(),
            'content' => [
                'lessons' => Lesson::count(),
                'sentences' => Sentence::count(),
            ],
        ]);
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
            ->paginate(25, ['id', 'name', 'email', 'xp', 'streak', 'last_active_date', 'premium_until', 'is_admin', 'created_at']);

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
}
