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

    /** Relative signed URL, exactly as VerifyEmail::verificationUrl builds it. */
    private function verificationLink(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ], absolute: false);
    }

    public function test_signed_link_verifies_and_redirects_into_the_app(): void
    {
        $user = User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->get($this->verificationLink($user))->assertRedirectContains('/dashboard?verified=1');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_link_survives_a_scheme_or_host_change(): void
    {
        // cPanel's force-https / www redirects change scheme+host between
        // mail-out and click; the relative signature must not care.
        $user = User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->get('https://www.saunaspeak.test'.$this->verificationLink($user))
            ->assertRedirectContains('/dashboard?verified=1');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_tampered_link_is_rejected(): void
    {
        $user = User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Valid signature but for a different hash → signed middleware rejects
        // and the exception handler bounces into the SPA, unverified.
        $url = $this->verificationLink($user);

        $this->get(str_replace($user->id.'/', $user->id.'x/', $url))
            ->assertRedirectContains('/dashboard?verified=expired');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_expired_link_redirects_to_the_resend_banner(): void
    {
        $user = User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('password123'),
        ]);

        $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ], absolute: false);

        $this->get($url)->assertRedirectContains('/dashboard?verified=expired');
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
