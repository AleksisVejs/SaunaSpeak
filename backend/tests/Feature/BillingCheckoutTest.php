<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingCheckoutTest extends TestCase
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

        config([
            'services.stripe.secret' => 'sk_test_x',
            'services.stripe.price_id' => 'price_x',
            'app.url' => 'https://sauna.test',
        ]);
    }

    public function test_checkout_redirects_to_stripes_hosted_page(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_123',
        ])]);

        $this->postJson('/api/billing/checkout')
            ->assertOk()
            ->assertJsonPath('url', 'https://checkout.stripe.com/c/pay/cs_123');

        Http::assertSent(fn ($request) => str_contains($request->body(), 'success_url')
            && str_contains($request->body(), 'cancel_url')
            && ! str_contains($request->body(), 'payment_method_types'));
    }

    public function test_first_checkout_includes_the_free_trial(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_123',
        ])]);

        $this->postJson('/api/billing/checkout')->assertOk();

        Http::assertSent(fn ($r) => str_contains(
            urldecode($r->body()),
            'subscription_data[trial_period_days]=3'
        ));
    }

    public function test_returning_subscribers_get_no_second_trial(): void
    {
        $this->user->update(['stripe_customer_id' => 'cus_123']);

        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_123',
        ])]);

        $this->postJson('/api/billing/checkout')->assertOk();

        Http::assertSent(fn ($r) => ! str_contains(urldecode($r->body()), 'trial_period_days'));
    }

    public function test_billing_status_reports_trial_eligibility(): void
    {
        $this->getJson('/api/billing')
            ->assertOk()
            ->assertJsonPath('trial_eligible', true)
            ->assertJsonPath('trial_days', 3);

        $this->user->update(['stripe_customer_id' => 'cus_123']);

        $this->getJson('/api/billing')->assertJsonPath('trial_eligible', false);
    }

    public function test_checkout_uses_the_yearly_price_when_asked(): void
    {
        config(['services.stripe.price_id_yearly' => 'price_yr']);

        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_123',
        ])]);

        $this->postJson('/api/billing/checkout', ['plan' => 'yearly'])->assertOk();

        Http::assertSent(fn ($r) => str_contains($r->body(), 'price_yr'));
    }

    public function test_yearly_checkout_without_a_yearly_price_is_unavailable(): void
    {
        config(['services.stripe.price_id_yearly' => null]);

        $this->postJson('/api/billing/checkout', ['plan' => 'yearly'])
            ->assertStatus(503);
    }

    public function test_billing_status_reports_available_plans(): void
    {
        config(['services.stripe.price_id_yearly' => 'price_yr']);

        $this->getJson('/api/billing')
            ->assertOk()
            ->assertJsonPath('plans.monthly', true)
            ->assertJsonPath('plans.yearly', true);
    }

    public function test_cancel_sets_cancel_at_period_end_and_keeps_access(): void
    {
        $periodEnd = now()->addWeeks(3)->timestamp;
        $this->user->update(['stripe_subscription_id' => 'sub_123']);

        Http::fake(['api.stripe.com/v1/subscriptions/sub_123' => Http::response([
            'id' => 'sub_123',
            'status' => 'active',
            'cancel_at_period_end' => true,
            'items' => ['data' => [['current_period_end' => $periodEnd]]],
        ])]);

        $this->postJson('/api/billing/cancel')
            ->assertOk()
            ->assertJsonPath('cancel_at_period_end', true);

        Http::assertSent(fn ($r) => str_contains($r->body(), 'cancel_at_period_end=true'));

        // Access continues to the end of the paid period.
        $this->user->refresh();
        $this->assertTrue($this->user->isPremium());
        $this->assertSame($periodEnd, $this->user->premium_until->timestamp);
    }

    public function test_resume_undoes_a_pending_cancellation(): void
    {
        $this->user->update(['stripe_subscription_id' => 'sub_123']);

        Http::fake(['api.stripe.com/v1/subscriptions/sub_123' => Http::response([
            'id' => 'sub_123',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'items' => ['data' => [['current_period_end' => now()->addMonth()->timestamp]]],
        ])]);

        $this->postJson('/api/billing/resume')
            ->assertOk()
            ->assertJsonPath('cancel_at_period_end', false);

        Http::assertSent(fn ($r) => str_contains($r->body(), 'cancel_at_period_end=false'));
    }

    public function test_cancel_without_subscription_is_404(): void
    {
        $this->postJson('/api/billing/cancel')->assertNotFound();
    }
}
