<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PairDrillTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $user = User::factory()->create(['xp' => 0]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_catalog_lists_sets_with_their_contrast(): void
    {
        $this->actor();

        $res = $this->getJson('/api/pairs')->assertOk();

        $this->assertNotEmpty($res->json('sets'));
        $this->assertCount(2, $res->json('sets.0.contrast'));
        $this->assertFalse($res->json('sets.0.done'));
    }

    public function test_a_set_serves_both_words_of_every_pair(): void
    {
        $this->actor();

        $id = $this->getJson('/api/pairs')->json('sets.0.id');
        $pairs = $this->getJson("/api/pairs/{$id}")->assertOk()->json('set.pairs');

        $this->assertNotEmpty($pairs);
        foreach ($pairs as $pair) {
            $this->assertNotSame('', $pair['a']);
            $this->assertNotSame('', $pair['b']);
            $this->assertArrayHasKey('a_audio', $pair);
            $this->assertArrayHasKey('b_audio', $pair);
        }
    }

    public function test_unknown_set_is_404(): void
    {
        $this->actor();

        $this->getJson('/api/pairs/not-a-set')->assertNotFound();
        $this->postJson('/api/pairs/not-a-set/complete')->assertNotFound();
    }

    public function test_completing_pays_xp_once(): void
    {
        $user = $this->actor();
        $id = $this->getJson('/api/pairs')->json('sets.0.id');

        $first = $this->postJson("/api/pairs/{$id}/complete")->assertOk();
        $this->assertSame(20, $first->json('xp_gained'));

        $second = $this->postJson("/api/pairs/{$id}/complete")->assertOk();
        $this->assertSame(0, $second->json('xp_gained'));

        $this->assertSame(20, $user->fresh()->xp);
        $this->assertArrayHasKey($id, $user->fresh()->pairs_done);
    }

    public function test_done_flag_follows_the_user(): void
    {
        $this->actor();
        $id = $this->getJson('/api/pairs')->json('sets.0.id');
        $this->postJson("/api/pairs/{$id}/complete");

        $sets = collect($this->getJson('/api/pairs')->json('sets'));

        $this->assertTrue($sets->firstWhere('id', $id)['done']);
    }

    public function test_the_drill_needs_a_login(): void
    {
        $this->getJson('/api/pairs')->assertUnauthorized();
    }
}
