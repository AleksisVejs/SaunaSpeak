<?php

namespace Tests\Feature;

use App\Models\ChatDay;
use App\Models\ReviewLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The snapshot export is what gets handed around for offline analysis, so
 * two things must hold: it never leaks names or email addresses, and it
 * answers the question it exists for - who signed up and never showed up.
 */
class AdminExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Boss', 'email' => 'boss@example.com',
            'password' => bcrypt('password'), 'is_admin' => true,
        ]);
    }

    public function test_export_omits_names_and_emails(): void
    {
        User::create([
            'name' => 'Sensitive Person', 'email' => 'private@example.com',
            'password' => bcrypt('password'),
        ]);

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/export')->assertOk();

        $body = $response->getContent();
        $this->assertStringNotContainsString('private@example.com', $body, 'export must not carry email addresses');
        $this->assertStringNotContainsString('Sensitive Person', $body, 'export must not carry names');
        $this->assertStringNotContainsString('boss@example.com', $body);
    }

    public function test_export_flags_activation_and_premium_source(): void
    {
        $admin = $this->admin();

        $activated = User::create([
            'name' => 'Doer', 'email' => 'doer@example.com',
            'password' => bcrypt('password'),
        ]);
        // email_verified_at is deliberately not fillable (same guard as
        // is_admin), so confirm it the way the app does - explicitly.
        $activated->forceFill(['email_verified_at' => now()])->save();
        ReviewLog::create(['user_id' => $activated->id, 'kind' => 'sentence', 'grade' => 'good', 'created_at' => now()]);
        ChatDay::create(['user_id' => $activated->id, 'date' => today()->toDateString(), 'messages' => 3]);

        // Signed up, confirmed nothing, did nothing - the row we care about.
        $ghost = User::create([
            'name' => 'Ghost', 'email' => 'ghost@example.com',
            'password' => bcrypt('password'),
        ]);

        $comped = User::create([
            'name' => 'Friend', 'email' => 'friend@example.com',
            'password' => bcrypt('password'), 'premium_until' => now()->addDays(30),
        ]);

        Sanctum::actingAs($admin);

        $rows = collect($this->getJson('/api/admin/export')->assertOk()->json('users'))->keyBy('id');

        $this->assertTrue($rows[$activated->id]['activated']);
        $this->assertTrue($rows[$activated->id]['verified']);
        $this->assertSame(1, $rows[$activated->id]['reviews_total']);
        $this->assertSame(3, $rows[$activated->id]['chat_messages']);
        $this->assertSame(0, $rows[$activated->id]['days_to_first_activity']);
        $this->assertSame('none', $rows[$activated->id]['premium_source']);

        $this->assertFalse($rows[$ghost->id]['activated']);
        $this->assertFalse($rows[$ghost->id]['verified']);
        $this->assertNull($rows[$ghost->id]['days_to_first_activity']);
        $this->assertSame(0, $rows[$ghost->id]['reviews_total']);

        $this->assertSame('comped', $rows[$comped->id]['premium_source']);
        $this->assertTrue($rows[$admin->id]['is_admin'], 'staff must be flaggable so they can be excluded');
    }

    public function test_export_bundles_every_panel_section(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/export')
            ->assertOk()
            ->assertJsonStructure([
                'meta' => ['generated_at', 'today', 'week_started', 'schema_version'],
                'notes' => ['partial_week', 'analytics_gap'],
                'stats' => ['users_total', 'premium_paying', 'premium_comped', 'users_verified'],
                'trends' => ['days'],
                'retention' => ['cohorts'],
                'users',
            ]);
    }

    /**
     * isPremium() opens every feature while billing is unconfigured, so the
     * export must not use it to attribute revenue - otherwise a keyless
     * instance reports the entire user table as comped. This test runs with
     * no STRIPE_SECRET on purpose.
     */
    public function test_premium_source_ignores_the_paywall_off_switch(): void
    {
        config(['services.stripe.secret' => null]);

        $free = User::create([
            'name' => 'Free', 'email' => 'free@example.com',
            'password' => bcrypt('password'),
        ]);

        Sanctum::actingAs($this->admin());

        $rows = collect($this->getJson('/api/admin/export')->assertOk()->json('users'))->keyBy('id');

        $this->assertSame('none', $rows[$free->id]['premium_source']);
    }

    /**
     * Mail opt-out has to be visible somewhere before anyone sends a manual
     * blast: it defaults to true, so a false is a deliberate "don't email me".
     */
    public function test_export_and_user_list_expose_mail_opt_out(): void
    {
        $optedOut = User::create([
            'name' => 'Quiet', 'email' => 'quiet@example.com',
            'password' => bcrypt('password'), 'review_emails' => false,
        ]);
        $default = User::create([
            'name' => 'Default', 'email' => 'default@example.com',
            'password' => bcrypt('password'),
        ]);

        Sanctum::actingAs($this->admin());

        $rows = collect($this->getJson('/api/admin/export')->assertOk()->json('users'))->keyBy('id');
        $this->assertFalse($rows[$optedOut->id]['review_emails']);
        $this->assertTrue($rows[$default->id]['review_emails'], 'opt-in is the default');

        $listed = collect($this->getJson('/api/admin/users')->assertOk()->json('data'))->keyBy('id');
        $this->assertFalse($listed[$optedOut->id]['review_emails']);
    }

    public function test_export_is_admin_only(): void
    {
        $plain = User::create([
            'name' => 'Nosy', 'email' => 'nosy@example.com',
            'password' => bcrypt('password'),
        ]);

        Sanctum::actingAs($plain);

        $this->getJson('/api/admin/export')->assertForbidden();
    }
}
