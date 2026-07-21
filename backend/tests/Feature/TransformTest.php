<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Themes;
use App\Support\Transforms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransformTest extends TestCase
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

    public function test_catalog_lists_every_set(): void
    {
        $sets = $this->getJson('/api/transforms')->assertOk()->json('sets');

        $this->assertCount(count(Transforms::all()), $sets);
        $this->assertNotEmpty($sets);

        $first = $sets[0];
        $this->assertArrayHasKey('rule', $first);
        $this->assertArrayHasKey('items_count', $first);
        $this->assertFalse($first['done']);
        // The catalog is a summary - the answers ship with the set itself.
        $this->assertArrayNotHasKey('items', $first);
    }

    /** Same ladder rule as the listening catalog - see ListeningTest. */
    public function test_the_catalog_climbs_by_level(): void
    {
        $levels = array_map(
            fn (array $s) => Themes::levelIndex($s['level']),
            $this->getJson('/api/transforms')->assertOk()->json('sets')
        );

        $sorted = $levels;
        sort($sorted);
        $this->assertSame($sorted, $levels, 'the transforms catalog is not ordered by level');
    }

    public function test_a_set_ships_its_items(): void
    {
        $set = $this->getJson('/api/transforms/kielto')->assertOk()->json('set');

        $this->assertNotEmpty($set['items']);

        foreach ($set['items'] as $item) {
            foreach (['prompt', 'from', 'from_en', 'to', 'to_en', 'note'] as $key) {
                $this->assertArrayHasKey($key, $item);
                $this->assertNotSame('', trim($item[$key]));
            }
        }
    }

    /**
     * Every item must actually change the sentence, and the drill's own
     * answer must never be listed as an alternate - a set where `to` equals
     * `from` would train nothing while looking correct.
     */
    public function test_every_item_is_a_real_transformation(): void
    {
        foreach (Transforms::all() as $set) {
            foreach ($set['items'] as $i => $item) {
                $this->assertNotSame(
                    mb_strtolower($item['from']),
                    mb_strtolower($item['to']),
                    "Set {$set['id']} item {$i}: 'to' is identical to 'from'.",
                );

                foreach ($item['accepts'] ?? [] as $alt) {
                    $this->assertNotSame(
                        mb_strtolower(trim($alt)),
                        mb_strtolower(trim($item['from'])),
                        "Set {$set['id']} item {$i}: the untransformed sentence is listed as a correct answer.",
                    );
                }
            }
        }
    }

    public function test_unknown_set_is_not_found(): void
    {
        $this->getJson('/api/transforms/kuuhun-lento')->assertNotFound();
        $this->postJson('/api/transforms/kuuhun-lento/complete')->assertNotFound();
    }

    public function test_clearing_a_set_awards_xp_once(): void
    {
        $this->postJson('/api/transforms/kielto/complete')
            ->assertOk()
            ->assertJsonPath('xp_gained', 25)
            ->assertJsonPath('xp', 25);

        // Re-running a set keeps the ✓ but never farms XP.
        $this->postJson('/api/transforms/kielto/complete')
            ->assertOk()
            ->assertJsonPath('xp_gained', 0);

        $this->assertSame(25, $this->user->fresh()->xp);
        $this->assertArrayHasKey('kielto', $this->user->fresh()->transforms_done);
    }

    public function test_completion_shows_up_in_the_catalog(): void
    {
        $this->postJson('/api/transforms/kielto/complete')->assertOk();

        $sets = collect($this->getJson('/api/transforms')->json('sets'))->keyBy('id');

        $this->assertTrue($sets['kielto']['done']);
        $this->assertFalse($sets['kysymys']['done']);
    }

    public function test_transforms_stay_free_when_billing_is_enabled(): void
    {
        config(['services.stripe.secret' => 'sk_test_x']);

        $this->getJson('/api/transforms')->assertOk();
        $this->getJson('/api/transforms/kielto')->assertOk();
        $this->postJson('/api/transforms/kielto/complete')->assertOk();
    }
}
