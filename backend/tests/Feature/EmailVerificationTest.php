<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_a_verification_mail(): void
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => 'password123',
        ])->assertCreated();

        Notification::assertSentTo(
            User::where('email', 'testi@example.com')->first(),
            VerifyEmail::class
        );
    }

    public function test_signed_link_verifies_and_redirects_into_the_app(): void
    {
        $user = User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('password123'),
        ]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertRedirectContains('/dashboard?verified=1');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_tampered_link_is_rejected(): void
    {
        $user = User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Valid signature but for a different hash → signed middleware 403s.
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get(str_replace($user->id.'/', $user->id.'x/', $url))->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_sends_again_and_reports_already_verified(): void
    {
        Notification::fake();

        $user = User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('password123'),
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/email/resend')->assertOk()->assertJsonPath('verified', false);
        Notification::assertSentTo($user, VerifyEmail::class);

        $user->markEmailAsVerified();
        $this->postJson('/api/email/resend')->assertOk()->assertJsonPath('verified', true);
    }
}
