<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Support\LessonImporter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Imports every reviewed lesson JSON from database/lessons/ (the tracked
 * output of `lesson:import`). Files are numbered and imported in filename
 * order so sentence ids - and the id-keyed audio files shipped in git -
 * line up across environments. Idempotent: lessons already present (by
 * title) are skipped, so this is safe on every deploy.
 */
class JsonLessonSeeder extends Seeder
{
    public function run(): void
    {
        $dir = database_path('lessons');
        if (! File::isDirectory($dir)) {
            return;
        }

        $importer = new LessonImporter;

        $files = collect(File::files($dir))
            ->filter(fn ($f) => $f->getExtension() === 'json')
            ->sortBy(fn ($f) => $f->getFilename());

        foreach ($files as $file) {
            $data = json_decode(File::get($file->getPathname()), true);

            if (! is_array($data) || empty($data['title'])) {
                $this->command?->warn("Skipping unreadable {$file->getFilename()}");

                continue;
            }

            if (Lesson::where('title', $data['title'])->exists()) {
                continue; // already imported
            }

            if ($importer->validate($data) !== []) {
                $this->command?->warn("Skipping invalid {$file->getFilename()}");

                continue;
            }

            $lesson = $importer->import($data);
            $this->command?->info("Imported \"{$lesson->title}\" ({$lesson->level}) as lesson #{$lesson->order_index}.");
        }
    }
}
