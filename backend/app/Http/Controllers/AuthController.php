<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\User;
use App\Models\UserProgress;
use App\Models\Sentence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'timezone' => ['sometimes', 'nullable', 'string', 'timezone:all'],
        ]);

        $user = User::create($data);
        $token = $user->createToken('saunaspeak')->plainTextToken;

        // Fire-and-forget: a broken mail transport must never break signup.
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            Log::warning('Verification mail failed on register', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    /**
     * GET /api/email/verify/{id}/{hash} - the link from the verification
     * mail. Protected by the 'signed' middleware (tamper-proof, 60-min
     * expiry); lands in the app with a confirmation flag.
     */
    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'Invalid verification link.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $appUrl = rtrim(config('services.stripe.frontend_url') ?: config('app.url'), '/');

        return redirect()->away($appUrl.'/dashboard?verified=1');
    }

    /** POST /api/email/resend - re-send the verification mail. */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Already verified.', 'verified' => true]);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            Log::warning('Verification mail failed on resend', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not send the email right now. Try again later.'], 503);
        }

        return response()->json(['message' => 'Verification email sent.', 'verified' => false]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'timezone' => ['sometimes', 'nullable', 'string', 'timezone:all'],
        ]);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $user = User::where('email', $data['email'])->firstOrFail();
        // Keep the stored zone fresh - people move, laptops travel.
        if (! empty($data['timezone']) && $data['timezone'] !== $user->timezone) {
            $user->update(['timezone' => $data['timezone']]);
        }
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

    /** POST /api/preferences - mirror the intake-quiz preferences server-side. */
    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'timezone' => ['sometimes', 'nullable', 'string', 'timezone:all'],
        ]);

        $user = $request->user();

        $updates = ['preferences' => $data['preferences']];
        if (! empty($data['timezone'])) {
            $updates['timezone'] = $data['timezone'];
        }
        // The reminder opt-out lives in a real column (the mail command
        // filters on it), mirrored from the same preferences payload.
        if (array_key_exists('review_emails', $data['preferences'])) {
            $updates['review_emails'] = (bool) $data['preferences']['review_emails'];
        }

        $user->update($updates);
        $this->applyPlacement($user, $data['preferences']['level'] ?? null);

        return response()->json(['preferences' => $user->fresh()->preferences]);
    }

    /**
     * Intake placement: a learner who says they already know "a few words"
     * (or is brushing up) shouldn't grind through "Moi! Mä oon Anna" one card
     * at a time. Skip 1-2 starter lessons by seeding them as light reviews -
     * due within days, so the skipped material still gets checked, just not
     * taught from scratch. Only ever runs on a blank account: placement must
     * never overwrite real progress.
     */
    private function applyPlacement(User $user, ?string $level): void
    {
        $skipLessons = match ($level) {
            'some' => 1,
            'rusty' => 2,
            default => 0,
        };

        if ($skipLessons === 0 || $user->progress()->exists()) {
            return;
        }

        $sentences = Sentence::join('lessons', 'lessons.id', '=', 'sentences.lesson_id')
            ->whereIn('lessons.id', Lesson::orderBy('order_index')->limit($skipLessons)->pluck('id'))
            ->orderBy('lessons.order_index')
            ->orderBy('sentences.id')
            ->get(['sentences.id']);

        foreach ($sentences->values() as $i => $sentence) {
            // Stagger due dates over days 2-5 so the skipped block doesn't
            // land as one review lump on top of the first new lessons.
            $due = 2 + ($i % 4);
            $user->progress()->create([
                'sentence_id' => $sentence->id,
                'status' => UserProgress::STATUS_REVIEW,
                'ease' => 2.5,
                'interval_days' => $due,
                'next_review_at' => now()->addDays($due),
            ]);
        }
    }

    /**
     * POST /api/password/forgot - send the reset mail. Always answers with
     * the same message so the endpoint can't be used to probe which emails
     * have accounts.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            Log::warning('Password reset mail failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'If that email has an account, a reset link is on its way. Check your inbox.',
        ]);
    }

    /** POST /api/password/reset - token from the mail + the new password. */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->update(['password' => $password]);
            // Every stolen or forgotten session dies with the old password.
            $user->tokens()->delete();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Password updated - log in with the new one.']);
    }

    /**
     * Reviews coming due over the next 7 days - makes the invisible
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
