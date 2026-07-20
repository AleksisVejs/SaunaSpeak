<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The "City Slang Starter Pack" summary claimed its words are "in every
 * conversation under 40". Native feedback (July 2026 Reddit/Facebook
 * rounds) contradicts that: spora in particular splits even Helsinki
 * speakers, and none of these words are nationwide. The summary now says
 * what is defensible - everyday speech around Helsinki, some of it spread
 * further.
 *
 * JsonLessonSeeder never updates lessons already present (it matches by
 * title and skips), so deployed databases need this migration to pick up
 * the reworded summary from database/lessons/49-city-slang-starter-pack.json.
 */
return new class extends Migration
{
    private const TITLE = 'Stadi slang: mesta, fillari, kämppis';

    private const OLD = 'The Helsinki slang layer you\'ll hear daily: mesta (place), fillari (bike), kämppis (roommate), spora (tram), bailut (party), bongata (to spot). None of it is in the dictionary your teacher gave you - all of it is in every conversation under 40.';

    private const NEW = 'The Helsinki slang layer: mesta (place), fillari (bike), kämppis (roommate), spora (tram), bailut (party), bongata (to spot). None of it is in the dictionary your teacher gave you - all of it is everyday speech around Helsinki, and plenty of it has spread further.';

    public function up(): void
    {
        DB::table('patterns')->where('title', self::TITLE)->update(['summary' => self::NEW]);
    }

    public function down(): void
    {
        DB::table('patterns')->where('title', self::TITLE)->update(['summary' => self::OLD]);
    }
};
