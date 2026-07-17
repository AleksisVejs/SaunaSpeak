<?php

namespace App\Console\Commands;

use App\Support\Listening;
use App\Support\Tts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Pre-generates the Kuuntelu scene audio: one MP3 per line, voiced by the
 * speaker's own neural voice (fi-FI-HarriNeural / fi-FI-NooraNeural) so a
 * two-person dialogue actually sounds like two people.
 *
 *   pip install edge-tts     # once
 *   php artisan listening:audio
 *
 * Files land in public/audio/listening/listening-{scene}-{index}.mp3, which
 * is exactly where App\Support\Listening looks for them. A human recording at
 * public/audio/human/listening-{scene}-{index}.* always wins over TTS - the
 * same override the sentence audio uses, so native takes can replace these
 * scene by scene without touching any code.
 */
class GenerateListeningAudio extends Command
{
    protected $signature = 'listening:audio
        {--scene= : only this scene id (default: all)}
        {--rate=+0% : speech rate (the page has its own slow toggle)}
        {--force : regenerate clips that already exist}';

    protected $description = 'Generate two-voice MP3 audio for the listening scenes (edge-tts)';

    private const VOICES = [
        'male' => 'fi-FI-HarriNeural',
        'female' => 'fi-FI-NooraNeural',
    ];

    public function handle(): int
    {
        $bin = config('services.tts.bin', 'edge-tts');
        try {
            $probe = Process::run([$bin, '--help']);
            if (! $probe->successful()) {
                throw new \RuntimeException('probe failed');
            }
        } catch (\Throwable) {
            $this->error('edge-tts not found (set EDGE_TTS_BIN or `pip install edge-tts`).');

            return self::FAILURE;
        }

        $dir = public_path('audio/listening');
        File::ensureDirectoryExists($dir);

        $only = $this->option('scene');
        $rate = $this->option('rate');
        $generated = 0;
        $failed = 0;
        $skipped = 0;

        foreach (Listening::all() as $scene) {
            if ($only !== null && $scene['id'] !== $only) {
                continue;
            }

            $this->line("<info>{$scene['title']}</info> ({$scene['id']})");

            foreach ($scene['lines'] as $i => $line) {
                $speaker = $scene['speakers'][$line['who']] ?? null;
                $voice = self::VOICES[$speaker['voice'] ?? 'male'] ?? self::VOICES['male'];

                $file = "{$dir}/listening-{$scene['id']}-{$i}.mp3";

                if (File::exists($file) && ! $this->option('force')) {
                    $skipped++;

                    continue;
                }

                // SystemRoot/SystemDrive must be passed explicitly on Windows -
                // without them edge-tts's asyncio stack breaks (see TtsController).
                $result = Process::timeout(30)
                    ->env([
                        'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
                        'SYSTEMROOT' => getenv('SYSTEMROOT') ?: 'C:\\Windows',
                        'SystemDrive' => getenv('SystemDrive') ?: 'C:',
                    ])
                    ->run([
                        $bin,
                        '--voice', $voice,
                        '--rate', $rate,
                        '--text', Tts::respell($line['fi']),
                        '--write-media', $file,
                    ]);

                if ($result->successful() && File::exists($file)) {
                    $generated++;
                    $this->line("  ✓ {$line['who']}: {$line['fi']}");
                } else {
                    $failed++;
                    $this->warn("  ✗ {$line['fi']} - {$result->errorOutput()}");
                }
            }
        }

        $this->line("Done: {$generated} generated, {$skipped} already present, {$failed} failed.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
