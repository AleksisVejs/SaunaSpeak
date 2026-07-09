<?php

namespace App\Console\Commands;

use Database\Seeders\ImageSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Downloads the OpenMoji illustrations (CC BY-SA 4.0) referenced by
 * ImageSeeder::MAP into public/images/, then links them onto sentences.
 * Idempotent: existing files are kept unless --force is passed.
 */
class FetchImages extends Command
{
    protected $signature = 'images:fetch {--force : re-download files that already exist}';

    protected $description = 'Download OpenMoji SVG illustrations for sentences and link them (dual coding)';

    private const BASE = 'https://raw.githubusercontent.com/hfg-gmuend/openmoji/master/color/svg';

    public function handle(): int
    {
        $dir = public_path('images');
        File::ensureDirectoryExists($dir);

        $downloaded = 0;
        $failed = 0;

        foreach (array_unique(ImageSeeder::MAP) as $hex) {
            $file = "{$dir}/{$hex}.svg";

            if (File::exists($file) && ! $this->option('force')) {
                continue;
            }

            try {
                $res = Http::timeout(20)->get(self::BASE."/{$hex}.svg");

                if ($res->successful() && str_contains($res->body(), '<svg')) {
                    File::put($file, $res->body());
                    $downloaded++;
                    $this->info("✓ {$hex}.svg");
                } else {
                    $failed++;
                    $this->warn("✗ {$hex}.svg — HTTP {$res->status()}");
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("✗ {$hex}.svg — {$e->getMessage()}");
            }
        }

        // Link whatever is on disk onto the sentences.
        (new ImageSeeder)->run();
        $linked = \App\Models\Sentence::whereNotNull('image_url')->count();

        $this->line("Done: {$downloaded} downloaded, {$failed} failed, {$linked} sentences linked.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
