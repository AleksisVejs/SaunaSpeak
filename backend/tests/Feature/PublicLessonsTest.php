<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Pattern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The unauthenticated /api/public/lessons endpoints behind the SEO pages. */
class PublicLessonsTest extends TestCase
{
    use RefreshDatabase;

    private function seedLessons(): void
    {
        $pattern = Pattern::create([
            'title' => 'Spoken pronouns',
            'summary' => 'mä, sä, se.',
            'examples' => ['Mä oon = I am'],
            'order_index' => 1,
        ]);

        foreach ([['First Words', 'A0', 1], ['Coffee Time', 'A0', 2], ['At Home', 'A1', 3]] as [$title, $level, $order]) {
            $lesson = Lesson::create([
                'title' => $title,
                'level' => $level,
                'order_index' => $order,
                'pattern_id' => $pattern->id,
            ]);
            $lesson->sentences()->create([
                'finnish_text' => 'Mä oon Anna.',
                'written_text' => 'Minä olen Anna.',
                'english_text' => "I'm Anna.",
                'word_glosses' => ['mä' => 'spoken form of minä'],
                'audio_url' => '/audio/sentence-1.mp3',
            ]);
        }
    }

    public function test_index_lists_lessons_without_auth(): void
    {
        $this->seedLessons();

        $this->getJson('/api/public/lessons')
            ->assertOk()
            ->assertJsonCount(3, 'lessons')
            ->assertJsonPath('lessons.0.slug', 'first-words')
            ->assertJsonPath('lessons.0.teaser', 'Mä oon Anna.');
    }

    public function test_show_returns_sentences_glosses_and_neighbors(): void
    {
        $this->seedLessons();

        $this->getJson('/api/public/lessons/coffee-time')
            ->assertOk()
            ->assertJsonPath('lesson.title', 'Coffee Time')
            ->assertJsonPath('lesson.pattern.title', 'Spoken pronouns')
            ->assertJsonPath('lesson.sentences.0.written_text', 'Minä olen Anna.')
            ->assertJsonPath('lesson.sentences.0.word_glosses.mä', 'spoken form of minä')
            ->assertJsonPath('previous.slug', 'first-words')
            ->assertJsonPath('next.slug', 'at-home');
    }

    public function test_show_404s_on_unknown_slug(): void
    {
        $this->seedLessons();

        $this->getJson('/api/public/lessons/nonexistent')->assertNotFound();
    }

    public function test_try_audio_maps_texts_to_current_audio(): void
    {
        $this->seedLessons();

        // Duplicate sentence texts across lessons: the human take must win.
        Lesson::where('title', 'Coffee Time')->first()->sentences()
            ->first()->update(['audio_url' => '/audio/human/sentence-2.mp3']);

        // Sentence texts contain dots, so read the array instead of JSON paths.
        $audio = $this->getJson('/api/public/try-audio?'.http_build_query([
            'texts' => ['Mä oon Anna.', 'Onks sul nälkä?'],
        ]))->assertOk()->json('audio');

        $this->assertSame('/audio/human/sentence-2.mp3', $audio['Mä oon Anna.'] ?? null);
        $this->assertArrayNotHasKey('Onks sul nälkä?', $audio);
    }

    public function test_try_audio_ignores_junk_input(): void
    {
        $this->seedLessons();

        // Non-string entries and absurd lengths are dropped, not 500s.
        $this->getJson('/api/public/try-audio?texts[0][nested]=x&texts[1]='.str_repeat('a', 500))
            ->assertOk()
            ->assertJsonPath('audio', []);
    }

    public function test_public_stats_report_native_audio_progress(): void
    {
        $this->seedLessons();
        Lesson::first()->sentences()->first()->update(['audio_url' => '/audio/human/sentence-1.mp3']);

        $this->getJson('/api/public/stats')
            ->assertOk()
            ->assertJsonPath('sentences_total', 3)
            ->assertJsonPath('sentences_human', 1);
    }

    public function test_sitemap_includes_lesson_urls(): void
    {
        $this->seedLessons();

        $response = $this->get('/sitemap.xml');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertStringContainsString('https://saunaspeak.com/lessons/coffee-time', $response->getContent());
        $this->assertStringContainsString('https://saunaspeak.com/lessons</loc>', $response->getContent());
    }
}
