<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Löyly+ subscription billing via Stripe Checkout — deliberately dependency-
 * free (plain HTTPS calls) so it runs anywhere PHP does, including cPanel.
 *
 * Flow: checkout() creates a Stripe Checkout session and the frontend
 * redirects to it; Stripe calls webhook() on payment/cancellation and we
 * mirror the subscription state onto users.premium_until. portal() opens
 * Stripe's hosted customer portal for cancel/card management.
 */
class BillingController extends Controller
{
    private const API = 'https://api.stripe.com/v1';

    /**
     * Pinned so Stripe can never reshape a response under us. Must match the
     * version set on the webhook endpoint in the Stripe Dashboard, otherwise
     * webhook payloads arrive in a different shape than the ones we fetch.
     */
    private const API_VERSION = '2026-06-24.dahlia';

    /** GET /api/billing — current plan state for the profile/upgrade pages. */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'billing_enabled' => (bool) config('services.stripe.secret'),
            'is_premium' => $user->isPremium(),
            'premium_until' => $user->premium_until,
            'has_subscription' => (bool) $user->stripe_subscription_id,
        ]);
    }

    /** POST /api/billing/checkout — start a subscription purchase. */
    public function checkout(Request $request): JsonResponse
    {
        $priceId = config('services.stripe.price_id');

        if (! config('services.stripe.secret') || ! $priceId) {
            return response()->json(['message' => 'Billing is not configured.'], 503);
        }

        $user = $request->user();
        $appUrl = rtrim(config('app.url'), '/');

        // No payment_method_types: Stripe picks the eligible methods from the
        // Dashboard settings, which converts better than hardcoding cards.
        $payload = array_filter([
            'mode' => 'subscription',
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => 1,
            'customer' => $user->stripe_customer_id,
            'customer_email' => $user->stripe_customer_id ? null : $user->email,
            'client_reference_id' => (string) $user->id,
            'subscription_data[metadata][user_id]' => (string) $user->id,
            'success_url' => "{$appUrl}/upgrade?status=success",
            'cancel_url' => "{$appUrl}/upgrade?status=cancelled",
        ]);

        $response = $this->stripe()
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->asForm()
            ->post(self::API.'/checkout/sessions', $payload);

        if (! $response->successful()) {
            // Never log the response wholesale — it can echo request params.
            Log::warning('Stripe checkout failed', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'error' => $response->json('error.message'),
            ]);

            return response()->json(['message' => 'Could not start checkout. Try again.'], 502);
        }

        return response()->json(['url' => $response->json('url')]);
    }

    /** POST /api/billing/portal — Stripe's hosted portal (cancel, card, invoices). */
    public function portal(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! config('services.stripe.secret') || ! $user->stripe_customer_id) {
            return response()->json(['message' => 'No subscription to manage.'], 404);
        }

        $response = $this->stripe()->asForm()->post(self::API.'/billing_portal/sessions', [
            'customer' => $user->stripe_customer_id,
            'return_url' => rtrim(config('app.url'), '/').'/profile',
        ]);

        return $response->successful()
            ? response()->json(['url' => $response->json('url')])
            : response()->json(['message' => 'Could not open the billing portal.'], 502);
    }

    /** POST /api/billing/webhook — Stripe events keep premium state in sync. */
    public function webhook(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        // json()->get() rejects non-scalar values — use the full array instead.
        $event = $request->json()->all();
        $object = $event['data']['object'] ?? [];

        match ($event['type'] ?? null) {
            'checkout.session.completed' => $this->activate($object),
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->syncSubscription($object),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    /** First payment done: link Stripe ids and open premium. */
    private function activate(array $session): void
    {
        // A completed session is not necessarily a paid one: delayed payment
        // methods finish the session and settle later.
        $paid = in_array($session['payment_status'] ?? '', ['paid', 'no_payment_required'], true);
        $user = User::find($session['client_reference_id'] ?? null);

        if (! $paid || ! $user) {
            return;
        }

        $user->update([
            'stripe_customer_id' => $session['customer'] ?? $user->stripe_customer_id,
            'stripe_subscription_id' => $session['subscription'] ?? $user->stripe_subscription_id,
        ]);

        // Prefer the subscription's real billing period over a guess. If the
        // fetch fails we still open access; customer.subscription.updated will
        // correct the date well before this provisional window runs out.
        $subscription = $this->fetchSubscription($user->stripe_subscription_id);

        $subscription
            ? $this->syncSubscription($subscription)
            : $user->update(['premium_until' => now()->addDays(35)]);
    }

    /** Renewals extend premium_until; cancellations let it lapse at period end. */
    private function syncSubscription(array $subscription): void
    {
        $user = User::where('stripe_subscription_id', $subscription['id'] ?? '')->first();
        if (! $user) {
            return;
        }

        $periodEnd = $this->periodEnd($subscription);
        $status = $subscription['status'] ?? '';

        if (in_array($status, ['active', 'trialing'], true) && $periodEnd) {
            // Store the true period end; the renewal-processing grace window
            // lives in User::isPremium() so displayed dates stay honest.
            $user->update(['premium_until' => now()->setTimestamp($periodEnd)]);
        } elseif (in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true)) {
            $user->update([
                'premium_until' => $periodEnd ? now()->setTimestamp($periodEnd) : now(),
                'stripe_subscription_id' => $status === 'canceled' ? null : $user->stripe_subscription_id,
            ]);
        }
    }

    /**
     * End of the paid period, as a unix timestamp.
     *
     * API version 2025-03-31 moved current_period_end off the subscription and
     * onto each subscription item. The top-level read is the pre-2025 fallback.
     */
    private function periodEnd(array $subscription): ?int
    {
        $end = $subscription['items']['data'][0]['current_period_end']
            ?? $subscription['current_period_end']
            ?? null;

        return $end ? (int) $end : null;
    }

    /** Retrieve a subscription, or null if Stripe is unreachable. */
    private function fetchSubscription(?string $id): ?array
    {
        if (! $id) {
            return null;
        }

        $response = $this->stripe()->get(self::API."/subscriptions/{$id}");

        if (! $response->successful()) {
            Log::warning('Stripe subscription fetch failed', [
                'subscription' => $id,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->json();
    }

    /** Authenticated Stripe client, pinned to one API version. */
    private function stripe(): PendingRequest
    {
        return Http::withToken(config('services.stripe.secret'))
            ->withHeaders(['Stripe-Version' => self::API_VERSION])
            ->timeout(15);
    }

    /** Stripe webhook signature: HMAC-SHA256 over "{t}.{payload}". */
    private function verifySignature(Request $request): bool
    {
        $secret = config('services.stripe.webhook_secret');
        if (! $secret) {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        // "t=1699,v1=abc,v1=def" — a v1 per active signing secret, so during a
        // secret rotation more than one is present and any may be the match.
        foreach (explode(',', $request->header('Stripe-Signature', '')) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');

            match (trim($key)) {
                't' => $timestamp = $value,
                'v1' => $signatures[] = $value,
                default => null,
            };
        }

        if (! $timestamp || ! $signatures) {
            return false;
        }

        // Reject stale events (replay protection).
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
