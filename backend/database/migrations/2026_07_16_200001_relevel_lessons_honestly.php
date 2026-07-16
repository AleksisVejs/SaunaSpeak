<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Honest CEFR re-leveling. The upper lessons were inflated: "City Slang
 * Starter Pack" (mesta, fillari, kämppis) was labeled C1 but is week-one
 * vocabulary; "No Niin" is one of the first phrases any learner meets.
 * This moves 17 lessons down to where they belong, leaving C1 empty until
 * genuinely advanced content exists.
 *
 * JsonLessonSeeder never updates lessons already present (it matches by
 * title and skips), so deployed databases need this migration to pick up
 * the new levels from database/lessons/*.json.
 *
 * Invariants:
 * - Sentence ids never change: the committed audio files
 *   (public/audio/sentence-N.mp3) are keyed by id.
 * - order_index is re-slotted so each level block stays contiguous
 *   (a level change mid-block would render a stray "Level X begins"
 *   divider on the path). Within a level, lessons keep creation (id)
 *   order - exactly what a fresh `migrate --seed` produces, so migrated
 *   and fresh databases end up with the identical path.
 * - users.checkpoints is left untouched: a pass stays valid for its level
 *   bucket. Learners who passed B1/B2 now also skip the lessons that moved
 *   into that bucket (consistent with what a placement pass means), and a
 *   C1 badge simply remains as a badge - the C1 checkpoint reports
 *   "nothing to study" while the level is empty.
 */
return new class extends Migration
{
    /** title => [old level, new level] */
    private const MOVES = [
        'Almost Happened' => ['C1', 'B2'],
        'It Just Happened' => ['C1', 'B2'],
        'You Never Know' => ['C1', 'B2'],
        'When Things Go Sideways' => ['C1', 'B2'],
        'Totally Lost' => ['C1', 'B2'],
        'Just Kidding' => ['C1', 'B1'],
        'Insanely Good, Totally Beat' => ['C1', 'B1'],
        'Not Bad at All' => ['C1', 'B1'],
        'When to Be Formal' => ['C1', 'B1'],
        'No Niin' => ['C1', 'B1'],
        'Dating and Gossip' => ['C1', 'B1'],
        'City Slang Starter Pack' => ['C1', 'A2'],
        'By the Time I Got There' => ['B2', 'B1'],
        'Good News, Bad News' => ['B2', 'B1'],
        'Totally Agree, No Way' => ['B2', 'B1'],
        'It Bugs Me' => ['B2', 'B1'],
        'Oh My!' => ['B2', 'B1'],
    ];

    private const LEVEL_ORDER = ['A0', 'A1', 'A2', 'B1', 'B2', 'C1'];

    public function up(): void
    {
        $this->apply(fn (array $move) => $move[1]);
    }

    public function down(): void
    {
        $this->apply(fn (array $move) => $move[0]);
    }

    /** Set each moved lesson's level, then rebuild contiguous level blocks. */
    private function apply(callable $target): void
    {
        DB::transaction(function () use ($target) {
            foreach (self::MOVES as $title => $move) {
                DB::table('lessons')->where('title', $title)->update(['level' => $target($move)]);
            }

            // Re-slot the whole path: level blocks in CEFR order, creation
            // (id) order within a block. Matches LessonImporter's "append at
            // the end of its level block", so this is also self-healing for
            // any environment whose order drifted.
            $rank = array_flip(self::LEVEL_ORDER);
            $lessons = DB::table('lessons')->get(['id', 'level'])
                ->sortBy(fn ($l) => ($rank[$l->level] ?? count($rank)) * 1_000_000 + $l->id)
                ->values();

            foreach ($lessons as $i => $lesson) {
                DB::table('lessons')->where('id', $lesson->id)->update(['order_index' => $i + 1]);
            }
        });
    }
};
