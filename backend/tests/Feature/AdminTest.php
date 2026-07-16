<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Boss', 'email' => 'boss@example.com',
            'password' => bcrypt('password'), 'is_admin' => true,
        ]);
        $this->member = User::create([
            'name' => 'Member', 'email' => 'member@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_non_admins_are_locked_out(): void
    {
        Sanctum::actingAs($this->member);

        $this->getJson('/api/admin/stats')->assertStatus(403);
        $this->getJson('/api/admin/users')->assertStatus(403);
        $this->postJson("/api/admin/users/{$this->admin->id}/premium")->assertStatus(403);
    }

    public function test_admin_sees_stats_and_users(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('users_total', 2)
            ->assertJsonStructure(['premium_count', 'reviews_7d', 'content' => ['lessons', 'sentences']]);

        $this->getJson('/api/admin/users?search=member')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'member@example.com');
    }

    public function test_admin_can_comp_and_revoke_premium(): void
    {
        Sanctum::actingAs($this->admin);
        config(['services.stripe.secret' => 'sk_test_x']); // billing on → gate real

        $this->postJson("/api/admin/users/{$this->member->id}/premium")->assertOk();
        $this->assertTrue($this->member->fresh()->isPremium());

        $this->postJson("/api/admin/users/{$this->member->id}/premium")->assertOk();
        $this->assertFalse($this->member->fresh()->isPremium());
    }

    public function test_admin_can_confirm_an_email_by_hand(): void
    {
        Sanctum::actingAs($this->admin);
        $this->assertFalse($this->member->hasVerifiedEmail());

        $this->postJson("/api/admin/users/{$this->member->id}/verify-email")
            ->assertOk()
            ->assertJsonPath('id', $this->member->id);

        $this->assertTrue($this->member->fresh()->hasVerifiedEmail());

        // Idempotent: confirming again keeps the original timestamp.
        $first = $this->member->fresh()->email_verified_at;
        $this->postJson("/api/admin/users/{$this->member->id}/verify-email")->assertOk();
        $this->assertEquals($first, $this->member->fresh()->email_verified_at);

        // And it is admin-only, like every other write action here.
        Sanctum::actingAs($this->member);
        $this->postJson("/api/admin/users/{$this->admin->id}/verify-email")->assertStatus(403);
    }

    public function test_promote_command_grants_and_revokes(): void
    {
        $this->artisan('user:promote', ['email' => 'member@example.com'])->assertSuccessful();
        $this->assertTrue($this->member->fresh()->is_admin);

        $this->artisan('user:promote', ['email' => 'member@example.com', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($this->member->fresh()->is_admin);

        $this->artisan('user:promote', ['email' => 'ghost@example.com'])->assertFailed();
    }
}
