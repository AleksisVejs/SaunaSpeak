<?php

use App\Models\Lesson;
use App\Support\LessonImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

/**
 * Adds the A0 "Joining Words" lesson to databases that already exist.
 *
 * The course could order coffee and catch a tram but never join two thoughts:
 * across all 552 sentences "ja" appeared 4 times, "mutta" and "tai" zero. A
 * beginner without the glue words can name things and not say anything, which
 * is exactly what "I can't get into it" feels like from the inside.
 *
 * JsonLessonSeeder only adds lessons on a fresh seed, so this replays the same
 * import for deployed databases. Idempotent: it checks the title first.
 *
 * NOTE: new sentences arrive without audio. Run `php artisan audio:generate`
 * (and `audio:eleven` for the better voice) after migrating.
 */
return new class extends Migration
{
    private const FILE = 'lessons/54-joining-words.json';

    private const TITLE = 'Joining Words: ja, mut, tai';

    public function up(): void
    {
        // Only ever back-fill a database that already HAS the course. On a
        // fresh install (and in tests) migrations run before any seeding, so
        // the table is empty here and this stays out of the way: JsonLessonSeeder
        // imports the same file, and test fixtures keep their controlled world.
        if (Lesson::count() === 0 || Lesson::where('title', self::TITLE)->exists()) {
            return;
        }

        $data = json_decode(File::get(database_path(self::FILE)), true);
        $importer = new LessonImporter;

        // A malformed file must not take the deploy down with it - the course
        // is simply one lesson short until the file is fixed.
        if ($importer->validate($data) !== []) {
            return;
        }

        $importer->import($data);
    }

    public function down(): void
    {
        $lesson = Lesson::where('title', self::TITLE)->first();
        if (! $lesson) {
            return;
        }

        $pattern = $lesson->pattern;
        $lesson->sentences()->delete();
        $lesson->delete();
        $pattern?->delete();
    }
};
