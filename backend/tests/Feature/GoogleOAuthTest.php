<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/** "Continue with Google": account creation, linking, and repeat logins. */
class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'https://saunaspeak.test/api/auth/google/callback',
            'app.url' => 'https://saunaspeak.test',
            // The callback prefers FRONTEND_URL (dev split-host setup); the
            // local .env sets it, so neutralize it for deterministic asserts.
            'services.stripe.frontend_url' => null,
        ]);
    }

    private function mockGoogleUser(string $id, string $email, string $name = 'Aino Testi'): void
    {
        $googleUser = new SocialiteUser;
        $googleUser->id = $id;
        $googleUser->email = $email;
        $googleUser->name = $name;

        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_redirect_endpoint_sends_browser_to_google(): void
    {
        $response = $this->get('/api/auth/google/redirect?tz=Europe/Helsinki');

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_redirect_endpoint_404s_when_unconfigured(): void
    {
        config(['services.google.client_id' => null]);

        $this->get('/api/auth/google/redirect')->assertNotFound();
    }

    public function test_callback_creates_a_new_verified_user_and_hands_off_a_token(): void
    {
        $this->mockGoogleUser('g-123', 'uusi@example.com');

        $response = $this->get('/api/auth/google/callback');

        $user = User::where('email', 'uusi@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('g-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(1, $user->tokens()->count());

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://saunaspeak.test/auth/google#token=', $location);
        $this->assertStringContainsString('&new=1', $location);
    }

    public function test_callback_links_google_to_an_existing_email_account(): void
    {
        $existing = User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('secret-123'),
        ]);

        $this->mockGoogleUser('g-456', 'testi@example.com');

        $response = $this->get('/api/auth/google/callback');

        $this->assertSame('g-456', $existing->fresh()->google_id);
        $this->assertSame(1, User::count());
        $this->assertStringContainsString('&new=0', $response->headers->get('Location'));
    }

    public function test_callback_logs_in_a_returning_google_user_without_duplicating(): void
    {
        User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('secret-123'),
            'google_id' => 'g-789',
        ]);

        $this->mockGoogleUser('g-789', 'testi@example.com');

        $this->get('/api/auth/google/callback')->assertRedirect();
        $this->assertSame(1, User::count());
    }

    public function test_callback_applies_the_timezone_from_state(): void
    {
        $this->mockGoogleUser('g-321', 'tz@example.com');
        $state = base64_encode(json_encode(['tz' => 'Europe/Helsinki']));

        $this->get('/api/auth/google/callback?state='.urlencode($state));

        $this->assertSame('Europe/Helsinki', User::where('email', 'tz@example.com')->first()->timezone);
    }

    public function test_callback_failure_bounces_to_login_with_a_flag(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('denied'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/api/auth/google/callback')
            ->assertRedirect('https://saunaspeak.test/login?oauth=failed');
    }
}
