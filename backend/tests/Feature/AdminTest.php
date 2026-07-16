<?php

namespace Tests\Feature;

use App\Models\ChatDay;
use App\Models\ReviewLog;
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

    public function test_activity_matrix_shows_per_day_cells(): void
    {
        Sanctum::actingAs($this->admin);

        // Member reviewed twice yesterday and chatted 5 messages today.
        ReviewLog::create(['user_id' => $this->member->id, 'kind' => 'sentence', 'grade' => 'good', 'created_at' => now()->subDay()]);
        ReviewLog::create(['user_id' => $this->member->id, 'kind' => 'word', 'grade' => 'easy', 'created_at' => now()->subDay()]);
        ChatDay::create(['user_id' => $this->member->id, 'date' => today()->toDateString(), 'messages' => 5]);

        $res = $this->getJson('/api/admin/activity?days=14&search=member')->assertOk()->json();

        $this->assertCount(14, $res['dates']);
        $this->assertCount(1, $res['users']);

        $row = $res['users'][0];
        $this->assertSame(2, $row['active_days']);
        $this->assertSame([2, 0], $row['cells'][12]); // yesterday: 2 reviews
        $this->assertSame([0, 5], $row['cells'][13]); // today: 5 chat messages

        // Locked down like everything else here.
        Sanctum::actingAs($this->member);
        $this->getJson('/api/admin/activity')->assertStatus(403);
    }

    public function test_retention_cohorts_count_who_came_back(): void
    {
        Sanctum::actingAs($this->admin);

        // Two learners joined two weeks ago; only one was active this week.
        // created_at is guarded, so backdate explicitly after creation.
        $joined = today()->startOfWeek()->subWeeks(2);
        $stayed = User::create([
            'name' => 'Stayer', 'email' => 'stayer@example.com', 'password' => bcrypt('password'),
        ]);
        $ghost = User::create([
            'name' => 'Ghost', 'email' => 'ghost@example.com', 'password' => bcrypt('password'),
        ]);
        $stayed->forceFill(['created_at' => $joined])->save();
        $ghost->forceFill(['created_at' => $joined])->save();
        ReviewLog::create(['user_id' => $stayed->id, 'kind' => 'sentence', 'grade' => 'good', 'created_at' => now()]);

        $cohorts = collect($this->getJson('/api/admin/retention')->assertOk()->json('cohorts'));
        $cohort = $cohorts->firstWhere('week', today()->startOfWeek()->subWeeks(2)->toDateString());

        $this->assertSame(2, $cohort['size']);
        $this->assertSame(0, $cohort['active'][0]); // signup week: nobody reviewed
        $this->assertSame(1, $cohort['active'][2]); // this week: the stayer came back
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
