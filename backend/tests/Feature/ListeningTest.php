<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Listening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListeningTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Testi Oppija',
            'email' => 'testi@example.com',
            'password' => bcrypt('password'),
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_catalog_lists_every_scene(): void
    {
        $scenes = $this->getJson('/api/listening')->assertOk()->json('scenes');

        $this->assertCount(count(Listening::all()), $scenes);
        $this->assertNotEmpty($scenes);

        $first = $scenes[0];
        $this->assertArrayHasKey('title', $first);
        $this->assertArrayHasKey('lines_count', $first);
        $this->assertFalse($first['done']);
        // The index is a summary - the transcript only ships with the scene.
        $this->assertArrayNotHasKey('lines', $first);
    }

    public function test_a_scene_ships_its_lines_with_speakers(): void
    {
        $scene = $this->getJson('/api/listening/kahvilassa')->assertOk()->json('scene');

        $this->assertNotEmpty($scene['lines']);
        $this->assertArrayHasKey('A', $scene['speakers']);

        foreach ($scene['lines'] as $line) {
            $this->assertArrayHasKey('fi', $line);
            $this->assertArrayHasKey('en', $line);
            $this->assertArrayHasKey('audio_url', $line);
            // Every line must name a speaker the scene actually defines,
            // or the player can't pick a voice for it.
            $this->assertArrayHasKey($line['who'], $scene['speakers']);
        }
    }

    public function test_every_scene_is_playable_end_to_end(): void
    {
        // A scene with a missing clip plays as silence, which reads as broken -
        // catch it here rather than in the learner's ear.
        foreach (Listening::all() as $scene) {
            foreach ($scene['lines'] as $i => $line) {
                $this->assertNotNull(
                    $line['audio_url'],
                    "Scene {$scene['id']} line {$i} has no audio - run `php artisan listening:audio`.",
                );
            }
        }
    }

    public function test_unknown_scene_is_not_found(): void
    {
        $this->getJson('/api/listening/kuuhun-lento')->assertNotFound();
        $this->postJson('/api/listening/kuuhun-lento/complete')->assertNotFound();
    }

    public function test_completing_a_scene_awards_xp_once(): void
    {
        $this->postJson('/api/listening/kahvilassa/complete')
            ->assertOk()
            ->assertJsonPath('xp_gained', 20)
            ->assertJsonPath('xp', 20);

        // Re-listening keeps the ✓ but never farms XP.
        $this->postJson('/api/listening/kahvilassa/complete')
            ->assertOk()
            ->assertJsonPath('xp_gained', 0);

        $this->assertSame(20, $this->user->fresh()->xp);
        $this->assertArrayHasKey('kahvilassa', $this->user->fresh()->listening_done);
    }

    public function test_completion_shows_up_in_the_catalog(): void
    {
        $this->postJson('/api/listening/kahvilassa/complete')->assertOk();

        $scenes = collect($this->getJson('/api/listening')->json('scenes'))->keyBy('id');

        $this->assertTrue($scenes['kahvilassa']['done']);
        $this->assertFalse($scenes['bussissa']['done']);
    }

    public function test_listening_stays_free_when_billing_is_enabled(): void
    {
        // Comprehension needs volume; volume can't sit behind the paywall.
        config(['services.stripe.secret' => 'sk_test_x']);

        $this->getJson('/api/listening')->assertOk();
        $this->getJson('/api/listening/kahvilassa')->assertOk();
        $this->postJson('/api/listening/kahvilassa/complete')->assertOk();
    }
}
