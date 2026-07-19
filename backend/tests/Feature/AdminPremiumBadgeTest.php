<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The "5 Löyly+" stat and the per-row badges must tell the same story:
 * a user counted by premium_count must come back with is_premium=true
 * from /api/admin/users.
 */
class AdminPremiumBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_list_flags_premium_users(): void
    {
        config(['services.stripe.secret' => 'sk_test_x']);

        $admin = User::forceCreate([
            'name' => 'Boss', 'email' => 'boss@example.com',
            'password' => bcrypt('password'), 'is_admin' => true,
        ]);
        User::create([
            'name' => 'Subscriber', 'email' => 'sub@example.com',
            'password' => bcrypt('password'), 'premium_until' => now()->addDays(30),
        ]);
        User::create([
            'name' => 'Free', 'email' => 'free@example.com',
            'password' => bcrypt('password'),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/stats')->assertJsonPath('premium_count', 1);

        $response = $this->getJson('/api/admin/users')->assertOk();
        $byEmail = collect($response->json('data'))->keyBy('email');

        // Every row must carry the flag - a Collection::each() callback that
        // returns false (e.g. an arrow-fn assignment of a falsy value) stops
        // the loop and strands the rest of the page without it.
        $this->assertTrue($byEmail['sub@example.com']['is_premium'], 'subscriber row must carry the badge flag');
        $this->assertFalse($byEmail['free@example.com']['is_premium']);
        $this->assertFalse($byEmail['boss@example.com']['is_premium']);
    }

    /**
     * Comps and trials must not read as revenue: only a live, non-trialing
     * Stripe subscription counts as paying, and the three buckets have to
     * add back up to the headline premium_count.
     */
    public function test_premium_stat_splits_paying_from_trial_and_comp(): void
    {
        $admin = User::forceCreate([
            'name' => 'Boss', 'email' => 'boss@example.com',
            'password' => bcrypt('password'), 'is_admin' => true,
        ]);
        User::create([
            'name' => 'Payer', 'email' => 'payer@example.com',
            'password' => bcrypt('password'), 'premium_until' => now()->addDays(30),
            'stripe_subscription_id' => 'sub_paid', 'stripe_status' => 'active',
        ]);
        User::create([
            'name' => 'Trialist', 'email' => 'trial@example.com',
            'password' => bcrypt('password'), 'premium_until' => now()->addDays(3),
            'stripe_subscription_id' => 'sub_trial', 'stripe_status' => 'trialing',
        ]);
        // Comped by hand from the admin panel: no Stripe subscription at all.
        User::create([
            'name' => 'Friend', 'email' => 'friend@example.com',
            'password' => bcrypt('password'), 'premium_until' => now()->addDays(30),
        ]);
        // Lapsed well past the renewal grace - outside every bucket.
        User::create([
            'name' => 'Lapsed', 'email' => 'lapsed@example.com',
            'password' => bcrypt('password'), 'premium_until' => now()->subDays(10),
            'stripe_subscription_id' => 'sub_old', 'stripe_status' => 'canceled',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/stats')
            ->assertJsonPath('premium_count', 3)
            ->assertJsonPath('premium_paying', 1)
            ->assertJsonPath('premium_trialing', 1)
            ->assertJsonPath('premium_comped', 1);
    }

    /**
     * Subscribers who predate the stripe_status column have a NULL status.
     * They are counted as paying, not silently dropped from every bucket.
     */
    public function test_legacy_subscriber_without_status_counts_as_paying(): void
    {
        $admin = User::forceCreate([
            'name' => 'Boss', 'email' => 'boss@example.com',
            'password' => bcrypt('password'), 'is_admin' => true,
        ]);
        User::create([
            'name' => 'Legacy', 'email' => 'legacy@example.com',
            'password' => bcrypt('password'), 'premium_until' => now()->addDays(30),
            'stripe_subscription_id' => 'sub_legacy',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/stats')
            ->assertJsonPath('premium_count', 1)
            ->assertJsonPath('premium_paying', 1)
            ->assertJsonPath('premium_trialing', 0)
            ->assertJsonPath('premium_comped', 0);
    }
}
