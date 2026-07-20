<?php

namespace App\Console\Commands;

use App\Models\Sentence;
use App\Services\ElevenLabs;
use App\Support\Listening;
use App\Support\MinimalPairs;
use App\Support\Transforms;
use App\Support\Tts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Re-voices the course with ElevenLabs, as far as the credits go.
 *
 *   php artisan audio:eleven --dry-run        # what would it cost? spends nothing
 *   php artisan audio:eleven --limit=50       # spend a little
 *   php artisan audio:eleven --only=sentences # spend it where it's heard most
 *
 * Clips land in public/audio/eleven/, ALONGSIDE the edge-tts ones rather than
 * over them, which is what makes a half-finished run fine: every resolver
 * prefers human → eleven → edge-tts, so anything not reached here keeps
 * playing its Finnish neural clip and nothing goes silent.
 *
 * Billing is per character, so the run stops when the plan's credits run out
 * (or when --limit / --max-chars says so) instead of failing 900 times.
 */
class GenerateElevenAudio extends Command
{
    protected $signature = 'audio:eleven
        {--only= : comma-separated: sentences,words,listening,transforms,pairs (default: all)}
        {--limit= : stop after this many clips}
        {--max-chars= : stop before spending more than this many characters}
        {--dry-run : report what it would cost and generate nothing}
        {--force : re-voice clips that already have an ElevenLabs take}';

    protected $description = 'Voice the course with ElevenLabs, as far as the credits go (mixes with edge-tts)';

    private const KINDS = ['sentences', 'words', 'listening', 'transforms', 'pairs'];

    public function handle(): int
    {
        if (! ElevenLabs::available()) {
            $this->error('No ELEVENLABS_API_KEY set.');

            return self::FAILURE;
        }

        $kinds = $this->kinds();
        if ($kinds === null) {
            return self::FAILURE;
        }

        $jobs = $this->jobs($kinds);
        $todo = array_values(array_filter(
            $jobs,
            fn (array $j) => $this->option('force') || ! File::exists($j['file']),
        ));

        $chars = array_sum(array_map(fn (array $j) => ElevenLabs::charactersFor($j['text']), $todo));

        $this->line(count($jobs).' clips in scope, '.count($todo).' not yet voiced by ElevenLabs.');
        $this->line('Cost to voice them all: <info>'.number_format($chars).'</info> characters.');

        if ($quota = ElevenLabs::quota()) {
            $this->line('Your plan: '.number_format($quota['left']).' of '
                .number_format($quota['limit']).' characters left.');

            if ($quota['left'] < $chars) {
                $this->warn('Not enough credits for all of it - this run will voice what fits and stop.');
                $this->warn('The rest keeps its edge-tts clip (a native Finnish voice), so nothing goes silent.');
            }
        } else {
            $this->warn('Could not read your plan quota'.(ElevenLabs::$lastError ? ' ('.ElevenLabs::$lastError.')' : '').'.');
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->line('<comment>Dry run - nothing generated, nothing spent.</comment>');
            foreach (array_slice($todo, 0, 10) as $job) {
                $this->line(sprintf('  %-12s %s', "[{$job['kind']}]", $job['text']));
            }
            if (count($todo) > 10) {
                $this->line('  … and '.(count($todo) - 10).' more.');
            }

            return self::SUCCESS;
        }

        return $this->run_($todo, $quota['left'] ?? null);
    }

    /** @param  array<int, array>  $todo */
    private function run_(array $todo, ?int $creditsLeft): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $maxChars = $this->option('max-chars') !== null ? (int) $this->option('max-chars') : null;

        $spent = 0;
        $done = 0;
        $failed = 0;

        foreach ($todo as $job) {
            if ($limit !== null && $done >= $limit) {
                $this->line("Reached --limit={$limit}.");
                break;
            }

            $cost = ElevenLabs::charactersFor($job['text']);

            if ($maxChars !== null && $spent + $cost > $maxChars) {
                $this->line("Reached --max-chars={$maxChars}.");
                break;
            }

            if ($creditsLeft !== null && $spent + $cost > $creditsLeft) {
                $this->warn('Out of ElevenLabs credits - stopping here.');
                break;
            }

            $audio = ElevenLabs::synthesize(Tts::respell($job['text']), $job['voice_id']);

            if ($audio === null) {
                $failed++;
                $this->warn("✗ {$job['text']} - ".ElevenLabs::$lastError);

                // A bad key or voice fails identically on every remaining clip;
                // stop rather than hammer the API 900 times.
                if ($failed >= 3 && $done === 0) {
                    $this->error('Three failures and nothing generated - check the key, voice IDs and model. Stopping.');

                    return self::FAILURE;
                }

                continue;
            }

            File::ensureDirectoryExists(dirname($job['file']));
            File::put($job['file'], $audio);

            $spent += $cost;
            $done++;
            $this->info("✓ [{$job['kind']}] {$job['text']}");
        }

        $left = count($todo) - $done - $failed;
        $this->newLine();
        $this->line("Voiced {$done} clips for ".number_format($spent).' characters'
            .($failed ? ", {$failed} failed" : '').'.');

        if ($left > 0) {
            $this->line("{$left} still on edge-tts - run again with more credits to continue.");
        }

        // Sentence and word URLs live in the DB / manifest, so re-link them.
        if ($done > 0) {
            $this->newLine();
            $this->line('Re-linking audio so the new clips are the ones that play…');
            $this->call('audio:generate');
        }

        return $failed && $done === 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<int, string>|null */
    private function kinds(): ?array
    {
        $only = $this->option('only');
        if ($only === null) {
            return self::KINDS;
        }

        $kinds = array_map('trim', explode(',', $only));
        $bad = array_diff($kinds, self::KINDS);

        if ($bad !== []) {
            $this->error('Unknown --only value: '.implode(', ', $bad).'. Pick from: '.implode(', ', self::KINDS));

            return null;
        }

        return $kinds;
    }

    /**
     * Everything that could be voiced, in the order credits are best spent:
     * course sentences first (heard most, and the landing page's samples),
     * then conversations, drills, and finally the ~1000 single words.
     *
     * @param  array<int, string>  $kinds
     * @return array<int, array{kind: string, text: string, voice_id: string, file: string}>
     */
    private function jobs(array $kinds): array
    {
        $jobs = [];
        $dir = public_path('audio/eleven');

        $male = ElevenLabs::voiceId('male');
        $female = ElevenLabs::voiceId('female');

        if ($male === null) {
            $this->warn('ELEVENLABS_VOICE_MALE is not set - skipping everything spoken by the male voice.');
        }

        if (in_array('sentences', $kinds, true) && $male !== null) {
            $sentences = Sentence::join('lessons', 'lessons.id', '=', 'sentences.lesson_id')
                ->orderBy('lessons.order_index')
                ->orderBy('sentences.id')
                ->get(['sentences.id', 'sentences.finnish_text']);

            foreach ($sentences as $s) {
                $jobs[] = [
                    'kind' => 'sentence',
                    'text' => $s->finnish_text,
                    'voice_id' => $male,
                    'file' => "{$dir}/sentence-{$s->id}.mp3",
                ];
            }
        }

        if (in_array('listening', $kinds, true)) {
            foreach (Listening::all() as $scene) {
                foreach ($scene['lines'] as $i => $line) {
                    $voice = ($scene['speakers'][$line['who']]['voice'] ?? 'male') === 'female' ? $female : $male;
                    if ($voice === null) {
                        continue; // that voice isn't configured - leave it on edge-tts
                    }

                    $jobs[] = [
                        'kind' => 'listening',
                        'text' => $line['fi'],
                        'voice_id' => $voice,
                        'file' => "{$dir}/".Listening::baseName($scene['id'], $i).'.mp3',
                    ];
                }
            }
        }

        if (in_array('transforms', $kinds, true) && $male !== null) {
            foreach (Transforms::ownPhrases() as $phrase) {
                $jobs[] = [
                    'kind' => 'phrase',
                    'text' => $phrase['text'],
                    'voice_id' => $male,
                    'file' => "{$dir}/{$phrase['base']}.mp3",
                ];
            }
        }

        // Kuulo vowel drills. Worth ElevenLabs credits ahead of most things:
        // every other clip survives a slightly-off rendering, but a drill that
        // asks "y or u?" is worthless if the voice doesn't separate them.
        if (in_array('pairs', $kinds, true) && $male !== null) {
            foreach (MinimalPairs::words() as $word) {
                $jobs[] = [
                    'kind' => 'pair',
                    'text' => $word,
                    'voice_id' => $male,
                    'file' => "{$dir}/pairs/".MinimalPairs::wordBase($word).'.mp3',
                ];
            }
        }

        if (in_array('words', $kinds, true) && $male !== null) {
            $words = Sentence::all()
                ->flatMap(fn (Sentence $s) => array_keys($s->word_glosses ?? []))
                ->map(fn (string $w) => mb_strtolower($w))
                ->unique()
                ->sort()
                ->values();

            foreach ($words as $word) {
                // Same slug+hash scheme audio:generate uses, so the files pair up.
                $base = Str::slug($word).'-'.substr(md5($word), 0, 6);
                $jobs[] = [
                    'kind' => 'word',
                    'text' => $word,
                    'voice_id' => $male,
                    'file' => "{$dir}/words/{$base}.mp3",
                ];
            }
        }

        return $jobs;
    }
}
