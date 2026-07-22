<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The learner's own data: take it with you (export) or take it away
 * (delete). Both are GDPR table stakes for an EU product.
 */
class AccountController extends Controller
{
    /**
     * GET /api/account/export - everything we hold about this learner as one
     * JSON document (account, progress, word bank, review history).
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'xp' => $user->xp,
                'streak' => $user->streak,
                'streak_freezes' => $user->streak_freezes,
                'checkpoints' => $user->checkpoints,
                'preferences' => $user->preferences,
                'timezone' => $user->timezone,
                'review_emails' => $user->review_emails,
            ],
            'sentence_progress' => $user->progress()->with('sentence:id,finnish_text,english_text')->get()
                ->map(fn ($p) => [
                    'sentence' => $p->sentence?->finnish_text,
                    'translation' => $p->sentence?->english_text,
                    'status' => $p->status,
                    'ease' => $p->ease,
                    'next_review_at' => $p->next_review_at,
                ]),
            'word_bank' => $user->words()->get(['word', 'gloss', 'status', 'next_review_at', 'created_at']),
            'review_history' => \App\Models\ReviewLog::where('user_id', $user->id)
                ->orderBy('created_at')
                ->get(['kind', 'grade', 'created_at']),
            'product_funnel' => $user->productEvents()
                ->orderBy('created_at')
                ->get(['event', 'metadata', 'created_at']),
        ], 200, [
            'Content-Disposition' => 'attachment; filename="saunaspeak-export.json"',
        ]);
    }

    /**
     * DELETE /api/account - password-confirmed, irreversible. Cancels any
     * Stripe subscription immediately (no orphaned charges), then removes the
     * user; progress/words/tokens cascade via their foreign keys.
     */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required_without:confirm', 'string'],
            'confirm' => ['required_without:password', 'string'],
        ]);

        $user = $request->user();

        // Google-created accounts hold a random password the user has never
        // seen - they confirm by typing "delete" instead. Password accounts
        // keep the stricter password check.
        $confirmed = isset($data['password'])
            ? Hash::check($data['password'], $user->password)
            : ($user->google_id !== null && strtolower(trim($data['confirm'])) === 'delete');

        if (! $confirmed) {
            throw ValidationException::withMessages([
                'password' => [isset($data['password']) ? 'That password is not correct.' : 'Type "delete" to confirm.'],
            ]);
        }

        $this->cancelStripeSubscription($user);

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account deleted. Kiitos ja näkemiin!']);
    }

    /** Best-effort immediate cancel; deletion must not hinge on Stripe uptime. */
    private function cancelStripeSubscription($user): void
    {
        $secret = config('services.stripe.secret');
        if (! $secret || ! $user->stripe_subscription_id) {
            return;
        }

        try {
            Http::withToken($secret)
                ->withHeaders(['Stripe-Version' => BillingController::API_VERSION])
                ->timeout(15)
                ->delete('https://api.stripe.com/v1/subscriptions/'.$user->stripe_subscription_id);
        } catch (\Throwable $e) {
            Log::warning('Stripe cancel during account deletion failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
