<?php

namespace App\Console\Commands;

use App\Support\MinimalPairs;
use App\Support\Tts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Generates the Kuulo vowel-drill audio with the same voice as the lessons.
 *
 *   pip install edge-tts     # once
 *   php artisan pairs:audio
 *
 * These clips deliberately do NOT reuse the course's per-word audio: that
 * manifest is rebuilt from sentence glosses on every audio:generate run, and
 * a drill word that stops appearing in a lesson would silently lose its clip.
 * Own directory, own lifecycle.
 *
 * Filenames carry a hash because Str::slug flattens ä to a - sää and saa would
 * otherwise overwrite each other, which is precisely the distinction the drill
 * is testing.
 */
class GeneratePairAudio extends Command
{
    protected $signature = 'pairs:audio
        {--voice=fi-FI-HarriNeural : edge-tts voice (match the lesson audio)}
        {--rate=-10% : speech rate; slightly slow, these are single words heard once}
        {--force : regenerate clips that already exist}';

    protected $description = 'Generate MP3 audio for the Kuulo vowel-contrast drills (edge-tts)';

    public function handle(): int
    {
        $bin = config('services.tts.bin', 'edge-tts');

        try {
            if (! Process::run([$bin, '--help'])->successful()) {
                throw new \RuntimeException('probe failed');
            }
        } catch (\Throwable) {
            $this->error('edge-tts not found (set EDGE_TTS_BIN or `pip install edge-tts`).');

            return self::FAILURE;
        }

        $dir = public_path('audio/pairs');
        File::ensureDirectoryExists($dir);

        $voice = $this->option('voice');
        $rate = $this->option('rate');
        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach (MinimalPairs::words() as $word) {
            $file = $dir.'/'.MinimalPairs::wordBase($word).'.mp3';

            if (File::exists($file) && ! $this->option('force')) {
                $skipped++;

                continue;
            }

            $result = Process::run([
                $bin,
                '--voice', $voice,
                // "--rate", "-10%" as two tokens makes argparse read the value
                // as a flag and abort. One token survives a negative rate,
                // which the other audio commands never needed (they default
                // to +0%).
                '--rate='.$rate,
                '--text', Tts::respell($word),
                '--write-media', $file,
            ]);

            if ($result->successful() && File::exists($file)) {
                $generated++;
                $this->info("✓ {$word}");
            } else {
                $failed++;
                $this->warn("✗ {$word} - {$result->errorOutput()}");
            }
        }

        $this->line("Done: {$generated} generated, {$skipped} already present, {$failed} failed.");

        if (! MinimalPairs::allVerified()) {
            $this->warn('Note: some sets are still marked "verified": false - a native speaker should check the words and glosses before this ships.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
