<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserWord;
use App\Services\Llm;
use App\Support\Scenarios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScenarioChatTest extends TestCase
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

    public function test_scenario_catalog_lists_public_fields_only(): void
    {
        $res = $this->getJson('/api/scenarios')->assertOk();

        $scenarios = $res->json('scenarios');
        $this->assertCount(count(Scenarios::ids()), $scenarios);

        $first = $scenarios[0];
        $this->assertArrayHasKey('mission', $first);
        $this->assertArrayHasKey('opener', $first);
        $this->assertArrayHasKey('xp', $first);
        // Prompt internals must not leak to the client.
        $this->assertArrayNotHasKey('scene', $first);
        $this->assertArrayNotHasKey('goal_check', $first);
    }

    public function test_scenarios_matching_the_intake_goal_come_first(): void
    {
        $this->user->update(['preferences' => ['goal' => 'move']]);

        $scenarios = $this->getJson('/api/scenarios')->json('scenarios');

        // All recommended entries precede all non-recommended ones.
        $flags = array_column($scenarios, 'recommended');
        $lastRecommended = array_search(true, array_reverse($flags, true), true);
        $firstOther = array_search(false, $flags, true);

        $this->assertTrue($flags[0]);
        $this->assertGreaterThan($lastRecommended, $firstOther);
    }

    public function test_scenario_chat_replies_via_mock_and_reaches_the_goal(): void
    {
        config(['services.ai.key' => null, 'services.ai.gemini_key' => null, 'services.ai.openrouter_key' => null]);

        // Third exchange: 5 messages deep (opener + 2 rounds), mock completes.
        $messages = [
            ['role' => 'user', 'content' => 'Moi! Missä on maito?'],
            ['role' => 'assistant', 'content' => 'Joo, onnistuu! Se maksaa kolme euroa.'],
            ['role' => 'user', 'content' => 'Otan maidon, kiitos.'],
            ['role' => 'assistant', 'content' => 'Kiitos! Tässä, ole hyvä. Tarviitko kuitin?'],
            ['role' => 'user', 'content' => 'Ei kiitos, moikka!'],
        ];

        $this->postJson('/api/chat', ['messages' => $messages, 'scenario' => 'kauppa'])
            ->assertOk()
            ->assertJsonPath('source', 'mock')
            ->assertJsonPath('goal_reached', true);
    }

    public function test_completing_a_scenario_awards_xp_once(): void
    {
        $this->postJson('/api/scenarios/kauppa/complete')
            ->assertOk()
            ->assertJsonPath('xp_gained', Scenarios::XP['easy'])
            ->assertJsonPath('xp', Scenarios::XP['easy']);

        // Replays keep the ✓ but never farm XP.
        $this->postJson('/api/scenarios/kauppa/complete')
            ->assertOk()
            ->assertJsonPath('xp_gained', 0);

        $this->assertSame(Scenarios::XP['easy'], $this->user->fresh()->xp);
    }

    public function test_harder_scenarios_pay_more_xp(): void
    {
        // puhelin is the hard one (phone call, no visual context).
        $this->postJson('/api/scenarios/puhelin/complete')
            ->assertOk()
            ->assertJsonPath('xp_gained', Scenarios::XP['hard']);
    }

    public function test_completion_is_premium_gated_when_billing_is_enabled(): void
    {
        config(['services.stripe.secret' => 'sk_test_x']);

        // Free users can browse the catalog but not bank completions/XP.
        $this->getJson('/api/scenarios')->assertOk();
        $this->postJson('/api/scenarios/kauppa/complete')
            ->assertStatus(402)
            ->assertJsonPath('code', 'premium_required');
    }

    public function test_unknown_scenario_is_rejected(): void
    {
        $this->postJson('/api/chat', [
            'messages' => [['role' => 'user', 'content' => 'Moi!']],
            'scenario' => 'kuuhun-lento',
        ])->assertStatus(422);
    }

    public function test_free_chat_response_has_no_goal_field(): void
    {
        config(['services.ai.key' => null, 'services.ai.gemini_key' => null, 'services.ai.openrouter_key' => null]);

        $this->postJson('/api/chat', [
            'messages' => [['role' => 'user', 'content' => 'Moi Väinö!']],
        ])
            ->assertOk()
            ->assertJsonMissingPath('goal_reached');
    }

    public function test_prompt_is_personalized_with_name_goal_and_weak_words(): void
    {
        $this->user->update(['preferences' => ['goal' => 'move']]);
        UserWord::create([
            'user_id' => $this->user->id,
            'word' => 'mustikka',
            'gloss' => 'blueberry',
            'status' => UserWord::STATUS_LEARNING,
            'next_review_at' => now()->subDay(),
        ]);

        config(['services.ai.key' => 'sk-test', 'services.ai.model' => 'claude-test']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['text' => '{"reply":"Moi Testi!","translation":"Hi Testi!","correction":null}']],
        ])]);

        $this->postJson('/api/chat', [
            'messages' => [['role' => 'user', 'content' => 'Moi!']],
        ])->assertOk()->assertJsonPath('source', 'ai');

        Http::assertSent(function ($request) {
            $system = $request->data()['system'] ?? '';

            return str_contains($system, 'Testi')       // first name only
                && ! str_contains($system, 'Oppija')
                && str_contains($system, 'moving to Finland')
                && str_contains($system, 'mustikka');
        });

        // Keep the static analyzer honest: Llm state untouched on success.
        $this->assertNull(Llm::$lastStatus);
    }

    public function test_scenario_prompt_casts_the_persona(): void
    {
        config(['services.ai.key' => 'sk-test']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['text' => '{"reply":"Moi!","translation":"Hi!","correction":null,"goal_reached":false}']],
        ])]);

        $this->postJson('/api/chat', [
            'messages' => [['role' => 'user', 'content' => 'Moi! Missä on maito?']],
            'scenario' => 'kauppa',
        ])->assertOk()->assertJsonPath('goal_reached', false);

        Http::assertSent(function ($request) {
            $system = $request->data()['system'] ?? '';

            return str_contains($system, 'Marja')
                && str_contains($system, 'MISSION')
                && str_contains($system, 'goal_reached');
        });
    }
}
