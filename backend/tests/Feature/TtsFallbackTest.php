<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TtsFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('password'),
        ]));

        // Force the edge-tts branch to fail so the fallback chain is exercised.
        config(['services.tts.bin' => 'definitely-not-a-real-binary']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('audio/tts-test-cleanup'), true);
        parent::tearDown();
    }

    public function test_falls_back_to_google_tts_when_edge_tts_is_unavailable(): void
    {
        config(['services.tts.google_key' => 'test-key']);

        Http::fake([
            'texttospeech.googleapis.com/*' => Http::response([
                'audioContent' => base64_encode('fake-mp3-bytes'),
            ]),
        ]);

        $res = $this->postJson('/api/tts', ['text' => 'Terve maailma!'])->assertOk();

        $url = $res->json('url');
        $this->assertNotNull($url);
        $this->assertFileExists(public_path(ltrim($url, '/')));
        $this->assertSame('fake-mp3-bytes', file_get_contents(public_path(ltrim($url, '/'))));

        // Clean up the cached fake clip.
        File::delete(public_path(ltrim($url, '/')));
    }

    public function test_returns_503_when_no_provider_is_available(): void
    {
        config(['services.tts.google_key' => null]);

        $this->postJson('/api/tts', ['text' => 'Terve!'])
            ->assertStatus(503)
            ->assertJsonPath('url', null);
    }
}
