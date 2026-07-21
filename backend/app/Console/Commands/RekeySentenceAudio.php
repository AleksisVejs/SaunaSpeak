<?php

namespace App\Console\Commands;

use App\Models\Sentence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Renames sentence clips from the old id-keyed names (sentence-545.mp3) to the
 * text-keyed ones (sentence-kahvi-ja-pulla-kiitos-1a2b3c.mp3).
 *
 *   php artisan audio:rekey --dry-run              # show the renames, touch nothing
 *   php artisan audio:rekey                        # every layer (run this LOCALLY)
 *   php artisan audio:rekey --only=human,pending   # native takes only (run this on the SERVER)
 *
 * WHICH LAYERS TO REKEY WHERE MATTERS.
 *
 * The edge-tts and ElevenLabs clips are committed, so they arrive on the
 * server already renamed by the deploy - and production's ids do NOT agree
 * with the names those files were generated under, so rekeying them there
 * would rename each clip after whatever sentence happens to hold its old id
 * and cement the mismatch this whole change exists to remove. On the server,
 * pass --only=human,pending: those live only on that machine, were recorded
 * against that machine's ids, and are the two layers git cannot carry.
 */
class RekeySentenceAudio extends Command
{
    protected $signature = 'audio:rekey
        {--only= : comma-separated: audio,eleven,human,pending (default: all)}
        {--dry-run : list the renames and change nothing}';

    protected $description = 'Rename sentence clips from id-keyed to text-keyed filenames';

    /** Layer → directory, relative to public/. */
    private const LAYERS = [
        'audio' => 'audio',
        'eleven' => 'audio/eleven',
        'human' => 'audio/human',
        'pending' => 'audio/pending',
    ];

    public function handle(): int
    {
        $layers = $this->layers();
        if ($layers === null) {
            return self::FAILURE;
        }

        $sentences = Sentence::all(['id', 'finnish_text']);
        $dry = $this->option('dry-run');

        $renamed = 0;
        $skipped = 0;
        $collisions = 0;

        foreach ($layers as $layer) {
            $dir = public_path(self::LAYERS[$layer]);

            if (! File::isDirectory($dir)) {
                $this->line("  {$layer}: no directory, skipping.");

                continue;
            }

            $handled = [];

            foreach ($sentences as $sentence) {
                $new = $sentence->audioBase();

                foreach (File::glob("{$dir}/sentence-{$sentence->id}.*") as $old) {
                    $handled[$old] = true;
                    $target = $dir.'/'.$new.'.'.pathinfo($old, PATHINFO_EXTENSION);

                    if (File::exists($target)) {
                        // Already rekeyed on a previous run, or two ids claim
                        // one text. Never overwrite - the operator decides.
                        $collisions++;
                        $this->warn("  ! {$layer}: ".basename($target).' already exists, left '.basename($old).' alone');

                        continue;
                    }

                    if ($dry) {
                        $this->line("  {$layer}: ".basename($old).' → '.basename($target));
                    } else {
                        File::move($old, $target);
                    }

                    $renamed++;
                }
            }

            // Anything still id-named that no row claimed has nothing to be
            // named after - a clip for a sentence that has since been deleted.
            $orphans = array_filter(
                File::glob("{$dir}/sentence-*.*"),
                fn (string $p) => ! isset($handled[$p])
                    && preg_match('/^sentence-\d+$/', pathinfo($p, PATHINFO_FILENAME)) === 1,
            );

            foreach ($orphans as $orphan) {
                $skipped++;
                $this->warn("  ? {$layer}: ".basename($orphan).' matches no sentence - left in place');
            }
        }

        $this->newLine();
        $this->line(($dry ? 'Would rename ' : 'Renamed ').$renamed.' clip(s)'
            .($collisions ? ", {$collisions} skipped (target exists)" : '')
            .($skipped ? ", {$skipped} orphaned" : '').'.');

        if (! $dry && $renamed > 0) {
            $this->newLine();
            $this->line('Now run <info>php artisan audio:generate</info> to repoint audio_url at the new names.');
        }

        return self::SUCCESS;
    }

    /** @return array<int, string>|null */
    private function layers(): ?array
    {
        $only = $this->option('only');
        if ($only === null) {
            return array_keys(self::LAYERS);
        }

        $layers = array_map('trim', explode(',', $only));
        $bad = array_diff($layers, array_keys(self::LAYERS));

        if ($bad !== []) {
            $this->error('Unknown --only value: '.implode(', ', $bad)
                .'. Pick from: '.implode(', ', array_keys(self::LAYERS)));

            return null;
        }

        return $layers;
    }
}
