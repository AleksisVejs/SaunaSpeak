<?php

namespace App\Console\Commands;

use App\Models\Lesson;
use App\Support\LessonImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Content pipeline, step 2: import a reviewed lesson draft into the course.
 * The reviewed JSON is also copied into database/lessons/ (tracked by git)
 * so production picks it up via `php artisan db:seed --class=JsonLessonSeeder`.
 * After importing, run `php artisan audio:generate` for the new MP3s.
 */
class ImportLesson extends Command
{
    protected $signature = 'lesson:import {file : path to a reviewed lesson-draft JSON}';

    protected $description = 'Import a reviewed lesson draft (from lesson:draft) into the database';

    public function handle(LessonImporter $importer): int
    {
        $path = $this->argument('file');
        if (! File::exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $data = json_decode(File::get($path), true);
        $problems = $importer->validate($data);
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

        $lesson = $importer->import($data);
        $this->info("Imported \"{$lesson->title}\" ({$lesson->level}) as lesson #{$lesson->order_index} with ".count($data['sentences']).' sentences.');

        // Ship the reviewed lesson to production: numbered so the seeder
        // imports in the same order (keeps sentence ids - and therefore the
        // id-keyed audio files - aligned between environments).
        $dir = database_path('lessons');
        File::ensureDirectoryExists($dir);
        $next = collect(File::files($dir))->count() + 1;
        $shipped = sprintf('%s/%02d-%s.json', $dir, $next, Str::slug($data['title']));
        File::put($shipped, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line('Copied for production: '.$shipped.' (commit it; server runs db:seed --class=JsonLessonSeeder)');

        $this->line('Now generate audio for the new sentences:');
        $this->line('  php artisan audio:generate');

        return self::SUCCESS;
    }
}
