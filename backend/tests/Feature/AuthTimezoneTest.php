<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The browser timezone rides along on auth requests, but it's cosmetic:
 * ICU zones Laravel's `timezone` rule rejects (Asia/Calcutta et al.) must
 * never 422 a login or signup - see the production incident of 2026-07-16.
 */
class AuthTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_login_accepts_legacy_icu_timezone_and_stores_it(): void
    {
        $this->makeUser();

        // Chrome on many systems still reports the pre-1993 alias.
        $this->postJson('/api/login', [
            'email' => 'testi@example.com',
            'password' => 'password123',
            'timezone' => 'Asia/Calcutta',
        ])->assertOk();

        $this->assertSame('Asia/Calcutta', User::first()->timezone);
    }

    public function test_login_drops_unparseable_timezone_instead_of_failing(): void
    {
        $user = $this->makeUser();
        $user->update(['timezone' => 'Europe/Helsinki']);

        $this->postJson('/api/login', [
            'email' => 'testi@example.com',
            'password' => 'password123',
            'timezone' => 'Not/A_Zone',
        ])->assertOk();

        // Junk is ignored; the previously stored zone survives.
        $this->assertSame('Europe/Helsinki', $user->fresh()->timezone);
    }

    public function test_register_drops_unparseable_timezone_instead_of_failing(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Testi',
            'email' => 'uusi@example.com',
            'password' => 'password123',
            'timezone' => 'Not/A_Zone',
        ])->assertCreated();

        $this->assertNull(User::where('email', 'uusi@example.com')->first()->timezone);
    }
}
