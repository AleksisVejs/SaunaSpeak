<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Password reset, data export, account deletion, and intake placement. */
class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('old-password'),
        ]);
    }

    public function test_forgot_password_sends_branded_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/password/forgot', ['email' => 'testi@example.com'])
            ->assertOk();

        Notification::assertSentTo($this->user, ResetPassword::class);
    }

    public function test_forgot_password_does_not_reveal_unknown_emails(): void
    {
        Notification::fake();

        $known = $this->postJson('/api/password/forgot', ['email' => 'testi@example.com'])->json('message');
        $unknown = $this->postJson('/api/password/forgot', ['email' => 'nobody@example.com'])->json('message');

        $this->assertSame($known, $unknown);
        Notification::assertNothingSentTo(new User(['email' => 'nobody@example.com']));
    }

    public function test_reset_password_updates_password_and_revokes_tokens(): void
    {
        $this->user->createToken('saunaspeak');
        $token = Password::createToken($this->user);

        $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => 'testi@example.com',
            'password' => 'brand-new-password',
        ])->assertOk();

        $this->user->refresh();
        $this->assertTrue(Hash::check('brand-new-password', $this->user->password));
        $this->assertSame(0, $this->user->tokens()->count());
    }

    public function test_reset_password_rejects_bad_token(): void
    {
        $this->postJson('/api/password/reset', [
            'token' => 'garbage',
            'email' => 'testi@example.com',
            'password' => 'brand-new-password',
        ])->assertStatus(422);
    }

    public function test_export_returns_the_learners_data(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/account/export')
            ->assertOk()
            ->assertJsonPath('account.email', 'testi@example.com')
            ->assertJsonStructure(['exported_at', 'account', 'sentence_progress', 'word_bank', 'review_history', 'product_funnel']);
    }

    public function test_delete_requires_the_correct_password(): void
    {
        Sanctum::actingAs($this->user);

        $this->deleteJson('/api/account', ['password' => 'wrong'])->assertStatus(422);
        $this->assertNotNull(User::find($this->user->id));

        $this->deleteJson('/api/account', ['password' => 'old-password'])->assertOk();
        $this->assertNull(User::find($this->user->id));
    }

    public function test_intake_level_seeds_placement_on_a_blank_account(): void
    {
        $lessonA = Lesson::create(['title' => 'One', 'level' => 'A0', 'order_index' => 1]);
        $lessonB = Lesson::create(['title' => 'Two', 'level' => 'A0', 'order_index' => 2]);
        foreach ([$lessonA, $lessonB] as $lesson) {
            $lesson->sentences()->createMany([
                ['finnish_text' => 'Moi.', 'english_text' => 'Hi.'],
                ['finnish_text' => 'Kiitos.', 'english_text' => 'Thanks.'],
            ]);
        }

        Sanctum::actingAs($this->user);

        // "rusty" skips two lessons -> all four sentences seeded as reviews.
        $this->postJson('/api/preferences', ['preferences' => ['level' => 'rusty']])->assertOk();
        $this->assertSame(4, $this->user->progress()->where('status', 'review')->count());
        $this->assertSame(0, $this->user->progress()->where('next_review_at', '<=', now())->count());

        // Re-running the intake must never touch existing progress.
        $this->postJson('/api/preferences', ['preferences' => ['level' => 'some']])->assertOk();
        $this->assertSame(4, $this->user->progress()->count());
    }
}
