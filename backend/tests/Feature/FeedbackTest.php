<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The in-app feedback box: learners write, admins read and clear. */
class FeedbackTest extends TestCase
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

    public function test_learner_can_submit_feedback(): void
    {
        Sanctum::actingAs($this->member);

        $this->postJson('/api/feedback', ['message' => '  The TTS mangles "sul"!  '])
            ->assertCreated();

        $this->assertDatabaseHas('feedback', [
            'user_id' => $this->member->id,
            'message' => 'The TTS mangles "sul"!',
        ]);
    }

    public function test_feedback_is_validated(): void
    {
        Sanctum::actingAs($this->member);

        $this->postJson('/api/feedback', ['message' => 'hi'])->assertStatus(422);
        $this->postJson('/api/feedback', ['message' => str_repeat('a', 2001)])->assertStatus(422);
        $this->postJson('/api/feedback', [])->assertStatus(422);
    }

    public function test_admin_reads_and_clears_the_inbox(): void
    {
        $note = Feedback::create([
            'user_id' => $this->member->id,
            'message' => 'Love the app, hate the robot voice.',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/feedback')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.message', 'Love the app, hate the robot voice.')
            ->assertJsonPath('data.0.user.email', 'member@example.com');

        $this->deleteJson("/api/admin/feedback/{$note->id}")->assertOk();
        $this->assertDatabaseCount('feedback', 0);
    }

    public function test_inbox_is_admin_only(): void
    {
        $note = Feedback::create([
            'user_id' => $this->member->id,
            'message' => 'Should not be deletable by non-admins.',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/admin/feedback')->assertStatus(403);
        $this->deleteJson("/api/admin/feedback/{$note->id}")->assertStatus(403);
    }
}
