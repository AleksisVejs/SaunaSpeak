<?php

namespace Tests\Feature;

use App\Models\Sentence;
use App\Models\User;
use App\Services\ElevenLabs;
use App\Support\Listening;
use App\Support\Transforms;
use Database\Seeders\JsonLessonSeeder;
use Database\Seeders\LessonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\StashesElevenAudio;
use Tests\TestCase;

/**
 * ElevenLabs is a MIXABLE tier, not a swap: credits are finite, so the normal
 * state is some clips voiced here and the rest still on edge-tts. What must
 * hold is the priority - human > eleven > edge-tts - and that a half-finished
 * run never leaves anything silent.
 */
class ElevenLabsAudioTest extends TestCase
{
    use RefreshDatabase;
    use StashesElevenAudio;

    /**
     * These tests run `audio:generate` for real, and that command rewrites the
     * tracked public/audio/words.json from whatever is in the database. A test
     * DB holds a fraction of the course, so without this the suite would
     * quietly shrink the real manifest from ~1000 words to a couple of hundred
     * and commit it.
     */
    private ?string $manifestBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.elevenlabs.key' => 'sk-test',
            'services.elevenlabs.voice_male' => 'voice-male-id',
            'services.elevenlabs.voice_female' => 'voice-female-id',
        ]);

        $path = public_path('audio/words.json');
        $this->manifestBackup = File::exists($path) ? File::get($path) : null;

        $this->stashElevenClips();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        $path = public_path('audio/words.json');
        if ($this->manifestBackup !== null) {
            File::put($path, $this->manifestBackup);
        } elseif (File::exists($path)) {
            File::delete($path);
        }

        $this->restoreElevenClips();

        parent::tearDown();
    }

    /** The suite's own leftover human takes; the eleven dir is handled by the trait. */
    private function cleanUp(): void
    {
        foreach (File::glob(public_path('audio/human/sentence-*.*')) as $path) {
            File::delete($path);
        }
    }

    /** A fake MP3 body (ElevenLabs returns raw audio/mpeg bytes). */
    private function fakeAudio(): string
    {
        return "\xFF\xF3\x64\xC4".str_repeat('x', 64);
    }

    private function fakeApi(): void
    {
        Http::fake([
            'api.elevenlabs.io/v1/user/subscription' => Http::response([
                'character_count' => 1000,
                'character_limit' => 100000,
            ]),
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response($this->fakeAudio()),
        ]);
    }

    public function test_it_is_off_until_a_key_is_set(): void
    {
        config(['services.elevenlabs.key' => null]);
        $this->assertFalse(ElevenLabs::available());

        $this->artisan('audio:eleven')
            ->expectsOutputToContain('No ELEVENLABS_API_KEY set')
            ->assertFailed();
    }

    /**
     * No default voices on purpose: edge-tts speaks native Finnish, so a stock
     * English voice reading Finnish would be a downgrade shipped by accident.
     */
    public function test_voices_have_no_defaults(): void
    {
        config(['services.elevenlabs.voice_male' => null, 'services.elevenlabs.voice_female' => null]);

        $this->assertNull(ElevenLabs::voiceId('male'));
        $this->assertNull(ElevenLabs::voiceId('female'));
    }

    public function test_dry_run_reports_the_cost_and_spends_nothing(): void
    {
        $this->seed(LessonSeeder::class);
        $this->fakeApi();

        $this->artisan('audio:eleven --only=sentences --dry-run')
            ->expectsOutputToContain('characters')
            ->assertSuccessful();

        // Reading the quota is free; synthesizing is what costs characters.
        // A dry run may do the first and must never do the second.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'text-to-speech'));
        $this->assertEmpty(File::glob(public_path('audio/eleven/*.mp3')));
    }

    public function test_limit_stops_the_run_early_and_the_rest_stays_on_edge_tts(): void
    {
        $this->seed(LessonSeeder::class);
        $this->fakeApi();

        $this->artisan('audio:eleven --only=sentences --limit=3')->assertSuccessful();

        // Exactly three voiced...
        $this->assertCount(3, File::glob(public_path('audio/eleven/sentence-*.mp3')));

        // ...and everything else still points at its edge-tts clip, not silence.
        $unvoiced = Sentence::where('audio_url', 'not like', '/audio/eleven/%')->count();
        $this->assertGreaterThan(0, $unvoiced);
        $this->assertSame(0, Sentence::whereNull('audio_url')->count());
    }

    public function test_a_voiced_sentence_is_linked_and_beats_edge_tts(): void
    {
        $this->seed(LessonSeeder::class);
        $this->fakeApi();

        $this->artisan('audio:eleven --only=sentences --limit=1')->assertSuccessful();

        $voiced = Sentence::where('audio_url', 'like', '/audio/eleven/%')->first();
        $this->assertNotNull($voiced, 'The generated clip should be the one that plays.');
    }

    public function test_a_human_recording_still_beats_elevenlabs(): void
    {
        $this->seed(LessonSeeder::class);
        $this->fakeApi();

        $this->artisan('audio:eleven --only=sentences --limit=1')->assertSuccessful();
        $sentence = Sentence::where('audio_url', 'like', '/audio/eleven/%')->firstOrFail();

        // A native take lands for the same sentence.
        File::ensureDirectoryExists(public_path('audio/human'));
        File::put(public_path("audio/human/sentence-{$sentence->id}.mp3"), $this->fakeAudio());

        $this->artisan('audio:generate')->run();

        $this->assertStringStartsWith('/audio/human/', $sentence->fresh()->audio_url);
    }

    /**
     * audio:generate runs on every deploy. If it didn't know about the eleven
     * tier it would quietly relink every premium clip back to the robot.
     */
    public function test_deploy_relink_does_not_demote_elevenlabs_back_to_edge_tts(): void
    {
        $this->seed(LessonSeeder::class);
        $this->fakeApi();

        $this->artisan('audio:eleven --only=sentences --limit=2')->assertSuccessful();
        $before = Sentence::where('audio_url', 'like', '/audio/eleven/%')->pluck('audio_url', 'id');
        $this->assertNotEmpty($before);

        $this->artisan('audio:generate')->run();

        foreach ($before as $id => $url) {
            $this->assertSame($url, Sentence::find($id)->audio_url, 'Deploy demoted an ElevenLabs clip.');
        }
    }

    public function test_listening_lines_prefer_elevenlabs_over_edge_tts(): void
    {
        $tts = Listening::audioUrl('kahvilassa', 0);
        $this->assertStringStartsWith('/audio/listening/', (string) $tts);

        File::ensureDirectoryExists(public_path('audio/eleven'));
        File::put(public_path('audio/eleven/'.Listening::baseName('kahvilassa', 0).'.mp3'), $this->fakeAudio());

        $this->assertStringStartsWith('/audio/eleven/', (string) Listening::audioUrl('kahvilassa', 0));
    }

    public function test_transform_phrases_prefer_elevenlabs_over_edge_tts(): void
    {
        $this->seed(LessonSeeder::class);
        $this->seed(JsonLessonSeeder::class);

        $phrase = Transforms::ownPhrases()[0];
        $this->assertStringStartsWith('/audio/transforms/', (string) $phrase['audio_url']);

        File::ensureDirectoryExists(public_path('audio/eleven'));
        File::put(public_path("audio/eleven/{$phrase['base']}.mp3"), $this->fakeAudio());

        $after = collect(Transforms::ownPhrases())->firstWhere('base', $phrase['base']);
        $this->assertStringStartsWith('/audio/eleven/', (string) $after['audio_url']);
    }

    public function test_running_out_of_credits_stops_cleanly(): void
    {
        $this->seed(LessonSeeder::class);

        Http::fake([
            // Almost nothing left: enough for a clip or two, not the course.
            'api.elevenlabs.io/v1/user/subscription' => Http::response([
                'character_count' => 99980,
                'character_limit' => 100000,
            ]),
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response($this->fakeAudio()),
        ]);

        $this->artisan('audio:eleven --only=sentences')
            ->expectsOutputToContain('Not enough credits')
            ->assertSuccessful();

        // Whatever fit got voiced; nothing was left silent.
        $this->assertSame(0, Sentence::whereNull('audio_url')->count());
    }

    public function test_a_bad_key_stops_early_instead_of_hammering_the_api(): void
    {
        $this->seed(LessonSeeder::class);

        Http::fake([
            'api.elevenlabs.io/v1/user/subscription' => Http::response(['detail' => 'unauthorized'], 401),
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(['detail' => 'unauthorized'], 401),
        ]);

        $this->artisan('audio:eleven --only=sentences')->assertFailed();

        // Three strikes, not 552.
        Http::assertSentCount(4); // 1 quota probe + 3 attempts
    }

    public function test_chat_tts_ignores_elevenlabs_unless_explicitly_enabled(): void
    {
        // Chat is unbounded spend - it must not quietly drain the budget.
        $this->assertFalse((bool) config('services.elevenlabs.for_chat'));
    }

    public function test_unknown_only_value_is_rejected(): void
    {
        $this->artisan('audio:eleven --only=nonsense')
            ->expectsOutputToContain('Unknown --only value')
            ->assertFailed();
    }

    public function test_studio_hides_elevenlabs_clips_by_default_but_counts_them(): void
    {
        $this->seed(LessonSeeder::class);
        $this->fakeApi();
        $this->artisan('audio:eleven --only=sentences --limit=2')->assertSuccessful();

        $recorder = User::forceCreate([
            'name' => 'Voice', 'email' => 'voice@example.com',
            'password' => bcrypt('password'), 'is_recorder' => true,
        ]);
        Sanctum::actingAs($recorder);

        // Default queue (robot-first): the 2 ElevenLabs sentences are hidden...
        $queue = $this->getJson('/api/record/queue')->assertOk()->json();
        foreach ($queue['sentences'] as $s) {
            $this->assertSame('tts', $s['tier'], 'A robot-first queue must not offer an ElevenLabs clip.');
        }
        // ...but still counted, so the studio can say "2 already on ElevenLabs".
        $this->assertSame(2, $queue['sentence_eleven']);
    }

    public function test_turning_the_filter_off_shows_the_elevenlabs_clips(): void
    {
        $this->seed(LessonSeeder::class);
        $this->fakeApi();
        $this->artisan('audio:eleven --only=sentences --limit=2')->assertSuccessful();

        $recorder = User::forceCreate([
            'name' => 'Voice', 'email' => 'voice@example.com',
            'password' => bcrypt('password'), 'is_recorder' => true,
        ]);
        Sanctum::actingAs($recorder);

        $all = $this->getJson('/api/record/queue?robot_first=0')->assertOk()->json();
        $tiers = array_column($all['sentences'], 'tier');

        $this->assertContains('eleven', $tiers, 'With the filter off, ElevenLabs clips must be offered.');
        $this->assertContains('tts', $tiers);
    }
}
