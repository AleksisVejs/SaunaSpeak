<?php

namespace Tests\Feature;

use App\Models\ReviewLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PremiumTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('password'),
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_everything_is_free_while_billing_is_unconfigured(): void
    {
        // Null AI keys too: this test must never spend real API credits.
        config(['services.stripe.secret' => null, 'services.ai.key' => null,
            'services.ai.gemini_key' => null, 'services.ai.openrouter_key' => null]);

        $this->postJson('/api/chat', ['messages' => [['role' => 'user', 'content' => 'Moi!']]])
            ->assertOk();
        $this->getJson('/api/insights/week')->assertOk();
    }

    public function test_premium_features_are_gated_when_billing_is_enabled(): void
    {
        config(['services.stripe.secret' => 'sk_test_x']);

        $this->postJson('/api/chat', ['messages' => [['role' => 'user', 'content' => 'Moi!']]])
            ->assertStatus(402)
            ->assertJsonPath('code', 'premium_required');
        $this->getJson('/api/insights/week')->assertStatus(402);

        // A live subscription opens the gate.
        $this->user->update(['premium_until' => now()->addMonth()]);
        $this->postJson('/api/chat', ['messages' => [['role' => 'user', 'content' => 'Moi!']]])
            ->assertOk();

        // An expired one closes it again.
        $this->user->update(['premium_until' => now()->subDay()]);
        $this->getJson('/api/insights/week')->assertStatus(402);
    }

    /** Post a webhook body signed the way Stripe signs it. */
    private function postWebhook(array $event, ?string $secret = 'whsec_test'): \Illuminate\Testing\TestResponse
    {
        $payload = json_encode($event);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return $this->call(
            'POST', '/api/billing/webhook', [], [], [],
            ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}", 'CONTENT_TYPE' => 'application/json'],
            $payload,
        );
    }

    public function test_webhook_with_valid_signature_activates_premium(): void
    {
        config(['services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_test']);

        $periodEnd = now()->addMonth()->timestamp;
        Http::fake(['api.stripe.com/v1/subscriptions/*' => Http::response([
            'id' => 'sub_123',
            'status' => 'active',
            'items' => ['data' => [['current_period_end' => $periodEnd]]],
        ])]);

        $this->postWebhook([
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'client_reference_id' => (string) $this->user->id,
                'payment_status' => 'paid',
                'customer' => 'cus_123',
                'subscription' => 'sub_123',
            ]],
        ])->assertOk();

        $this->user->refresh();
        $this->assertTrue($this->user->isPremium());
        $this->assertSame('cus_123', $this->user->stripe_customer_id);
        // The real billing period is used, not a 35-day guess.
        $this->assertSame($periodEnd, $this->user->premium_until->copy()->subDays(2)->timestamp);
    }

    public function test_webhook_ignores_a_completed_but_unpaid_session(): void
    {
        config(['services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_test']);

        $this->postWebhook([
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'client_reference_id' => (string) $this->user->id,
                'payment_status' => 'unpaid',
                'customer' => 'cus_123',
                'subscription' => 'sub_123',
            ]],
        ])->assertOk();

        $this->assertFalse($this->user->fresh()->isPremium());
    }

    /**
     * Regression: API version 2025-03-31 moved current_period_end from the
     * subscription onto its items. Reading the old location silently stopped
     * renewals from ever extending premium_until.
     */
    public function test_renewal_reads_period_end_from_the_subscription_item(): void
    {
        config(['services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_test']);
        $this->user->update(['stripe_subscription_id' => 'sub_123', 'premium_until' => now()->addDay()]);

        $periodEnd = now()->addMonth()->timestamp;

        $this->postWebhook([
            'type' => 'customer.subscription.updated',
            'data' => ['object' => [
                'id' => 'sub_123',
                'status' => 'active',
                // No top-level current_period_end — exactly what Stripe sends today.
                'items' => ['data' => [['current_period_end' => $periodEnd]]],
            ]],
        ])->assertOk();

        $this->assertSame($periodEnd, $this->user->fresh()->premium_until->copy()->subDays(2)->timestamp);
    }

    public function test_cancellation_keeps_access_until_the_period_ends(): void
    {
        config(['services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_test']);
        $this->user->update(['stripe_subscription_id' => 'sub_123']);

        $periodEnd = now()->addWeek()->timestamp;

        $this->postWebhook([
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => [
                'id' => 'sub_123',
                'status' => 'canceled',
                'items' => ['data' => [['current_period_end' => $periodEnd]]],
            ]],
        ])->assertOk();

        $this->user->refresh();
        $this->assertTrue($this->user->isPremium(), 'access runs to the end of the paid period');
        $this->assertSame($periodEnd, $this->user->premium_until->timestamp);
        $this->assertNull($this->user->stripe_subscription_id);
    }

    public function test_webhook_accepts_any_of_several_rotating_signatures(): void
    {
        config(['services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_test']);

        $payload = '{"type":"ping"}';
        $timestamp = time();
        $valid = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        // During a signing-secret rotation Stripe sends one v1 per active secret.
        $this->call(
            'POST', '/api/billing/webhook', [], [], [],
            ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1=fromtheoldsecret,v1={$valid}", 'CONTENT_TYPE' => 'application/json'],
            $payload,
        )->assertOk();
    }

    public function test_webhook_rejects_bad_signature(): void
    {
        config(['services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_test']);

        $this->call(
            'POST', '/api/billing/webhook', [], [], [],
            ['HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=forged', 'CONTENT_TYPE' => 'application/json'],
            '{"type":"checkout.session.completed"}',
        )->assertStatus(400);

        $this->assertNull($this->user->fresh()->premium_until);
    }

    public function test_webhook_rejects_a_replayed_event(): void
    {
        config(['services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_test']);

        $payload = '{"type":"ping"}';
        $stale = time() - 600;
        $signature = hash_hmac('sha256', $stale.'.'.$payload, 'whsec_test');

        $this->call(
            'POST', '/api/billing/webhook', [], [], [],
            ['HTTP_STRIPE_SIGNATURE' => "t={$stale},v1={$signature}", 'CONTENT_TYPE' => 'application/json'],
            $payload,
        )->assertStatus(400);
    }

    public function test_insights_report_weekly_numbers(): void
    {
        config(['services.stripe.secret' => null]);

        foreach (['good', 'easy', 'again', 'good'] as $grade) {
            ReviewLog::create(['user_id' => $this->user->id, 'kind' => 'sentence', 'grade' => $grade, 'created_at' => now()->subDay()]);
        }
        ReviewLog::create(['user_id' => $this->user->id, 'kind' => 'word', 'grade' => 'good', 'created_at' => now()]);
        // Outside the 7-day window — must not count.
        ReviewLog::create(['user_id' => $this->user->id, 'kind' => 'sentence', 'grade' => 'good', 'created_at' => now()->subDays(10)]);

        $this->getJson('/api/insights/week')
            ->assertOk()
            ->assertJsonPath('reviews', 5)
            ->assertJsonPath('recall_pct', 80) // 4 of 5 good/easy
            ->assertJsonPath('word_reviews', 1)
            ->assertJsonPath('active_days', 2);
    }
}
