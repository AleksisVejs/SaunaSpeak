<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Sentence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The SPA shell is one file serving every route. Before SpaController it
 * carried a hardcoded canonical pointing at '/', which told crawlers that
 * every page was a duplicate of the homepage.
 */
class SpaCanonicalTest extends TestCase
{
    use RefreshDatabase;

    private string $shell;

    protected function setUp(): void
    {
        parent::setUp();

        // Stand in for the built frontend, which only exists after a deploy.
        $this->shell = public_path('index.html');
        file_put_contents($this->shell, <<<'HTML'
        <!doctype html>
        <html lang="en">
          <head>
            <title>SaunaSpeak - Learn Spoken Finnish</title>
            <meta name="description" content="Default shell description." />
          </head>
          <body><div id="app"></div></body>
        </html>
        HTML);
    }

    protected function tearDown(): void
    {
        @unlink($this->shell);
        parent::tearDown();
    }

    public function test_each_route_gets_its_own_canonical(): void
    {
        $this->get('/')->assertSee('<link rel="canonical" href="https://saunaspeak.com/" />', false);
        $this->get('/compare')->assertSee('<link rel="canonical" href="https://saunaspeak.com/compare" />', false);
        $this->get('/try')->assertSee('<link rel="canonical" href="https://saunaspeak.com/try" />', false);
    }

    public function test_shell_ships_exactly_one_canonical(): void
    {
        $html = $this->get('/pricing')->getContent();

        $this->assertSame(1, substr_count($html, 'rel="canonical"'));
    }

    public function test_public_routes_get_their_own_title_and_description(): void
    {
        $res = $this->get('/compare');

        $res->assertSee('<title>Best apps to learn Finnish, compared honestly (2026) - SaunaSpeak</title>', false);
        $res->assertDontSee('Default shell description.', false);
    }

    public function test_app_routes_keep_the_shell_tags_but_still_self_canonicalise(): void
    {
        $res = $this->get('/dashboard');

        $res->assertSee('Default shell description.', false);
        $res->assertSee('<link rel="canonical" href="https://saunaspeak.com/dashboard" />', false);
    }

    public function test_lesson_pages_describe_the_lesson_they_serve(): void
    {
        $lesson = Lesson::create(['title' => 'Buses and Trains', 'level' => 'A1', 'order_index' => 1]);
        Sentence::create([
            'lesson_id' => $lesson->id,
            'finnish_text' => 'Onks tää bussi?',
            'english_text' => 'Is this the bus?',
        ]);

        $res = $this->get('/lessons/buses-and-trains');

        $res->assertSee('<title>Buses and Trains - spoken Finnish lesson - SaunaSpeak</title>', false);
        $res->assertSee('Onks tää bussi?', false);
        $res->assertSee('<link rel="canonical" href="https://saunaspeak.com/lessons/buses-and-trains" />', false);
    }

    public function test_unknown_lesson_slug_falls_back_to_the_shell_tags(): void
    {
        $res = $this->get('/lessons/does-not-exist');

        $res->assertSee('Default shell description.', false);
        $res->assertSee('<link rel="canonical" href="https://saunaspeak.com/lessons/does-not-exist" />', false);
    }
}
