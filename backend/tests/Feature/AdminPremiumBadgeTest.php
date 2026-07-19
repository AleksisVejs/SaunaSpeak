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
}
