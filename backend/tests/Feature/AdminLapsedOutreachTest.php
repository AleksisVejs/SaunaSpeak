<?php

namespace Tests\Feature;

use App\Models\ChatDay;
use App\Models\ReviewLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The win-back list. Every case here is a way the list could quietly hand
 * back the wrong person: someone still mid-first-session, someone who did
 * come back, someone who only ever chatted, or someone already written to.
 * Each of those costs a real learner a duplicate or misdirected mail.
 */
class AdminLapsedOutreachTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Boss', 'email' => 'boss@example.com',
            'password' => bcrypt('password'), 'is_admin' => true,
        ]);
    }

    private function learner(string $email): User
    {
        return User::create([
            'name' => 'Learner', 'email' => $email, 'password' => bcrypt('password'),
        ]);
    }

    /** @param  array<int,int>  $daysAgo */
    private function reviewedOn(User $user, array $daysAgo): void
    {
        foreach ($daysAgo as $d) {
            ReviewLog::create([
                'user_id' => $user->id, 'kind' => 'sentence',
                'grade' => 'good', 'created_at' => now()->subDays($d),
            ]);
        }
    }

    public function test_lists_a_learner_who_showed_up_once_and_never_returned(): void
    {
        $lapsed = $this->learner('once@example.com');
        $this->reviewedOn($lapsed, [5]);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/lapsed')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('users.0.email', 'once@example.com')
            ->assertJsonPath('users.0.days_since', 5);
    }

    public function test_two_sessions_in_one_day_is_still_only_day_one(): void
    {
        $user = $this->learner('twice-one-day@example.com');
        // Same calendar day, two sittings - one active day, not two.
        $this->reviewedOn($user, [4, 4]);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/lapsed')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('users.0.email', 'twice-one-day@example.com');
    }

    public function test_a_learner_who_came_back_on_another_day_is_excluded(): void
    {
        $returned = $this->learner('returned@example.com');
        $this->reviewedOn($returned, [6, 3]);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/lapsed')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_a_chat_only_second_day_counts_as_coming_back(): void
    {
        $user = $this->learner('chatted-back@example.com');
        $this->reviewedOn($user, [6]);
        ChatDay::create([
            'user_id' => $user->id, 'date' => now()->subDays(3)->toDateString(), 'messages' => 4,
        ]);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/lapsed')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_todays_first_session_is_never_offered(): void
    {
        $fresh = $this->learner('today@example.com');
        $this->reviewedOn($fresh, [0]);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/lapsed')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_yesterdays_signup_is_held_back_by_the_default_grace(): void
    {
        $yesterday = $this->learner('yesterday@example.com');
        $this->reviewedOn($yesterday, [1]);

        Sanctum::actingAs($this->admin());

        // Default min_days=2 leaves them today to return on their own...
        $this->getJson('/api/admin/lapsed')->assertOk()->assertJsonPath('total', 0);
        // ...but the admin can ask for them explicitly.
        $this->getJson('/api/admin/lapsed?min_days=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('users.0.email', 'yesterday@example.com');
    }

    public function test_marking_removes_them_from_the_next_list(): void
    {
        $a = $this->learner('a@example.com');
        $b = $this->learner('b@example.com');
        $this->reviewedOn($a, [5]);
        $this->reviewedOn($b, [5]);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/lapsed')->assertOk()->assertJsonPath('total', 2);

        $this->postJson('/api/admin/lapsed/mark', ['ids' => [$a->id]])
            ->assertOk()
            ->assertJsonPath('marked', 1);

        $this->getJson('/api/admin/lapsed')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('users.0.email', 'b@example.com');

        $this->assertNotNull($a->fresh()->outreach_emailed_at);
    }

    public function test_undo_puts_a_copied_but_unsent_learner_back(): void
    {
        $user = $this->learner('oops@example.com');
        $this->reviewedOn($user, [5]);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/lapsed/mark', ['ids' => [$user->id]])->assertOk();
        $this->getJson('/api/admin/lapsed')->assertOk()->assertJsonPath('total', 0);

        $this->postJson('/api/admin/lapsed/mark', ['ids' => [$user->id], 'undo' => true])
            ->assertOk()
            ->assertJsonPath('undo', true);

        $this->getJson('/api/admin/lapsed')->assertOk()->assertJsonPath('total', 1);
        $this->assertNull($user->fresh()->outreach_emailed_at);
    }

    public function test_a_learner_who_never_did_anything_is_not_a_win_back(): void
    {
        // Registered, never reviewed, never chatted: no day 1 to lapse from.
        $this->learner('ghost@example.com');

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/lapsed')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_the_endpoints_are_admin_only(): void
    {
        $user = $this->learner('nosy@example.com');
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/lapsed')->assertForbidden();
        $this->postJson('/api/admin/lapsed/mark', ['ids' => [$user->id]])->assertForbidden();
    }
}
