<?php

namespace App\Support;

use App\Models\User;

/**
 * The thematic weave.
 *
 * The daily Sauna Session used to be flashcards and nothing else - the
 * connected-speech listening, the generative Taivutus drills and the roleplay
 * all lived on separate screens the guided path never opened. This class turns
 * one sentence block into a four-skill session by picking the extra steps that
 * get woven in after the sentences: one LISTEN (a whole conversation), one BEND
 * (a Taivutus set, generation not recall) and one USE (a roleplay for premium
 * learners; the frontend adds a free self-graded production step otherwise).
 *
 * Two layers of matching:
 *
 *  1. THE THEME MAP (below) links a lesson to the assets that share its
 *     subject - "Buses and Trains" → the bus conversation, the missä/mihin
 *     drill and the bus roleplay. When the session's frontier is a mapped
 *     lesson, its own assets are woven in, so the day hangs together: learn the
 *     café lines, then hear a café, then order in one.
 *
 *  2. LEVEL FALLBACK. The multimedia is sparse - six scenes, four sets, nine
 *     scenarios against 50+ lessons - so most lessons aren't mapped, and a
 *     mapped lesson may pin only some of the three facets. Anything the map
 *     doesn't cover falls back to a level-appropriate pick (at/below the
 *     session level, not-done first, rotated daily) rather than nothing.
 */
class Themes
{
    /** CEFR ladder, low → high. Listening/transforms only reach A2 today. */
    private const LEVELS = ['A0', 'A1', 'A2', 'B1', 'B2', 'C1'];

    /** Scenarios carry a difficulty, not a CEFR level - map it onto the ladder. */
    private const DIFFICULTY_LEVEL = ['easy' => 'A1', 'medium' => 'A2', 'hard' => 'B1'];

    /**
     * Below this session size (the "2-minute taste" goal) only the short output
     * step is woven in - a listen + a full drill set would blow a 2-minute
     * session wide open. The 5- and 15-minute goals get the full weave.
     */
    private const FULL_WEAVE_MIN_SIZE = 6;

    /**
     * Lesson title → the assets that share its theme. Keyed by title because
     * that's how lessons are identified (no slug column; the seeder is
     * idempotent on title). Any facet a lesson doesn't pin ('listening',
     * 'transform', 'scenario') falls back to a level-appropriate pick, so a
     * partial entry is normal - a grammar lesson often maps only its drill.
     *
     * Ids must match: listening scene ids (database/listening/*.json minus the
     * numeric prefix), transform set ids (database/transforms/*.json likewise),
     * and scenario ids (App\Support\Scenarios). Unknown ids are ignored and the
     * facet falls back, so a retired asset degrades quietly.
     */
    private const THEME_MAP = [
        // ---- topic lessons: the strong clusters (scene + drill + roleplay) ----
        'Eating at a Restaurant' => ['listening' => 'kahvilassa', 'transform' => 'kysymys', 'scenario' => 'ravintola'],
        'Buses and Trains' => ['listening' => 'bussissa', 'transform' => 'missa-mihin', 'scenario' => 'bussi'],
        'Numbers, Prices and Time' => ['listening' => 'kaupassa', 'transform' => 'kysymys', 'scenario' => 'tori'],
        'Family and Friends' => ['transform' => 'kysymys', 'scenario' => 'naapuri'],
        'At the Pharmacy' => ['listening' => 'ajanvaraus', 'transform' => 'kielto', 'scenario' => 'apteekki'],
        'A Night Out' => ['listening' => 'kahvilassa', 'transform' => 'mennyt-aika', 'scenario' => 'ravintola'],
        'Weekend at the Mökki' => ['listening' => 'saunailta', 'transform' => 'mennyt-aika', 'scenario' => 'saunailta'],
        'When Something Breaks' => ['listening' => 'rappukaytavassa', 'transform' => 'kielto', 'scenario' => 'naapuri'],
        'Buying Clothes' => ['listening' => 'kaupassa', 'transform' => 'kysymys', 'scenario' => 'kauppa'],
        'Running Errands' => ['listening' => 'kaupassa', 'transform' => 'missa-mihin', 'scenario' => 'kauppa'],
        'Hobbies and Free Time' => ['listening' => 'saunailta', 'transform' => 'mennyt-aika', 'scenario' => 'saunailta'],

        // ---- B1 clusters: the intermediate scenes and drills ----
        'Renting a Place' => ['listening' => 'asuntonaytto', 'transform' => 'missa-mihin', 'scenario' => 'naapuri'],
        'Job Hunting' => ['listening' => 'tyohaastattelu', 'transform' => 'konditionaali'],
        'The One Who...' => ['transform' => 'relatiivilause'],
        'You Should Try It' => ['transform' => 'konditionaali'],
        'Would Have, Should Have' => ['transform' => 'konditionaali'],
        'Maybe, Probably, I Guess' => ['transform' => 'konditionaali'],

        // ---- grammar-forward lessons: the drill is the theme ----
        'Come Here, Look at This' => ['transform' => 'missa-mihin'],
        'Last Week, Next Month' => ['transform' => 'mennyt-aika'],
        'It Just Happened' => ['transform' => 'mennyt-aika'],
        'Almost Happened' => ['transform' => 'mennyt-aika'],
        'By the Time I Got There' => ['transform' => 'mennyt-aika'],
        'Have You Ever?' => ['transform' => 'mennyt-aika'],
        'Allowed or Not' => ['transform' => 'kielto'],
        'Not Bad at All' => ['transform' => 'kielto'],
        'Totally Agree, No Way' => ['transform' => 'kielto'],
        'Can You Even?' => ['transform' => 'kysymys'],
    ];

    /** Position of a level on the ladder; unknown/`null` falls back to A1. */
    public static function levelIndex(?string $level): int
    {
        $i = array_search($level, self::LEVELS, true);

        return $i === false ? 1 : $i;
    }

    /**
     * The woven extras for one session.
     *
     * @param  string  $focusLevel  the level the sentence block sits at
     * @param  int  $size  the session's sentence count (gates the full weave)
     * @param  ?string  $focusTitle  the frontier lesson's title, for theme matching
     * @return array{listening: ?array, transform: ?array, use: ?array}
     */
    public static function wovenFor(User $user, string $focusLevel, int $size, ?string $focusTitle = null): array
    {
        // Stable per user per day: same weave if they reload, a fresh pick tomorrow.
        $seed = (int) $user->id + (int) now()->format('z');
        $full = $size >= self::FULL_WEAVE_MIN_SIZE;
        $theme = self::THEME_MAP[$focusTitle] ?? [];

        return [
            'listening' => $full ? self::pickListening($user, $focusLevel, $seed, $theme['listening'] ?? null) : null,
            'transform' => $full ? self::pickTransform($user, $focusLevel, $seed, $theme['transform'] ?? null) : null,
            'use' => self::pickUse($user, $focusLevel, $seed, $theme['scenario'] ?? null),
        ];
    }

    /** One listening scene: the theme's own if mapped, else level-matched. */
    private static function pickListening(User $user, string $focusLevel, int $seed, ?string $themeId): ?array
    {
        $done = $user->listening_done ?? [];
        $catalog = Listening::index();

        $pick = self::byId($catalog, $themeId)
            ?? self::choose(self::atOrBelow($catalog, $focusLevel), $done, $seed);

        return $pick === null ? null : $pick + ['done' => isset($done[$pick['id']])];
    }

    /** One Taivutus set: the theme's own if mapped, else level-matched. */
    private static function pickTransform(User $user, string $focusLevel, int $seed, ?string $themeId): ?array
    {
        $done = $user->transforms_done ?? [];
        $catalog = Transforms::index();

        $pick = self::byId($catalog, $themeId)
            ?? self::choose(self::atOrBelow($catalog, $focusLevel), $done, $seed);

        return $pick === null ? null : $pick + ['done' => isset($done[$pick['id']])];
    }

    /**
     * The USE step. Premium learners get a roleplay scenario - the theme's own
     * when mapped, otherwise goal-matched and at/below the focus difficulty,
     * not-done first. Free learners get `null` here and the frontend supplies a
     * self-graded "say it for real" step from a sentence they just studied.
     */
    private static function pickUse(User $user, string $focusLevel, int $seed, ?string $themeId): ?array
    {
        if (! $user->isPremium()) {
            return null;
        }

        $done = $user->scenarios_done ?? [];

        // The theme's own scenario wins outright when it's mapped and real.
        $scenario = $themeId !== null ? Scenarios::find($themeId) : null;

        if ($scenario === null) {
            $goal = $user->preferences['goal'] ?? null;

            $eligible = array_values(array_filter(
                Scenarios::forGoal($goal),
                fn (array $s) => self::levelIndex(self::DIFFICULTY_LEVEL[$s['difficulty']] ?? 'A1')
                    <= self::levelIndex($focusLevel),
            ));

            if ($eligible === []) {
                $eligible = Scenarios::forGoal($goal); // nothing at level: use the whole catalog
            }

            // Keep the goal-first order but prefer a scenario they haven't cleared.
            $notDone = array_values(array_filter($eligible, fn (array $s) => ! isset($done[$s['id']])));
            $pool = $notDone !== [] ? $notDone : $eligible;
            if ($pool === []) {
                return null;
            }

            $scenario = $pool[$seed % count($pool)];
        }

        return [
            'kind' => 'scenario',
            'id' => $scenario['id'],
            'emoji' => $scenario['emoji'],
            'title' => $scenario['title'],
            'tagline' => $scenario['tagline'] ?? '',
            'mission' => $scenario['mission'],
            'done' => isset($done[$scenario['id']]),
        ];
    }

    /**
     * A catalog entry by id, or null if the id is absent/unknown. Lets a mapped
     * theme pin a specific scene or set, and degrade quietly if that id is
     * later retired.
     *
     * @param  array<int, array{id: string}>  $catalog
     */
    private static function byId(array $catalog, ?string $id): ?array
    {
        if ($id === null) {
            return null;
        }

        foreach ($catalog as $entry) {
            if ($entry['id'] === $id) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Catalog entries whose level is at or below the focus level. If none
     * qualify (a learner below the lowest asset - not possible today, but
     * cheap to guard), the whole catalog is returned so a step still appears.
     *
     * @param  array<int, array{level?: string}>  $items
     * @return array<int, array>
     */
    private static function atOrBelow(array $items, string $focusLevel): array
    {
        $cap = self::levelIndex($focusLevel);
        $eligible = array_values(array_filter(
            $items,
            fn (array $i) => self::levelIndex($i['level'] ?? 'A1') <= $cap,
        ));

        return $eligible !== [] ? $eligible : $items;
    }

    /**
     * Deterministic pick from a pool: prefer entries not in `$done`, and rotate
     * across days via `$seed` so an all-cleared pool still varies.
     *
     * @param  array<int, array{id: string}>  $items
     * @param  array<string, mixed>  $done
     */
    private static function choose(array $items, array $done, int $seed): ?array
    {
        if ($items === []) {
            return null;
        }

        $notDone = array_values(array_filter($items, fn (array $i) => ! isset($done[$i['id']])));
        $pool = $notDone !== [] ? $notDone : $items;

        return $pool[$seed % count($pool)];
    }
}
