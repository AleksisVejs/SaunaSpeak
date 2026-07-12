<?php

namespace App\Console\Commands;

use App\Models\Lesson;
use App\Models\Pattern;
use App\Models\Sentence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Content pipeline, step 2: import a reviewed lesson draft into the course.
 * Appends after the current last lesson. Rerun-safe: refuses duplicate titles.
 * After importing, run `php artisan audio:generate` for the new MP3s.
 */
class ImportLesson extends Command
{
    protected $signature = 'lesson:import {file : path to a reviewed lesson-draft JSON}';

    protected $description = 'Import a reviewed lesson draft (from lesson:draft) into the database';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! File::exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $data = json_decode(File::get($path), true);
        $problems = $this->validateDraft($data);
        if ($problems !== []) {
            foreach ($problems as $p) {
                $this->error($p);
            }

            return self::FAILURE;
        }

        if (Lesson::where('title', $data['title'])->exists()) {
            $this->error("A lesson titled \"{$data['title']}\" already exists - not importing twice.");

            return self::FAILURE;
        }

        DB::transaction(function () use ($data) {
            $pattern = Pattern::create([
                'title' => $data['pattern']['title'],
                'summary' => $data['pattern']['summary'],
                'examples' => $data['pattern']['examples'],
                'order_index' => (int) Pattern::max('order_index') + 1,
            ]);

            $lesson = Lesson::create([
                'title' => $data['title'],
                'level' => $data['level'],
                'order_index' => (int) Lesson::max('order_index') + 1,
                'pattern_id' => $pattern->id,
            ]);

            foreach ($data['sentences'] as $row) {
                $lesson->sentences()->create([
                    'finnish_text' => $row['fi'],
                    'english_text' => $row['en'],
                    'written_text' => $row['written'] ?? null,
                    'word_glosses' => $row['glosses'] ?? null,
                ]);
            }

            $this->info("Imported \"{$lesson->title}\" ({$lesson->level}) as lesson #{$lesson->order_index} with ".count($data['sentences']).' sentences.');
        });

        $this->line('Now generate audio for the new sentences:');
        $this->line('  php artisan audio:generate');

        return self::SUCCESS;
    }

    /** @return list<string> human-readable problems, empty when valid */
    private function validateDraft(mixed $data): array
    {
        $problems = [];

        if (! is_array($data)) {
            return ['Not valid JSON.'];
        }
        foreach (['title', 'level', 'pattern', 'sentences'] as $key) {
            if (empty($data[$key])) {
                $problems[] = "Missing \"{$key}\".";
            }
        }
        if (isset($data['pattern']) && (empty($data['pattern']['title']) || empty($data['pattern']['summary']) || empty($data['pattern']['examples']))) {
            $problems[] = 'Pattern needs title, summary and examples.';
        }
        foreach ((array) ($data['sentences'] ?? []) as $i => $row) {
            if (empty($row['fi']) || empty($row['en'])) {
                $problems[] = "Sentence #{$i} needs both \"fi\" and \"en\".";
            }
            // Duplicate guard: same Finnish sentence elsewhere in the course.
            if (! empty($row['fi']) && Sentence::where('finnish_text', $row['fi'])->exists()) {
                $problems[] = "Sentence #{$i} (\"{$row['fi']}\") already exists in the course.";
            }
        }

        return $problems;
    }
}
