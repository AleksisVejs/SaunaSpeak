<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Kuuntelu: whole conversations, played back at natural speed.
 *
 * The rest of the course is sentence-shaped - one line, one card, one voice.
 * That's how items are memorized, but it isn't how Finnish arrives at you:
 * real speech is two people, back to back, with no pause to translate in.
 * These scenes are the extensive-listening half of the method - volume of
 * connected speech, comprehension first, transcript only when you ask.
 *
 * Content lives in database/listening/*.json (tracked, reviewable in a diff);
 * audio is pre-generated per line by `php artisan listening:audio` with the
 * male and female Finnish voices so a two-speaker dialogue actually sounds
 * like two speakers.
 */
class Listening
{
    /**
     * Parsed scene JSON, keyed by id. Safe to cache: these files are content,
     * fixed for the life of the process.
     *
     * Audio URLs are deliberately NOT part of this - they're resolved on read
     * (see clips()). Baking them in here would mean a take approved in the
     * recording studio never reached the learner in a process that had already
     * read the scene once.
     */
    private static ?array $raw = null;

    /** @return array<string, array> id → scene JSON (no audio) */
    private static function raw(): array
    {
        if (self::$raw !== null) {
            return self::$raw;
        }

        $scenes = [];

        foreach (File::glob(database_path('listening').'/*.json') as $path) {
            $data = json_decode(File::get($path), true);
            if (! is_array($data) || ! isset($data['lines'])) {
                continue;
            }

            // Id comes from the filename ("01-kahvilassa.json" → "kahvilassa"):
            // the numeric prefix orders the catalog without leaking into URLs.
            $id = preg_replace('/^\d+-/', '', pathinfo($path, PATHINFO_FILENAME));
            $data['id'] = $id;
            $scenes[$id] = $data;
        }

        return self::$raw = $scenes;
    }

    /**
     * Every line's current clip, as base name → URL. A few directory scans
     * rather than a glob per line, and never cached: approving a recording is
     * a file move, so this map is the freshest source of truth there is.
     *
     * Last writer wins, so the order IS the priority: edge-tts, then any
     * ElevenLabs take, then any human recording. ElevenLabs coverage is
     * partial by design (credits), so a scene voiced half by each is normal.
     *
     * @return array<string, string>
     */
    private static function clips(): array
    {
        $map = [];

        foreach (File::glob(public_path('audio/listening/listening-*.mp3')) as $path) {
            $map[pathinfo($path, PATHINFO_FILENAME)] = '/audio/listening/'.basename($path);
        }

        foreach (File::glob(public_path('audio/eleven/listening-*.mp3')) as $path) {
            $map[pathinfo($path, PATHINFO_FILENAME)] = '/audio/eleven/'.basename($path);
        }

        foreach (File::glob(public_path('audio/human/listening-*.*')) as $path) {
            $map[pathinfo($path, PATHINFO_FILENAME)] = '/audio/human/'.basename($path);
        }

        return $map;
    }

    /** Every scene, in filename order (01-, 02-, ...), with current audio. */
    public static function all(): array
    {
        $clips = self::clips();

        return array_values(array_map(
            fn (array $scene) => self::withAudio($scene, $clips),
            self::raw(),
        ));
    }

    public static function find(?string $id): ?array
    {
        $scene = $id === null ? null : (self::raw()[$id] ?? null);

        return $scene === null ? null : self::withAudio($scene, self::clips());
    }

    /** @param  array<string, string>  $clips */
    private static function withAudio(array $scene, array $clips): array
    {
        $scene['lines'] = array_map(
            fn (array $line, int $i) => $line + ['audio_url' => $clips[self::baseName($scene['id'], $i)] ?? null],
            $scene['lines'],
            array_keys($scene['lines']),
        );

        return $scene;
    }

    /** Catalog entries without the lines - enough to render the index. */
    public static function index(): array
    {
        return array_map(fn (array $s) => [
            'id' => $s['id'],
            'emoji' => $s['emoji'] ?? '🎧',
            'title' => $s['title'],
            'tagline' => $s['tagline'] ?? '',
            'level' => $s['level'] ?? 'A1',
            'lines_count' => count($s['lines']),
        ], self::all());
    }

    /**
     * Where one line's clip lives right now. A human recording always wins
     * over the generated one.
     */
    public static function audioUrl(string $id, int $index): ?string
    {
        return self::clips()[self::baseName($id, $index)] ?? null;
    }

    /**
     * The filename every copy of one line shares: the TTS clip, a take waiting
     * for review, and the approved human take. The recording studio globs for
     * these, so the scheme lives here rather than being spelled out in three
     * places.
     */
    public static function baseName(string $id, int $index): string
    {
        return "listening-{$id}-{$index}";
    }

    /** Split a base name back into [scene id, line index], or null. */
    public static function parseBaseName(string $base): ?array
    {
        return preg_match('/^listening-(.+)-(\d+)$/', $base, $m) === 1
            ? [$m[1], (int) $m[2]]
            : null;
    }

    /** Is this a real line? Guards uploads against invented scene/index pairs. */
    public static function hasLine(string $id, int $index): bool
    {
        return isset(self::find($id)['lines'][$index]);
    }
}
