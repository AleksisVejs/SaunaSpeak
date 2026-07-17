<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Listening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\StashesElevenAudio;
use Tests\TestCase;

/**
 * The recording studio's conversation queue. The behaviour under test is the
 * speaker split: a scene is two people talking, so its lines must never be
 * offered as one flat list that a single voice can work through.
 */
class ListeningRecordTest extends TestCase
{
    use RefreshDatabase;
    use StashesElevenAudio;

    private User $recorder;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorder = User::create([
            'name' => 'Native Speaker', 'email' => 'voice@example.com',
            'password' => bcrypt('password'), 'is_recorder' => true,
        ]);
        $this->admin = User::create([
            'name' => 'Boss', 'email' => 'boss@example.com',
            'password' => bcrypt('password'), 'is_admin' => true,
        ]);

        // Revert-to-tts assertions expect /audio/listening/; the real
        // ElevenLabs clips on disk would otherwise be the fallback.
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
        foreach (['audio/pending/listening-*.*', 'audio/human/listening-*.*'] as $glob) {
            foreach (File::glob(public_path($glob)) as $path) {
                File::delete($path);
            }
        }
    }

    private function take(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('take.wav', 'RIFF....WAVEfake');
    }

    public function test_queue_groups_lines_by_scene_and_speaker(): void
    {
        Sanctum::actingAs($this->recorder);

        $scenes = $this->getJson('/api/record/listening')->assertOk()->json('scenes');
        $kahvila = collect($scenes)->firstWhere('id', 'kahvilassa');

        // Two speakers, and each one carries the identity a recorder needs.
        $this->assertCount(2, $kahvila['speakers']);
        foreach ($kahvila['speakers'] as $speaker) {
            $this->assertArrayHasKey('name', $speaker);
            $this->assertContains($speaker['voice'], ['male', 'female']);
            $this->assertNotEmpty($speaker['lines']);
            $this->assertSame(count($speaker['lines']), $speaker['total']);
        }

        // The two parts must be different voices - otherwise the recorded
        // "conversation" is one person talking to themselves.
        $voices = array_column($kahvila['speakers'], 'voice');
        $this->assertCount(2, array_unique($voices), 'A scene\'s speakers must not share one voice.');

        // Every line belongs to exactly one speaker: no line is offered twice,
        // and none goes missing.
        $grouped = array_sum(array_column($kahvila['speakers'], 'total'));
        $this->assertSame($kahvila['total'], $grouped);
        $this->assertSame(count(Listening::find('kahvilassa')['lines']), $grouped);

        $indexes = collect($kahvila['speakers'])->flatMap(fn ($s) => array_column($s['lines'], 'index'))->all();
        $this->assertSame(count($indexes), count(array_unique($indexes)));
    }

    public function test_lines_start_on_tts_and_report_progress(): void
    {
        Sanctum::actingAs($this->recorder);

        $data = $this->getJson('/api/record/listening')->assertOk()->json();

        $this->assertSame(0, $data['line_done']);
        $this->assertGreaterThan(0, $data['line_total']);

        $speaker = $data['scenes'][0]['speakers'][0];
        $this->assertSame(0, $speaker['done']);
        $this->assertSame('tts', $speaker['lines'][0]['state']);
    }

    public function test_recording_a_line_moves_it_to_pending_then_live_on_approval(): void
    {
        Sanctum::actingAs($this->recorder);

        $this->postJson('/api/record/listening/kahvilassa/0', ['audio' => $this->take()])
            ->assertOk()
            ->assertJsonPath('scene', 'kahvilassa')
            ->assertJsonPath('index', 0);

        // Pending: recorded, not live yet - the app still plays TTS.
        $speakers = collect($this->getJson('/api/record/listening')->json('scenes'))
            ->firstWhere('id', 'kahvilassa')['speakers'];
        $line = collect($speakers)->flatMap(fn ($s) => $s['lines'])->firstWhere('index', 0);
        $this->assertSame('pending', $line['state']);
        $this->assertStringStartsNotWith('/audio/human/', (string) $line['current_url']);

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/admin/recordings')
            ->assertOk()
            ->assertJsonPath('listening.0.scene', 'kahvilassa')
            ->assertJsonPath('listening.0.speaker', 'Noora');

        $this->postJson('/api/admin/recordings/approve', ['type' => 'listening', 'key' => 'kahvilassa:0'])
            ->assertOk();

        // Approved: the human take is what the learner now hears.
        $this->assertStringStartsWith('/audio/human/', (string) Listening::audioUrl('kahvilassa', 0));
    }

    public function test_approved_take_reaches_the_learner(): void
    {
        Sanctum::actingAs($this->recorder);
        $this->postJson('/api/record/listening/kahvilassa/0', ['audio' => $this->take()])->assertOk();

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/recordings/approve', ['type' => 'listening', 'key' => 'kahvilassa:0'])->assertOk();

        // The end of the whole chain: the listening page serves the human clip.
        $scene = $this->getJson('/api/listening/kahvilassa')->assertOk()->json('scene');
        $this->assertStringStartsWith('/audio/human/', $scene['lines'][0]['audio_url']);
    }

    public function test_rejecting_a_take_returns_the_line_to_the_queue(): void
    {
        Sanctum::actingAs($this->recorder);
        $this->postJson('/api/record/listening/kahvilassa/0', ['audio' => $this->take()])->assertOk();

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/recordings/reject', ['type' => 'listening', 'key' => 'kahvilassa:0'])->assertOk();

        Sanctum::actingAs($this->recorder);
        $speakers = collect($this->getJson('/api/record/listening')->json('scenes'))
            ->firstWhere('id', 'kahvilassa')['speakers'];
        $line = collect($speakers)->flatMap(fn ($s) => $s['lines'])->firstWhere('index', 0);

        $this->assertSame('tts', $line['state']);
    }

    public function test_admin_can_revert_a_live_line_back_to_tts(): void
    {
        Sanctum::actingAs($this->recorder);
        $this->postJson('/api/record/listening/kahvilassa/0', ['audio' => $this->take()])->assertOk();

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/recordings/approve', ['type' => 'listening', 'key' => 'kahvilassa:0'])->assertOk();
        $this->assertStringStartsWith('/audio/human/', (string) Listening::audioUrl('kahvilassa', 0));

        $this->deleteJson('/api/record/listening/kahvilassa/0')->assertOk();

        // Back to the generated clip, which was never deleted.
        $this->assertSame('/audio/listening/listening-kahvilassa-0.mp3', Listening::audioUrl('kahvilassa', 0));
    }

    public function test_invented_lines_are_rejected(): void
    {
        Sanctum::actingAs($this->recorder);

        $this->postJson('/api/record/listening/kuuhun-lento/0', ['audio' => $this->take()])->assertNotFound();
        // Line 999 doesn't exist in a 10-line scene.
        $this->postJson('/api/record/listening/kahvilassa/999', ['audio' => $this->take()])->assertNotFound();
    }

    public function test_conversation_queue_needs_recording_rights(): void
    {
        $member = User::create([
            'name' => 'Member', 'email' => 'member@example.com', 'password' => bcrypt('password'),
        ]);
        Sanctum::actingAs($member);

        $this->getJson('/api/record/listening')->assertStatus(403);
        $this->postJson('/api/record/listening/kahvilassa/0', ['audio' => $this->take()])->assertStatus(403);
    }

    public function test_only_admins_can_revert_a_live_line(): void
    {
        Sanctum::actingAs($this->recorder);
        $this->deleteJson('/api/record/listening/kahvilassa/0')->assertStatus(403);
    }

    public function test_conversation_takes_do_not_collide_with_sentence_takes(): void
    {
        Sanctum::actingAs($this->recorder);

        // Both land in audio/pending/; only the "listening-" prefix separates
        // them, so a conversation take must never show up as a sentence one.
        $this->postJson('/api/record/listening/kahvilassa/0', ['audio' => $this->take()])->assertOk();

        Sanctum::actingAs($this->admin);
        $data = $this->getJson('/api/admin/recordings')->assertOk()->json();

        $this->assertCount(1, $data['listening']);
        $this->assertEmpty($data['sentences']);
        $this->assertEmpty($data['words']);
    }
}
