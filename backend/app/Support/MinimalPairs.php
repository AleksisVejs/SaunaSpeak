<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Kuulo: front/back vowel discrimination drills (y/u, ä/a, ö/o).
 *
 * Three natives in the July 2026 r/Finland thread converged on the same
 * answer about what actually breaks comprehension: not vowel LENGTH, not
 * double consonants - whole dialects drop those and stay perfectly clear -
 * but substituting a different vowel. u for y, a for ä.
 *
 * That contrast is worth its own drill because Finnish vowel harmony runs on
 * it: get the stem vowel wrong and every suffix after it harmonises the wrong
 * way, so one slip corrupts the whole word rather than one syllable.
 *
 * The method is High Variability Phonetic Training (Logan, Lively & Pisoni
 * 1991): hear one word, choose which of the pair it was, get told immediately.
 * The "high variability" half means several different voices - which is why
 * audio resolution below prefers human recordings and keeps looking after it
 * finds one voice.
 *
 * Content is hand-written and NOT yet native-reviewed - see each set's
 * "verified" flag, and allVerified() for the guard the API uses.
 */
class MinimalPairs
{
    /** Parsed sets, keyed by id - built once per request. */
    private static ?array $sets = null;

    /** @return array<string, array> id → set JSON */
    private static function raw(): array
    {
        if (self::$sets !== null) {
            return self::$sets;
        }

        $sets = [];

        foreach (File::glob(database_path('pairs').'/*.json') as $path) {
            $data = json_decode(File::get($path), true);
            if (! is_array($data) || ! isset($data['pairs'])) {
                continue;
            }

            $data['id'] = preg_replace('/^\d+-/', '', pathinfo($path, PATHINFO_FILENAME));
            $sets[$data['id']] = $data;
        }

        return self::$sets = $sets;
    }

    /** Every set, in filename order, with audio resolved on each word. */
    public static function all(): array
    {
        $clips = self::clips();

        return array_values(array_map(
            fn (array $set) => self::withAudio($set, $clips),
            self::raw(),
        ));
    }

    public static function find(?string $id): ?array
    {
        $set = $id === null ? null : (self::raw()[$id] ?? null);

        return $set === null ? null : self::withAudio($set, self::clips());
    }

    /** Catalog entries without the pairs - enough to render the index. */
    public static function index(): array
    {
        return array_map(fn (array $s) => [
            'id' => $s['id'],
            'emoji' => $s['emoji'] ?? '👂',
            'title' => $s['title'],
            'contrast' => $s['contrast'] ?? [],
            'rule' => $s['rule'] ?? '',
            'summary' => $s['summary'] ?? '',
            'level' => $s['level'] ?? 'A1',
            'verified' => (bool) ($s['verified'] ?? false),
            'pairs_count' => count($s['pairs']),
        ], array_values(self::raw()));
    }

    /**
     * Every distinct word the drills say, normalized → original.
     * Both halves of every pair; this is what needs a recording.
     *
     * @return array<string, string>
     */
    public static function words(): array
    {
        $out = [];

        foreach (self::raw() as $set) {
            foreach ($set['pairs'] as $pair) {
                foreach (['a', 'b'] as $side) {
                    $word = $pair[$side];
                    $out[mb_strtolower($word)] ??= $word;
                }
            }
        }

        return $out;
    }

    /**
     * Every drill word with its current clip resolved - what the recording
     * studio queues and the admin panel counts. Same normalized keys as
     * words(); null means no clip at all yet (the drill stays silent).
     *
     * @return array<string, ?string> normalized word → url
     */
    public static function wordClips(): array
    {
        $clips = self::clips();
        $out = [];

        foreach (self::words() as $norm => $word) {
            $out[$norm] = $clips[self::wordBase($word)] ?? null;
        }

        return $out;
    }

    /**
     * Filesystem-safe, collision-proof name for a word's clip. The hash is
     * load-bearing, not decoration: Str::slug flattens ä to a, so sää and saa
     * would otherwise fight over one filename - which is the exact contrast
     * this drill exists to teach.
     */
    public static function wordBase(string $word): string
    {
        return Str::slug($word).'-'.substr(md5(mb_strtolower($word)), 0, 6);
    }

    /** Has a native reviewed every set? Gates the drill in the API. */
    public static function allVerified(): bool
    {
        foreach (self::raw() as $set) {
            if (! ($set['verified'] ?? false)) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, string>  $clips  base name → url */
    private static function withAudio(array $set, array $clips): array
    {
        $set['pairs'] = array_map(function (array $pair) use ($clips) {
            foreach (['a', 'b'] as $side) {
                $pair[$side.'_audio'] = $clips[self::wordBase($pair[$side])] ?? null;
            }

            return $pair;
        }, $set['pairs']);

        return $set;
    }

    /**
     * Clips on disk, base name → url. Last writer wins, so the order is the
     * priority: edge-tts → ElevenLabs → human.
     *
     * Never cached: approving a recording is a file move, and a stale map
     * would keep playing the robot after a human take landed.
     *
     * @return array<string, string>
     */
    private static function clips(): array
    {
        $map = [];

        foreach ([
            'audio/pairs' => '/audio/pairs/',
            'audio/eleven/pairs' => '/audio/eleven/pairs/',
        ] as $dir => $url) {
            foreach (File::glob(public_path($dir).'/*.mp3') as $path) {
                $map[pathinfo($path, PATHINFO_FILENAME)] = $url.basename($path);
            }
        }

        // Any extension: a studio take stays in the browser's native format
        // (webm/m4a) when ffmpeg isn't around to transcode it.
        foreach (File::glob(public_path('audio/human/pairs').'/*.*') as $path) {
            $map[pathinfo($path, PATHINFO_FILENAME)] = '/audio/human/pairs/'.basename($path);
        }

        return $map;
    }
}
