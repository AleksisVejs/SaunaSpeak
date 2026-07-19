<?php

namespace Tests\Feature;

use App\Models\Sentence;
use App\Models\User;
use App\Support\Transforms;
use Database\Seeders\JsonLessonSeeder;
use Database\Seeders\LessonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\StashesElevenAudio;
use Tests\TestCase;

/**
 * Taivutus audio. The property under test is "say it once": audio is keyed by
 * the sentence's TEXT, so the same sentence in two drills shares one clip, and
 * a sentence the course already teaches reuses the course's recording.
 */
class TransformAudioTest extends TestCase
{
    use RefreshDatabase;
    use StashesElevenAudio;

    private User $recorder;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // The drills quote real course sentences ("Mä otan kahvin."), which
        // live in database/lessons/*.json - so the reuse path only exists
        // against a seeded course.
        $this->seed(LessonSeeder::class);
        $this->seed(JsonLessonSeeder::class);

        $this->recorder = User::forceCreate([
            'name' => 'Native Speaker', 'email' => 'voice@example.com',
            'password' => bcrypt('password'), 'is_recorder' => true,
        ]);
        $this->admin = User::forceCreate([
            'name' => 'Boss', 'email' => 'boss@example.com',
            'password' => bcrypt('password'), 'is_admin' => true,
        ]);

        // Assertions here expect the edge-tts tier (/audio/transforms/); the
        // real ElevenLabs clips on disk would otherwise win and break them.
        $this->stashElevenClips();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        $this->restoreElevenClips();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        foreach (['audio/pending/phrase-*.*', 'audio/human/phrase-*.*'] as $glob) {
            foreach (File::glob(public_path($glob)) as $path) {
                File::delete($path);
            }
        }
    }

    private function take(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('take.wav', 'RIFF....WAVEfake');
    }

    /** Link the committed MP3s the way `audio:generate` does on deploy. */
    private function linkCourseAudio(): void
    {
        Sentence::query()->update(['audio_url' => null]);
        foreach (Sentence::get(['id']) as $s) {
            Sentence::whereKey($s->id)->update(['audio_url' => "/audio/sentence-{$s->id}.mp3"]);
        }
    }

    public function test_every_phrase_has_a_generated_clip(): void
    {
        // The real guard: a set added without running `transforms:audio` ships
        // a silent drill, and an audio-first course cannot do that.
        foreach (Transforms::ownPhrases() as $phrase) {
            $this->assertNotNull(
                $phrase['audio_url'],
                "\"{$phrase['text']}\" has no clip - run `php artisan transforms:audio`.",
            );
        }
    }

    public function test_every_drill_sentence_resolves_to_audio(): void
    {
        $this->linkCourseAudio();

        foreach (Transforms::all() as $set) {
            foreach ($set['items'] as $i => $item) {
                $this->assertNotNull($item['from_audio'], "Set {$set['id']} item {$i}: 'from' has no audio.");
                $this->assertNotNull($item['to_audio'], "Set {$set['id']} item {$i}: 'to' has no audio.");
            }
        }
    }

    /**
     * A course sentence with no audio yet must still be the owner of its text.
     * If Taivutus fell through to a phrase clip in that gap, the sentence
     * would exist twice and the studio would ask a recorder to read it twice.
     */
    public function test_a_course_sentence_owns_its_text_even_before_audio_exists(): void
    {
        Sentence::query()->update(['audio_url' => null]);

        $texts = array_map(
            fn ($p) => Transforms::normalize($p['text']),
            Transforms::ownPhrases(),
        );

        $this->assertNotContains(Transforms::normalize('Mä otan kahvin.'), $texts);
    }

    public function test_the_same_sentence_in_two_drills_shares_one_clip(): void
    {
        // "Se tulee mukaan." starts a drill in two different sets. Keyed by
        // text, that's one file - and one take for whoever records it.
        $urls = [];

        foreach (Transforms::all() as $set) {
            foreach ($set['items'] as $item) {
                foreach (['from', 'to'] as $side) {
                    if (Transforms::normalize($item[$side]) === Transforms::normalize('Se tulee mukaan.')) {
                        $urls[] = $item[$side.'_audio'];
                    }
                }
            }
        }

        $this->assertGreaterThan(1, count($urls), 'Expected this sentence in more than one drill.');
        $this->assertCount(1, array_unique($urls), 'The same sentence must resolve to one clip, not several.');
    }

    public function test_a_sentence_the_course_teaches_reuses_the_course_recording(): void
    {
        $course = Sentence::whereRaw('lower(finnish_text) = ?', ['mä otan kahvin.'])->firstOrFail();
        // Recorded once, as a course sentence.
        $course->update(['audio_url' => '/audio/human/sentence-'.$course->id.'.mp3']);

        $found = false;
        foreach (Transforms::all() as $set) {
            foreach ($set['items'] as $item) {
                if (Transforms::normalize($item['from']) === Transforms::normalize('Mä otan kahvin.')) {
                    // The human take recorded once, for the course, plays here too.
                    $this->assertSame($course->audio_url, $item['from_audio']);
                    $found = true;
                }
            }
        }

        $this->assertTrue($found, 'Expected a drill starting from "Mä otan kahvin."');
    }

    public function test_sentences_the_course_covers_are_never_queued_for_recording(): void
    {
        $this->linkCourseAudio();

        $texts = array_column(Transforms::ownPhrases(), 'text');
        $normalized = array_map(fn ($t) => Transforms::normalize($t), $texts);

        // The course says this one, so it must not be offered as its own job.
        $this->assertNotContains(Transforms::normalize('Mä otan kahvin.'), $normalized);

        // And the queue holds unique texts only - no sentence twice.
        $this->assertSame(count($normalized), count(array_unique($normalized)));
    }

    public function test_phrases_appear_in_the_regular_sentences_queue(): void
    {
        Sanctum::actingAs($this->recorder);

        $queue = $this->getJson('/api/record/queue')->assertOk()->json();
        $kinds = array_column($queue['sentences'], 'kind');

        $this->assertContains('phrase', $kinds, 'Taivutus phrases belong in the sentences queue, not a tab of their own.');
        $this->assertContains('sentence', $kinds);
    }

    public function test_the_queue_never_offers_the_same_sentence_twice(): void
    {
        $this->linkCourseAudio();
        Sanctum::actingAs($this->recorder);

        // Ask for everything (the default slice is 100).
        $seen = [];
        foreach (Transforms::ownPhrases() as $p) {
            $seen[] = Transforms::normalize($p['text']);
        }
        foreach (Sentence::get(['finnish_text']) as $s) {
            $seen[] = Transforms::normalize($s->finnish_text);
        }

        $dupes = array_keys(array_filter(array_count_values($seen), fn ($n) => $n > 1));
        $this->assertSame([], $dupes, 'These would be read aloud more than once: '.implode(' | ', $dupes));
    }

    public function test_searching_the_queue_filters_server_side(): void
    {
        Sanctum::actingAs($this->recorder);

        $hits = $this->getJson('/api/record/queue?q=kahvi')->assertOk()->json('sentences');

        $this->assertNotEmpty($hits);
        foreach ($hits as $row) {
            $this->assertTrue(
                mb_stripos($row['finnish_text'], 'kahvi') !== false
                    || mb_stripos((string) $row['english_text'], 'kahvi') !== false,
                "Search returned a non-match: {$row['finnish_text']}",
            );
        }

        // Counts describe the corpus, so the progress bar can't lie mid-search.
        $all = $this->getJson('/api/record/queue')->json();
        $searched = $this->getJson('/api/record/queue?q=kahvi')->json();
        $this->assertSame($all['sentence_total'], $searched['sentence_total']);
        $this->assertLessThan($all['sentence_total'], $searched['sentence_matches']);
    }

    public function test_word_search_filters_too(): void
    {
        Sanctum::actingAs($this->recorder);

        $words = $this->getJson('/api/record/queue?q=kahv')->assertOk()->json('words');

        foreach ($words as $w) {
            $this->assertStringContainsStringIgnoringCase('kahv', $w['word']);
        }
    }

    public function test_recording_a_phrase_goes_live_on_approval_and_reaches_every_drill(): void
    {
        $phrase = collect(Transforms::ownPhrases())->firstWhere('text', 'Se tulee mukaan.');
        $this->assertNotNull($phrase);

        Sanctum::actingAs($this->recorder);
        $this->postJson("/api/record/phrase/{$phrase['base']}", ['audio' => $this->take()])->assertOk();

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/admin/recordings')->assertOk()->assertJsonPath('phrases.0.text', 'Se tulee mukaan.');
        $this->postJson('/api/admin/recordings/approve', ['type' => 'phrase', 'key' => $phrase['base']])->assertOk();

        // One take, live in both drills that say it.
        $live = [];
        foreach (Transforms::all() as $set) {
            foreach ($set['items'] as $item) {
                if (Transforms::normalize($item['from']) === Transforms::normalize('Se tulee mukaan.')) {
                    $live[] = $item['from_audio'];
                }
            }
        }

        $this->assertGreaterThan(1, count($live));
        foreach ($live as $url) {
            $this->assertStringStartsWith('/audio/human/', $url);
        }
    }

    public function test_reverting_a_phrase_falls_back_to_tts(): void
    {
        $phrase = Transforms::ownPhrases()[0];

        Sanctum::actingAs($this->recorder);
        $this->postJson("/api/record/phrase/{$phrase['base']}", ['audio' => $this->take()])->assertOk();

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/recordings/approve', ['type' => 'phrase', 'key' => $phrase['base']])->assertOk();
        $this->deleteJson("/api/record/phrase/{$phrase['base']}")->assertOk();

        $after = collect(Transforms::ownPhrases())->firstWhere('base', $phrase['base']);
        $this->assertStringStartsWith('/audio/transforms/', (string) $after['audio_url']);
    }

    public function test_invented_phrases_are_rejected(): void
    {
        Sanctum::actingAs($this->recorder);
        $this->postJson('/api/record/phrase/phrase-deadbeef01', ['audio' => $this->take()])->assertNotFound();

        // A key is a filesystem glob - it must never escape the audio directory.
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/recordings/approve', ['type' => 'phrase', 'key' => '../../../etc/passwd'])
            ->assertNotFound();
        $this->postJson('/api/admin/recordings/reject', ['type' => 'phrase', 'key' => '../../../etc/passwd'])
            ->assertNotFound();
    }

    public function test_phrase_takes_do_not_collide_with_sentence_or_listening_takes(): void
    {
        $phrase = Transforms::ownPhrases()[0];

        Sanctum::actingAs($this->recorder);
        $this->postJson("/api/record/phrase/{$phrase['base']}", ['audio' => $this->take()])->assertOk();

        Sanctum::actingAs($this->admin);
        $data = $this->getJson('/api/admin/recordings')->assertOk()->json();

        $this->assertCount(1, $data['phrases']);
        $this->assertEmpty($data['sentences']);
        $this->assertEmpty($data['listening']);
        $this->assertEmpty($data['words']);
    }
}
