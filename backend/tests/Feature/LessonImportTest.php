<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Support\LessonImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonImportTest extends TestCase
{
    use RefreshDatabase;

    private function draft(string $title, string $level): array
    {
        return [
            'title' => $title,
            'level' => $level,
            'pattern' => ['title' => 'P', 'summary' => 'S', 'examples' => ['a = b']],
            'sentences' => [['fi' => 'Moi vaan '.$title.'.', 'en' => 'Hi.', 'written' => null, 'glosses' => null]],
        ];
    }

    public function test_import_inserts_at_the_end_of_its_level_block(): void
    {
        Lesson::create(['title' => 'A1 one', 'level' => 'A1', 'order_index' => 1]);
        $a2 = Lesson::create(['title' => 'A2 one', 'level' => 'A2', 'order_index' => 2]);

        // An A1 lesson must land INSIDE the A1 block, pushing A2 down -
        // appending it after A2 would render a second "Level A1 begins"
        // divider on the lesson path.
        $imported = (new LessonImporter)->import($this->draft('A1 two', 'A1'));

        $this->assertSame(2, $imported->order_index);
        $this->assertSame(3, $a2->fresh()->order_index);
    }

    public function test_import_of_unseen_level_appends_after_everything(): void
    {
        Lesson::create(['title' => 'A1 one', 'level' => 'A1', 'order_index' => 1]);

        $imported = (new LessonImporter)->import($this->draft('B1 one', 'B1'));

        $this->assertSame(2, $imported->order_index);
    }

    public function test_validate_rejects_duplicate_sentences(): void
    {
        $lesson = Lesson::create(['title' => 'A1 one', 'level' => 'A1', 'order_index' => 1]);
        $lesson->sentences()->create(['finnish_text' => 'Emmä tiiä.', 'english_text' => "I don't know."]);

        $draft = $this->draft('A1 two', 'A1');
        $draft['sentences'][0]['fi'] = 'Emmä tiiä.';

        $problems = (new LessonImporter)->validate($draft);
        $this->assertNotEmpty($problems);
        $this->assertStringContainsString('already exists', $problems[0]);
    }
}
