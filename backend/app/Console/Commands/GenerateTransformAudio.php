<?php

namespace App\Console\Commands;

use App\Support\Transforms;
use App\Support\Tts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Generates the Taivutus drill audio with the same male neural voice as the
 * lesson sentences.
 *
 *   pip install edge-tts     # once
 *   php artisan transforms:audio
 *
 * Clips are keyed by the sentence's TEXT, not by which drill slot it sits in:
 * "Mä otan kahvin" starts two different drills, and saying it twice would be
 * two identical files and two pointless takes for whoever records it later.
 *
 * Sentences the course already teaches are skipped entirely - Taivutus plays
 * the course's clip for those (see Transforms::audioIndex), so they inherit an
 * approved human recording automatically.
 */
class GenerateTransformAudio extends Command
{
    protected $signature = 'transforms:audio
        {--voice=fi-FI-HarriNeural : edge-tts voice (match the lesson audio)}
        {--rate=+0% : speech rate}
        {--force : regenerate clips that already exist}';

    protected $description = 'Generate MP3 audio for the Taivutus drill sentences (edge-tts)';

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

        $dir = public_path('audio/transforms');
        File::ensureDirectoryExists($dir);

        $phrases = Transforms::ownPhrases();
        $reused = count(Transforms::texts()) - count($phrases);

        $this->line(count($phrases).' phrases need their own clip; '
            ."{$reused} already covered by a course sentence (reused, not re-recorded).");

        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($phrases as $phrase) {
            $file = "{$dir}/{$phrase['base']}.mp3";

            if (File::exists($file) && ! $this->option('force')) {
                $skipped++;

                continue;
            }

            // SystemRoot/SystemDrive must be passed explicitly on Windows or
            // edge-tts's asyncio stack breaks (see TtsController).
            $result = Process::timeout(30)
                ->env([
                    'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
                    'SYSTEMROOT' => getenv('SYSTEMROOT') ?: 'C:\\Windows',
                    'SystemDrive' => getenv('SystemDrive') ?: 'C:',
                ])
                ->run([
                    $bin,
                    '--voice', $this->option('voice'),
                    '--rate', $this->option('rate'),
                    '--text', Tts::respell($phrase['text']),
                    '--write-media', $file,
                ]);

            if ($result->successful() && File::exists($file)) {
                $generated++;
                $this->info("✓ {$phrase['text']}");
            } else {
                $failed++;
                $this->warn("✗ {$phrase['text']} - {$result->errorOutput()}");
            }
        }

        $this->line("Done: {$generated} generated, {$skipped} already present, {$failed} failed.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
