<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProgress;
use App\Models\Sentence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create($data);
        $token = $user->createToken('saunaspeak')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($data)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $user = User::where('email', $data['email'])->firstOrFail();
        $user->syncStreak();
        $token = $user->createToken('saunaspeak')->plainTextToken;

        return response()->json(['user' => $user->fresh(), 'token' => $token]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->syncStreak();

        $fresh = $user->fresh();
        $fresh->is_premium = $fresh->isPremium();

        return response()->json([
            'user' => $fresh,
            'stats' => [
                'total_sentences' => Sentence::count(),
                'mastered_count' => $user->progress()->where('status', UserProgress::STATUS_MASTERED)->count(),
                'learning_count' => $user->progress()->whereNot('status', UserProgress::STATUS_MASTERED)->count(),
                'due_count' => $user->progress()->where('next_review_at', '<=', now())->count(),
                'forecast' => $this->forecast($user),
            ],
        ]);
    }

    /** POST /api/preferences — mirror the intake-quiz preferences server-side. */
    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'preferences' => ['required', 'array'],
        ]);

        $request->user()->update(['preferences' => $data['preferences']]);

        return response()->json(['preferences' => $request->user()->fresh()->preferences]);
    }

    /**
     * Reviews coming due over the next 7 days — makes the invisible
     * spaced-repetition schedule visible on the dashboard.
     */
    private function forecast(User $user): array
    {
        $rows = $user->progress()
            ->where('next_review_at', '>', now())
            ->where('next_review_at', '<=', now()->addDays(7))
            ->get(['next_review_at']);

        $byDay = [];
        foreach ($rows as $row) {
            $day = $row->next_review_at->toDateString();
            $byDay[$day] = ($byDay[$day] ?? 0) + 1;
        }
        ksort($byDay);

        return collect($byDay)
            ->map(fn ($count, $day) => ['date' => $day, 'count' => $count])
            ->values()
            ->all();
    }
}
