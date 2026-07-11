<?php

namespace App\Console\Commands;

use App\Models\Sentence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Pre-generates native-quality Finnish audio for every sentence using the
 * free edge-tts CLI (Microsoft neural voices: fi-FI-HarriNeural,
 * fi-FI-NooraNeural, fi-FI-SelmaNeural). Install it once with:
 *
 *   pip install edge-tts
 *
 * Sentence MP3s are written to public/audio/ and audio_url is set on each
 * sentence. Per-word MP3s are written to public/audio/words/ with a
 * public/audio/words.json manifest (word -> url) that the frontend uses to
 * play native pronunciation for individual tapped words. The frontend falls
 * back to browser TTS for anything without a generated file.
 */
class GenerateAudio extends Command
{
    protected $signature = 'audio:generate
        {--voice=fi-FI-HarriNeural : edge-tts voice to use}
        {--rate=+0% : speech rate adjustment (the app player has its own speed control)}
        {--force : regenerate files that already exist}';

    protected $description = 'Generate MP3 audio for all sentences with a free Finnish neural voice (edge-tts)';

    public function handle(): int
    {
        $probe = Process::run('edge-tts --help');
        if (! $probe->successful()) {
            $this->error('edge-tts is not installed or not on PATH.');
            $this->line('Install it with:  pip install edge-tts');

            return self::FAILURE;
        }

        $dir = public_path('audio');
        File::ensureDirectoryExists($dir);

        $voice = $this->option('voice');
        $rate = $this->option('rate');
        $generated = 0;
        $failed = 0;

        foreach (Sentence::all() as $sentence) {
            $file = "{$dir}/sentence-{$sentence->id}.mp3";
            $url = "/audio/sentence-{$sentence->id}.mp3";

            if (File::exists($file) && ! $this->option('force')) {
                if ($sentence->audio_url !== $url) {
                    $sentence->update(['audio_url' => $url]);
                }
                continue;
            }

            $result = Process::run([
                'edge-tts',
                '--voice', $voice,
                '--rate', $rate,
                '--text', $sentence->finnish_text,
                '--write-media', $file,
            ]);

            if ($result->successful() && File::exists($file)) {
                $sentence->update(['audio_url' => $url]);
                $generated++;
                $this->info("✓ {$sentence->finnish_text}");
            } else {
                $failed++;
                $this->warn("✗ {$sentence->finnish_text} - {$result->errorOutput()}");
            }
        }

        [$wordsGenerated, $wordsFailed] = $this->generateWordAudio($voice, $rate);

        $this->line("Done: {$generated} sentences + {$wordsGenerated} words generated, "
            . ($failed + $wordsFailed) . ' failed.');

        return ($failed + $wordsFailed) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * One MP3 per unique glossed word, plus public/audio/words.json mapping
     * word → URL so the frontend can play native audio for tapped words.
     */
    private function generateWordAudio(string $voice, string $rate): array
    {
        $dir = public_path('audio/words');
        File::ensureDirectoryExists($dir);

        $words = Sentence::all()
            ->flatMap(fn (Sentence $s) => array_keys($s->word_glosses ?? []))
            ->map(fn (string $w) => mb_strtolower($w))
            ->unique()
            ->sort()
            ->values();

        $manifest = [];
        $generated = 0;
        $failed = 0;

        foreach ($words as $word) {
            // Slug + short hash: filesystem-safe and collision-proof (sää vs saa).
            $name = Str::slug($word) . '-' . substr(md5($word), 0, 6) . '.mp3';
            $file = "{$dir}/{$name}";
            $url = "/audio/words/{$name}";

            if (! File::exists($file) || $this->option('force')) {
                $result = Process::run([
                    'edge-tts',
                    '--voice', $voice,
                    '--rate', $rate,
                    '--text', $word,
                    '--write-media', $file,
                ]);

                if ($result->successful() && File::exists($file)) {
                    $generated++;
                } else {
                    $failed++;
                    $this->warn("✗ word '{$word}' - {$result->errorOutput()}");
                    continue;
                }
            }

            $manifest[$word] = $url;
        }

        File::put(
            public_path('audio/words.json'),
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        );

        return [$generated, $failed];
    }
}
