<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Lessons 3-10 (and 54-56) glossed only the word each sentence was teaching
 * and left the everyday ones - mä, on, se, mun - unglossed, so tapping them
 * answered "No gloss for this word yet." Every word in a sentence is now
 * glossed, in the wording the rest of the corpus already uses for it.
 *
 * JsonLessonSeeder never updates lessons already present, so deployed
 * databases pick the new glosses up from the lesson JSONs here. Reading the
 * files (not a copied map) keeps this in sync with the source of truth.
 */
return new class extends Migration
{
    private const FILES = [
        'lessons/03-numbers-prices-and-time.json',
        'lessons/04-family-and-friends.json',
        'lessons/05-at-the-pharmacy.json',
        'lessons/06-a-night-out.json',
        'lessons/07-weekend-at-the-mokki.json',
        'lessons/08-when-something-breaks.json',
        'lessons/09-renting-a-place.json',
        'lessons/10-maybe-probably-i-guess.json',
        'lessons/54-joining-words.json',
        'lessons/55-some-of-it-not-all.json',
        'lessons/56-when-words-change-shape.json',
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
        // Content-only improvement; the gaps are not worth carrying around.
        // Rolling back leaves the fuller glosses in place.
    }
};
