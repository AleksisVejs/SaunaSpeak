<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MinimalPairs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\StashesElevenAudio;
use Tests\TestCase;

/**
 * The recording studio's Kuulo tab. The behaviour under test is the
 * separation: drill words have their own queue, their own pending/human
 * directories and their own review type - they never ride the course word
 * manifest, whose entries are rebuilt from sentence glosses and use a
 * different hash scheme.
 */
class PairRecordTest extends TestCase
{
    use RefreshDatabase;
    use StashesElevenAudio;

    private User $recorder;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorder = User::forceCreate([
            'name' => 'Native Speaker', 'email' => 'voice@example.com',
            'password' => bcrypt('password'), 'is_recorder' => true,
        ]);
        $this->admin = User::forceCreate([
            'name' => 'Boss', 'email' => 'boss@example.com',
            'password' => bcrypt('password'), 'is_admin' => true,
        ]);

        // The committed ElevenLabs pair clips would otherwise put drill words
        // on the 'eleven' tier and hide them behind the robot-first filter.
        $this->stashElevenClips();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        $this->restoreElevenClips();
        parent::tearDown();
    }

    /** Takes are real files - never let one test's uploads leak into another. */
    private function cleanUp(): void
    {
        foreach (['audio/pending/pairs/*.*', 'audio/human/pairs/*.*'] as $glob) {
            foreach (File::glob(public_path($glob)) as $path) {
                File::delete($path);
            }
        }
    }

    private function take(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('take.wav', 'RIFF....WAVEfake');
    }

    public function test_queue_lists_pairs_separately_from_course_words(): void
    {
        Sanctum::actingAs($this->recorder);

        $data = $this->getJson('/api/record/queue')->assertOk()->json();

        $this->assertGreaterThan(0, $data['pair_total']);
        $this->assertSame(0, $data['pair_done']);
        $this->assertSame(0, $data['pair_pending']);

        // Every queued entry is a drill word, and none of them leaked into
        // the course-word queue under the word manifest's hash scheme.
        $drillWords = MinimalPairs::words();
        foreach ($data['pairs'] as $pair) {
            $this->assertArrayHasKey(mb_strtolower($pair['word']), $drillWords);
        }
        $queuedWords = array_column($data['words'], 'word');
        $this->assertNotContains('syy', $queuedWords);
    }

    public function test_take_goes_to_pending_then_live_on_approval(): void
    {
        Sanctum::actingAs($this->recorder);

        $this->postJson('/api/record/pair', ['word' => 'syy', 'audio' => $this->take()])
            ->assertOk()
            ->assertJsonPath('word', 'syy');

        // Pending: out of the queue, counted, but the drill still plays TTS.
        $data = $this->getJson('/api/record/queue')->assertOk()->json();
        $this->assertSame(1, $data['pair_pending']);
        $this->assertNotContains('syy', array_column($data['pairs'], 'word'));
        $this->assertStringStartsNotWith('/audio/human/', (string) MinimalPairs::wordClips()['syy']);

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/admin/recordings')
            ->assertOk()
            ->assertJsonPath('pairs.0.word', 'syy');

        $this->postJson('/api/admin/recordings/approve', ['type' => 'pair', 'key' => 'syy'])->assertOk();

        // Approved: the drill resolves the human clip for both halves' lookups.
        $this->assertStringStartsWith('/audio/human/pairs/', (string) MinimalPairs::wordClips()['syy']);
    }

    public function test_rejecting_a_take_returns_the_word_to_the_queue(): void
    {
        Sanctum::actingAs($this->recorder);
        $this->postJson('/api/record/pair', ['word' => 'syy', 'audio' => $this->take()])->assertOk();

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/recordings/reject', ['type' => 'pair', 'key' => 'syy'])->assertOk();

        Sanctum::actingAs($this->recorder);
        $data = $this->getJson('/api/record/queue')->assertOk()->json();

        $this->assertSame(0, $data['pair_pending']);
        $this->assertContains('syy', array_column($data['pairs'], 'word'));
    }

    public function test_admin_can_revert_a_live_pair_take(): void
    {
        Sanctum::actingAs($this->recorder);
        $this->postJson('/api/record/pair', ['word' => 'syy', 'audio' => $this->take()])->assertOk();

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/recordings/approve', ['type' => 'pair', 'key' => 'syy'])->assertOk();
        $this->assertStringStartsWith('/audio/human/pairs/', (string) MinimalPairs::wordClips()['syy']);

        $this->deleteJson('/api/record/pair?word=syy')->assertOk();

        // Back to the best synthetic clip - never a human file again.
        $this->assertStringStartsNotWith('/audio/human/', (string) MinimalPairs::wordClips()['syy']);
    }

    public function test_pair_takes_do_not_collide_with_word_takes(): void
    {
        Sanctum::actingAs($this->recorder);
        $this->postJson('/api/record/pair', ['word' => 'syy', 'audio' => $this->take()])->assertOk();

        Sanctum::actingAs($this->admin);
        $data = $this->getJson('/api/admin/recordings')->assertOk()->json();

        $this->assertCount(1, $data['pairs']);
        $this->assertEmpty($data['words']);
        $this->assertEmpty($data['sentences']);
    }

    public function test_words_outside_the_drills_are_rejected(): void
    {
        Sanctum::actingAs($this->recorder);

        $this->postJson('/api/record/pair', ['word' => 'kahvi', 'audio' => $this->take()])
            ->assertStatus(422);
    }

    public function test_pair_recording_needs_the_right_rights(): void
    {
        $member = User::create([
            'name' => 'Member', 'email' => 'member@example.com', 'password' => bcrypt('password'),
        ]);
        Sanctum::actingAs($member);
        $this->postJson('/api/record/pair', ['word' => 'syy', 'audio' => $this->take()])->assertStatus(403);

        // Reverting a live take is an admin action, not a recorder one.
        Sanctum::actingAs($this->recorder);
        $this->deleteJson('/api/record/pair?word=syy')->assertStatus(403);
    }
}
