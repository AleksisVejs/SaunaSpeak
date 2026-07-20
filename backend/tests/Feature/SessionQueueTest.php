<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Testi',
            'email' => 'testi@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->lesson = Lesson::create(['title' => 'Testilektio', 'level' => 'A0', 'order_index' => 1]);

        Sanctum::actingAs($this->user);
    }

    private function makeSentences(int $count): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $ids[] = $this->lesson->sentences()->create([
                'finnish_text' => "Lause {$i}",
                'english_text' => "Sentence {$i}",
            ])->id;
        }

        return $ids;
    }

    public function test_session_size_follows_request_within_limits(): void
    {
        $this->makeSentences(15);

        $this->getJson('/api/today-session?size=5')->assertOk()->assertJsonCount(5, 'sentences');
        // Clamped to the 3..12 window.
        $this->getJson('/api/today-session?size=1')->assertOk()->assertJsonCount(3, 'sentences');
        $this->getJson('/api/today-session?size=99')->assertOk()->assertJsonCount(12, 'sentences');
    }

    public function test_due_reviews_are_interleaved_with_new_sentences(): void
    {
        $ids = $this->makeSentences(4);

        // First two sentences are due reviews; the last two are unseen.
        foreach ([$ids[0], $ids[1]] as $id) {
            UserProgress::create([
                'user_id' => $this->user->id,
                'sentence_id' => $id,
                'status' => UserProgress::STATUS_LEARNING,
                'next_review_at' => now()->subHour(),
            ]);
        }

        $res = $this->getJson('/api/today-session?size=4')->assertOk()->assertJsonPath('due_count', 2);
        $statuses = collect($res->json('sentences'))->pluck('status')->all();

        // Weave, not blocks: review, new, review, new.
        $this->assertSame(['learning', 'new', 'learning', 'new'], $statuses);
    }

    public function test_full_size_session_weaves_in_listening_and_a_drill(): void
    {
        $this->lesson->update(['level' => 'A1']);
        $this->makeSentences(8);

        // 5-/15-minute goals (size >= 6) get the four-skill weave...
        $res = $this->getJson('/api/today-session?size=6')->assertOk();
        $this->assertNotNull($res->json('woven.listening'), 'a listen step should be woven in');
        $this->assertNotNull($res->json('woven.transform'), 'a bend step should be woven in');

        // ...while the 2-minute "taste" (size 3) stays short - no extra steps.
        $light = $this->getJson('/api/today-session?size=3')->assertOk();
        $this->assertNull($light->json('woven.listening'));
        $this->assertNull($light->json('woven.transform'));
    }

    /**
     * Every listening scene and drill set starts at A1, so an A0 learner has
     * nothing at their level. They must get NO woven extras rather than a
     * random pick from the whole catalog - which is how a learner four days
     * into Finnish used to land in the B1 job interview at native speed.
     */
    public function test_an_a0_session_weaves_in_nothing_above_the_learners_level(): void
    {
        $this->makeSentences(8); // $this->lesson is A0

        $res = $this->getJson('/api/today-session?size=6')->assertOk();

        $this->assertNull($res->json('woven.listening'), 'A0 has no scene at its level - weave none');
        $this->assertNull($res->json('woven.transform'), 'A0 has no drill at its level - weave none');
        $this->assertNotEmpty($res->json('sentences'), 'the sentence block still runs');
    }

    public function test_longest_sessions_weave_in_a_second_conversation(): void
    {
        $this->lesson->update(['level' => 'A1']);
        $this->makeSentences(14);

        // The 15-minute goal (size 12) gets a second, different conversation...
        $big = $this->getJson('/api/today-session?size=12')->assertOk();
        $this->assertNotNull($big->json('woven.listening'));
        $this->assertNotNull($big->json('woven.listening2'), 'a second listen should ride along on long sessions');
        $this->assertNotSame($big->json('woven.listening.id'), $big->json('woven.listening2.id'));

        // ...but a normal 5-minute session (size 6) gets only the one.
        $mid = $this->getJson('/api/today-session?size=6')->assertOk();
        $this->assertNull($mid->json('woven.listening2'));
    }

    public function test_a_themed_lesson_weaves_in_its_own_matching_assets(): void
    {
        // A mapped lesson pins its own scene and drill over the level default.
        $themed = Lesson::create(['title' => 'Buses and Trains', 'level' => 'A1', 'order_index' => 2]);
        for ($i = 1; $i <= 8; $i++) {
            $themed->sentences()->create(['finnish_text' => "Bussi {$i}", 'english_text' => "Bus {$i}"]);
        }

        $res = $this->getJson('/api/today-session?size=6')->assertOk();
        $res->assertJsonPath('woven.listening.id', 'bussissa');
        $res->assertJsonPath('woven.transform.id', 'missa-mihin');
    }

    public function test_all_due_and_no_fresh_still_works(): void
    {
        $ids = $this->makeSentences(2);

        foreach ($ids as $id) {
            UserProgress::create([
                'user_id' => $this->user->id,
                'sentence_id' => $id,
                'status' => UserProgress::STATUS_REVIEW,
                'next_review_at' => now()->subDay(),
            ]);
        }

        $this->getJson('/api/today-session')
            ->assertOk()
            ->assertJsonCount(2, 'sentences')
            ->assertJsonPath('due_count', 2);
    }
}
