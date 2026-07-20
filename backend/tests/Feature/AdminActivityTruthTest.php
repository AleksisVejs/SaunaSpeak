<?php

namespace Tests\Feature;

use App\Models\ReviewLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * users.last_active_date is the streak anchor: SessionController only writes
 * it when a session is *completed*. Anyone who grades a few cards and closes
 * the tab leaves it null. Nothing that answers "was this learner here?" may
 * be built on it, or those people read as having never shown up at all.
 */
class AdminActivityTruthTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Boss', 'email' => 'boss@example.com',
            'password' => bcrypt('password'), 'is_admin' => true,
        ]);
    }

    /** A learner who reviewed today but never finished a session. */
    private function midSessionLearner(): User
    {
        $user = User::create([
            'name' => 'Quit Midway', 'email' => 'midway@example.com',
            'password' => bcrypt('password'),
        ]);
        foreach (range(1, 12) as $i) {
            ReviewLog::create([
                'user_id' => $user->id, 'kind' => 'sentence',
                'grade' => 'good', 'created_at' => now(),
            ]);
        }
        $this->assertNull($user->fresh()->last_active_date, 'precondition: no completed session');

        return $user;
    }

    public function test_active_counts_include_reviewers_who_never_completed_a_session(): void
    {
        $this->midSessionLearner();

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('users_active_today', 1)
            ->assertJsonPath('users_active_7d', 1)
            ->assertJsonPath('users_active_30d', 1);
    }

    public function test_activity_rows_carry_a_real_last_activity_date(): void
    {
        $user = $this->midSessionLearner();

        Sanctum::actingAs($this->admin());

        $rows = collect($this->getJson('/api/admin/activity')->assertOk()->json('users'))->keyBy('id');

        // The streak anchor stays null - that part was never wrong.
        $this->assertNull($rows[$user->id]['last_active_date']);
        // ...but the panel now has something honest to draw the chip from.
        $this->assertSame(today()->toDateString(), $rows[$user->id]['last_activity_date']);
        $this->assertSame(1, $rows[$user->id]['active_days']);
    }

    public function test_never_active_still_means_never_active(): void
    {
        $ghost = User::create([
            'name' => 'Ghost', 'email' => 'ghost@example.com',
            'password' => bcrypt('password'),
        ]);

        Sanctum::actingAs($this->admin());

        $rows = collect($this->getJson('/api/admin/activity')->assertOk()->json('users'))->keyBy('id');

        $this->assertNull($rows[$ghost->id]['last_activity_date']);
        $this->assertSame(0, $rows[$ghost->id]['active_days']);
        $this->getJson('/api/admin/stats')->assertJsonPath('users_active_today', 0);
    }

    /**
     * Checkpoints, listening scenes, transform drills and scenarios write no
     * ReviewLog - they only stamp a JSON map on the user. Counting reviews
     * alone marked a learner who passed a checkpoint as never activated.
     */
    public function test_export_counts_checkpoint_only_learners_as_activated(): void
    {
        $user = User::create([
            'name' => 'Quiz Taker', 'email' => 'quiz@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->update([
            'checkpoints' => ['A0' => now()->subDay()->toIso8601String()],
            'listening_done' => ['scene-1' => now()->toIso8601String()],
        ]);

        Sanctum::actingAs($this->admin());

        $rows = collect($this->getJson('/api/admin/export')->assertOk()->json('users'))->keyBy('id');

        $this->assertTrue($rows[$user->id]['activated'], 'a checkpoint pass is activity');
        $this->assertSame(0, $rows[$user->id]['reviews_total']);
        $this->assertSame(1, $rows[$user->id]['checkpoints_passed']);
        $this->assertSame(1, $rows[$user->id]['listening_done']);
        $this->assertNotNull($rows[$user->id]['first_activity_at']);
    }
}
