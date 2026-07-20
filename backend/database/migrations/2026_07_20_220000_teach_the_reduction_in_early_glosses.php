<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Lessons 1-2 had dictionary glosses ("onks = is", "tääl = here") that
 * translate the residue while hiding the transformation - and the
 * transformation (onko → onks, täällä → tääl) is the entire product thesis,
 * shown at the exact moment a learner wonders "why is this word like that".
 * Lesson 3 already glossed in the teaching style; 1-2 now match it.
 *
 * JsonLessonSeeder never updates lessons already present, so deployed
 * databases pick the reworded glosses up from the lesson JSONs here. Reading
 * the files (not a copied map) keeps this in sync with the source of truth.
 */
return new class extends Migration
{
    private const FILES = [
        'lessons/01-eating-at-a-restaurant.json',
        'lessons/02-buses-and-trains.json',
    ];

    public function up(): void
    {
        foreach (self::FILES as $file) {
            $data = json_decode(File::get(database_path($file)), true);

            foreach ($data['sentences'] ?? [] as $row) {
                if (empty($row['fi']) || empty($row['glosses'])) {
                    continue;
                }

                DB::table('sentences')
                    ->where('finnish_text', $row['fi'])
                    ->update(['word_glosses' => json_encode($row['glosses'], JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    public function down(): void
    {
        // Content-only improvement; the old dictionary glosses are not worth
        // carrying around. Rolling back leaves the richer glosses in place.
    }
};
