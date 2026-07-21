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
 * WHICH LAYERS TO REKEY WHERE MATTERS, because "what id is this?" has a
 * different answer on each machine.
 *
 *   audio/          committed, so the deploy delivers it already renamed.
 *   eleven/         gitignored (culling a clip on the server has to stick),
 *                   so the server's copy must be renamed IN PLACE - but its
 *                   files are named after the ids of the machine that
 *                   GENERATED them, which is not the server. Rekeying it by
 *                   the server's own ids would name a joining-words recording
 *                   after a don't-switch-to-english sentence. Use --manifest,
 *                   which carries the old-base → new-base map from the
 *                   machine whose ids those filenames actually came from.
 *   human/pending   server-only, and recorded against the server's own ids,
 *                   so those DO rekey correctly from its database.
 *
 * On the server:  audio:rekey --only=eleven --manifest=database/audio-rekey.json
 *                 audio:rekey --only=human,pending
 *
 * Skipping the eleven pass is safe, just not pretty: every sentence falls back
 * to its edge-tts clip, which the deploy renamed correctly. Wrong voice, right
 * words - never the other way round.
 */
class RekeySentenceAudio extends Command
{
    protected $signature = 'audio:rekey
        {--only= : comma-separated: audio,eleven,human,pending (default: all)}
        {--manifest= : apply a shipped old-base → new-base map instead of this database ids}
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

        $renames = $this->renames();
        if ($renames === null) {
            return self::FAILURE;
        }

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

            foreach ($renames as $oldBase => $new) {
                foreach (File::glob("{$dir}/{$oldBase}.*") as $old) {
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

    /**
     * old base name → new base name.
     *
     * From this database by default; from a shipped manifest when the files
     * being renamed were named by a DIFFERENT machine's ids (see the class
     * comment - that is the whole point of --manifest).
     *
     * @return array<string, string>|null
     */
    private function renames(): ?array
    {
        $path = $this->option('manifest');

        if ($path === null) {
            $map = [];
            foreach (Sentence::all(['id', 'finnish_text']) as $sentence) {
                $map["sentence-{$sentence->id}"] = $sentence->audioBase();
            }

            return $map;
        }

        $full = str_starts_with($path, '/') ? $path : base_path($path);

        if (! File::exists($full)) {
            $this->error("No manifest at {$full}.");

            return null;
        }

        $map = json_decode(File::get($full), true);

        if (! is_array($map) || $map === []) {
            $this->error("Manifest at {$full} is not a non-empty old→new object.");

            return null;
        }

        $this->line('Using manifest '.$path.' ('.count($map).' mappings).');

        return $map;
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
