<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Sentence;
use App\Models\User;
use App\Support\MinimalPairs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\StashesElevenAudio;
use Tests\TestCase;

/**
 * Culling a bad ElevenLabs clip from the admin panel. The tier is mixable
 * (human > eleven > edge-tts), so deleting one clip must drop that item
 * exactly one rung - never leave it silent, and never disturb the human take
 * or the edge-tts clip underneath.
 */
class ElevenCullTest extends TestCase
{
    use RefreshDatabase;
    use StashesElevenAudio;

    private User $admin;

    /**
     * Culling a word rewrites the tracked public/audio/words.json. The test DB
     * holds a couple of words where the real manifest holds ~1000, so without
     * this backup the suite would quietly shrink the committed manifest.
     */
    private ?string $manifestBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'name' => 'Boss', 'email' => 'boss@example.com',
            'password' => bcrypt('password'), 'is_admin' => true,
        ]);

        $path = public_path('audio/words.json');
        $this->manifestBackup = File::exists($path) ? File::get($path) : null;

        // The real eleven clips move aside for the duration; everything this
        // test writes under audio/eleven/ goes with them on the way out.
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

    /** Only this test's own human takes; the eleven dir is the trait's job. */
    private function cleanUp(): void
    {
        foreach (File::glob(public_path('audio/human/sentence-*.*')) as $path) {
            File::delete($path);
        }
    }

    private function clip(string $relative): string
    {
        $path = public_path($relative);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'ID3fake-mp3');

        return $path;
    }

    /** A sentence whose edge-tts clip is one of the 552 committed ones. */
    private function sentence(): Sentence
    {
        $lesson = Lesson::create(['title' => 'Testi', 'level' => 'A0', 'order_index' => 1]);

        return Sentence::create([
            'lesson_id' => $lesson->id,
            'finnish_text' => 'Onks tää bussi?',
            'english_text' => 'Is this the bus?',
        ]);
    }

    public function test_culling_a_sentence_clip_drops_it_back_to_edge_tts(): void
    {
        $sentence = $this->sentence();
        $eleven = $this->clip("audio/eleven/sentence-{$sentence->id}.mp3");
        $sentence->update(['audio_url' => "/audio/eleven/sentence-{$sentence->id}.mp3"]);

        Sanctum::actingAs($this->admin);
        $this->deleteJson('/api/admin/eleven', ['type' => 'sentence', 'key' => (string) $sentence->id])
            ->assertOk()
            ->assertJson(['audio_url' => "/audio/sentence-{$sentence->id}.mp3"]);

        $this->assertFileDoesNotExist($eleven);
        // Still voiced, one tier down - the whole point of culling.
        $this->assertSame("/audio/sentence-{$sentence->id}.mp3", $sentence->fresh()->audio_url);
        $this->assertFileExists(public_path("audio/sentence-{$sentence->id}.mp3"));
    }

    public function test_a_human_take_outranks_the_edge_tts_clip_on_the_way_down(): void
    {
        $sentence = $this->sentence();
        $this->clip("audio/eleven/sentence-{$sentence->id}.mp3");
        $this->clip("audio/human/sentence-{$sentence->id}.mp3");

        Sanctum::actingAs($this->admin);
        $this->deleteJson('/api/admin/eleven', ['type' => 'sentence', 'key' => (string) $sentence->id])
            ->assertOk();

        $this->assertSame("/audio/human/sentence-{$sentence->id}.mp3", $sentence->fresh()->audio_url);
    }

    public function test_culling_a_word_repoints_the_manifest(): void
    {
        $base = 'kahvia-'.substr(md5('kahvia'), 0, 6);
        $this->clip("audio/eleven/words/{$base}.mp3");
        $this->clip("audio/words/{$base}.mp3");
        File::put(public_path('audio/words.json'), json_encode(['kahvia' => "/audio/eleven/words/{$base}.mp3"]));

        Sanctum::actingAs($this->admin);
        $this->deleteJson('/api/admin/eleven', ['type' => 'word', 'key' => 'kahvia'])
            ->assertOk()
            ->assertJson(['audio_url' => "/audio/words/{$base}.mp3"]);

        $this->assertFileDoesNotExist(public_path("audio/eleven/words/{$base}.mp3"));
        $manifest = json_decode(File::get(public_path('audio/words.json')), true);
        $this->assertSame("/audio/words/{$base}.mp3", $manifest['kahvia']);

        File::delete(public_path("audio/words/{$base}.mp3"));
    }

    /**
     * Pairs resolve their URL by globbing the layers on every request, so for
     * them deleting the file IS the relink - nothing stored to update.
     */
    public function test_culling_a_pair_clip_falls_back_without_a_stored_url(): void
    {
        $word = MinimalPairs::words()[array_key_first(MinimalPairs::words())];
        $base = MinimalPairs::wordBase($word);
        $this->clip("audio/eleven/pairs/{$base}.mp3");

        $this->assertStringStartsWith(
            '/audio/eleven/',
            MinimalPairs::wordClips()[array_key_first(MinimalPairs::words())],
            'the fake clip should be the winning tier before the cull',
        );

        Sanctum::actingAs($this->admin);
        $this->deleteJson('/api/admin/eleven', ['type' => 'pair', 'key' => $word])->assertOk();

        $this->assertFileDoesNotExist(public_path("audio/eleven/pairs/{$base}.mp3"));
        $this->assertStringStartsNotWith(
            '/audio/eleven/',
            (string) MinimalPairs::wordClips()[array_key_first(MinimalPairs::words())],
        );
    }

    public function test_the_recordings_payload_lists_what_eleven_voiced(): void
    {
        $sentence = $this->sentence();
        $this->clip("audio/eleven/sentence-{$sentence->id}.mp3");
        $sentence->update(['audio_url' => "/audio/eleven/sentence-{$sentence->id}.mp3"]);

        Sanctum::actingAs($this->admin);
        $data = $this->getJson('/api/admin/recordings')->assertOk()->json();

        $this->assertSame([$sentence->id], array_column($data['eleven_sentences'], 'id'));
        // ...and it's not confused with a human take.
        $this->assertSame([], $data['live_sentences']);
    }

    public function test_a_non_admin_cannot_cull_anything(): void
    {
        $sentence = $this->sentence();
        $eleven = $this->clip("audio/eleven/sentence-{$sentence->id}.mp3");

        $plain = User::forceCreate([
            'name' => 'Learner', 'email' => 'learner@example.com', 'password' => bcrypt('password'),
        ]);

        Sanctum::actingAs($plain);
        $this->deleteJson('/api/admin/eleven', ['type' => 'sentence', 'key' => (string) $sentence->id])
            ->assertForbidden();

        $this->assertFileExists($eleven);
    }

    /**
     * Every key reaches a filesystem path, so one that names nothing real has
     * to be refused rather than globbed.
     */
    public function test_an_unknown_key_is_refused(): void
    {
        Sanctum::actingAs($this->admin);

        $this->deleteJson('/api/admin/eleven', ['type' => 'sentence', 'key' => '999999'])->assertNotFound();
        $this->deleteJson('/api/admin/eleven', ['type' => 'word', 'key' => 'ei-sanaa'])->assertStatus(422);
        $this->deleteJson('/api/admin/eleven', ['type' => 'pair', 'key' => 'ei-sanaa'])->assertNotFound();
        $this->deleteJson('/api/admin/eleven', ['type' => 'sauna', 'key' => '1'])->assertStatus(422);
    }
}
