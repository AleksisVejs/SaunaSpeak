<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * ElevenLabs text-to-speech: the premium voice tier.
 *
 * Deliberately NOT an all-or-nothing swap. Credits are bought in blocks and
 * the corpus is ~1700 clips, so this is designed to be mixed: whatever gets
 * voiced here plays here, everything else keeps its edge-tts clip, and a human
 * recording still beats both. Partial coverage is the normal state, not a
 * broken one.
 *
 * Billing is per CHARACTER, so the caller can ask what a run would cost
 * (charactersFor) and what's left in the plan (remaining) before spending
 * anything - see `php artisan audio:eleven --dry-run`.
 */
class ElevenLabs
{
    private const BASE = 'https://api.elevenlabs.io/v1';

    /** Why the last call failed, for commands to report. */
    public static ?string $lastError = null;

    public static function available(): bool
    {
        return (bool) config('services.elevenlabs.key');
    }

    /**
     * The voice for a speaker, or null when it isn't configured.
     * No defaults on purpose: a stock English voice reading Finnish sounds
     * worse than the native fi-FI edge-tts voice it would replace.
     */
    public static function voiceId(string $voice = 'male'): ?string
    {
        return config('services.elevenlabs.voice_'.($voice === 'female' ? 'female' : 'male')) ?: null;
    }

    /** What this text will cost, in billed characters. */
    public static function charactersFor(string $text): int
    {
        return mb_strlen($text);
    }

    /**
     * Characters left on the plan right now, or null if it can't be read.
     *
     * @return array{used: int, limit: int, left: int}|null
     */
    public static function quota(): ?array
    {
        if (! self::available()) {
            return null;
        }

        try {
            $response = Http::withHeaders(['xi-api-key' => config('services.elevenlabs.key')])
                ->timeout(15)
                ->get(self::BASE.'/user/subscription');

            if (! $response->successful()) {
                self::$lastError = 'quota check failed: HTTP '.$response->status();

                return null;
            }

            $used = (int) $response->json('character_count');
            $limit = (int) $response->json('character_limit');

            return ['used' => $used, 'limit' => $limit, 'left' => max(0, $limit - $used)];
        } catch (\Throwable $e) {
            self::$lastError = 'quota check failed: '.$e->getMessage();

            return null;
        }
    }

    /**
     * Synthesize one clip. Returns raw MP3 bytes, or null on failure
     * (reason in self::$lastError).
     */
    public static function synthesize(string $text, string $voiceId): ?string
    {
        self::$lastError = null;

        try {
            $response = Http::withHeaders([
                'xi-api-key' => config('services.elevenlabs.key'),
                'Accept' => 'audio/mpeg',
            ])
                ->timeout(60)
                ->post(self::BASE.'/text-to-speech/'.$voiceId, [
                    'text' => $text,
                    'model_id' => config('services.elevenlabs.model', 'eleven_multilingual_v2'),
                    'voice_settings' => [
                        // Steady over expressive: these are teaching clips that
                        // get replayed dozens of times, and a sentence that
                        // reads differently on each generation makes the course
                        // sound inconsistent.
                        'stability' => 0.5,
                        'similarity_boost' => 0.75,
                    ],
                ]);

            if (! $response->successful()) {
                // 401 = bad key, 422 = bad voice/model. Both are worth saying
                // out loud rather than silently leaving the clip on edge-tts.
                self::$lastError = 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 200);

                return null;
            }

            $body = $response->body();

            // A JSON body here means an error dressed as a 200.
            if ($body === '' || str_starts_with(ltrim($body), '{')) {
                self::$lastError = 'unexpected response: '.mb_substr($body, 0, 200);

                return null;
            }

            return $body;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();

            return null;
        }
    }
}
