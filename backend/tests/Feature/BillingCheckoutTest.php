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

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('password'),
        ]));

        config([
            'services.stripe.secret' => 'sk_test_x',
            'services.stripe.price_id' => 'price_x',
            'app.url' => 'https://sauna.test',
        ]);
    }

    public function test_embedded_checkout_when_publishable_key_is_configured(): void
    {
        config(['services.stripe.publishable' => 'pk_test_x']);

        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_123', 'client_secret' => 'cs_123_secret_abc',
        ])]);

        $this->postJson('/api/billing/checkout')
            ->assertOk()
            ->assertJsonPath('mode', 'embedded')
            ->assertJsonPath('client_secret', 'cs_123_secret_abc');

        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($body, 'ui_mode=embedded')
                && str_contains($body, urlencode('https://sauna.test/upgrade?status=success'))
                && ! str_contains($body, 'cancel_url')
                && ! str_contains($body, 'payment_method_types');
        });
    }

    public function test_hosted_redirect_without_publishable_key(): void
    {
        config(['services.stripe.publishable' => null]);

        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_123',
        ])]);

        $this->postJson('/api/billing/checkout')
            ->assertOk()
            ->assertJsonPath('mode', 'redirect')
            ->assertJsonPath('url', 'https://checkout.stripe.com/c/pay/cs_123');

        Http::assertSent(fn ($request) => str_contains($request->body(), 'success_url')
            && ! str_contains($request->body(), 'ui_mode'));
    }
}
