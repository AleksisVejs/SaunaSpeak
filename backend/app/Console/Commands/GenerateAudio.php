<?php

namespace App\Console\Commands;

use App\Models\Sentence;
use App\Support\Tts;
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

    /** Resolved once: null when edge-tts isn't available on this machine. */
    private ?string $bin = null;

    public function handle(): int
    {
        // edge-tts is only needed to SYNTHESIZE missing clips. Linking clips
        // that already exist (e.g. shipped via git to a cPanel host) must
        // work without it, so probe lazily instead of hard-failing here.
        $candidate = config('services.tts.bin', 'edge-tts');
        try {
            $probe = Process::run([$candidate, '--help']);
            $this->bin = $probe->successful() ? $candidate : null;
        } catch (\Throwable) {
            $this->bin = null;
        }

        if ($this->bin === null) {
            $this->warn('edge-tts not found (set EDGE_TTS_BIN or `pip install edge-tts`).');
            $this->line('Existing MP3s will still be linked; missing ones will be skipped.');
        }

        $dir = public_path('audio');
        File::ensureDirectoryExists($dir);

        $voice = $this->option('voice');
        $rate = $this->option('rate');
        $generated = 0;
        $failed = 0;

        foreach (Sentence::all() as $sentence) {
            // A human recording (from /record) always wins over TTS - link it
            // and move on, even under --force.
            if ($human = $this->humanUrl("sentence-{$sentence->id}")) {
                if ($sentence->audio_url !== $human) {
                    $sentence->update(['audio_url' => $human]);
                }

                continue;
            }

            $file = "{$dir}/sentence-{$sentence->id}.mp3";
            $url = "/audio/sentence-{$sentence->id}.mp3";

            if (File::exists($file) && ! $this->option('force')) {
                if ($sentence->audio_url !== $url) {
                    $sentence->update(['audio_url' => $url]);
                }
                continue;
            }

            if ($this->bin === null) {
                $failed++;
                $this->warn("✗ {$sentence->finnish_text} - no edge-tts to synthesize with");

                continue;
            }

            $result = Process::run([
                $this->bin,
                '--voice', $voice,
                '--rate', $rate,
                '--text', Tts::respell($sentence->finnish_text),
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
            $base = Str::slug($word) . '-' . substr(md5($word), 0, 6);

            // A human recording always wins over TTS in the manifest.
            if ($human = $this->humanUrl("words/{$base}")) {
                $manifest[$word] = $human;

                continue;
            }

            $name = $base . '.mp3';
            $file = "{$dir}/{$name}";
            $url = "/audio/words/{$name}";

            if (! File::exists($file) || $this->option('force')) {
                if ($this->bin === null) {
                    $failed++;
                    $this->warn("✗ word '{$word}' - no edge-tts to synthesize with");

                    continue;
                }

                $result = Process::run([
                    $this->bin,
                    '--voice', $voice,
                    '--rate', $rate,
                    '--text', Tts::respell($word),
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

    /** First human take for a base name ("sentence-12", "words/moi-abc123"), as a URL. */
    private function humanUrl(string $base): ?string
    {
        $match = File::glob(public_path("audio/human/{$base}.*"))[0] ?? null;

        return $match ? '/audio/human/'.(str_contains($base, '/') ? 'words/' : '').basename($match) : null;
    }
}
