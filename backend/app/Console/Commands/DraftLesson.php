<?php

namespace App\Console\Commands;

use App\Models\Sentence;
use App\Services\Llm;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Content pipeline, step 1: AI-draft a lesson as JSON for HUMAN REVIEW.
 * Never writes to the database - review the draft (puhekieli quality is the
 * product), fix what's off, then `php artisan lesson:import <file>`.
 */
class DraftLesson extends Command
{
    protected $signature = 'lesson:draft {topic : e.g. "Ordering coffee"} {--level=A1} {--count=8 : sentences}';

    protected $description = 'Draft a puhekieli lesson as reviewable JSON (storage/app/lesson-drafts/)';

    public function handle(): int
    {
        if (! Llm::available()) {
            $this->error('No LLM configured (AI_API_KEY / OPENROUTER_API_KEY / GEMINI_API_KEY).');

            return self::FAILURE;
        }

        $topic = $this->argument('topic');
        $level = strtoupper($this->option('level'));
        $count = max(4, min(12, (int) $this->option('count')));

        // A few existing sentences anchor the register and difficulty.
        $examples = Sentence::inRandomOrder()->limit(5)->get(['finnish_text', 'english_text'])
            ->map(fn ($s) => "- \"{$s->finnish_text}\" = {$s->english_text}")
            ->implode("\n");

        $prompt = <<<PROMPT
Create one SaunaSpeak lesson: topic "{$topic}", CEFR level {$level}, exactly {$count} sentences.

SaunaSpeak teaches everyday SPOKEN Finnish (puhekieli) - the register Finns actually use. Style samples from the existing course:
{$examples}

Hard rules:
- "fi" is natural puhekieli: mä/sä/toi/tää pronoun reductions, -ko→-ks questions (onks, tuuks), oa→oo / ea→ee vowel changes (mä oon, mä meen), mennään-passive for "let's", NEVER textbook kirjakieli.
- "written" is the kirjakieli equivalent ONLY when it differs from "fi", else null.
- "glosses" maps EVERY word of "fi" (lowercased, punctuation stripped) to a short English gloss.
- Sentences must be short, high-frequency, immediately useful at {$level}; progress from easiest to hardest.
- The pattern is ONE grammar/usage point the sentences keep showing, explained in plain English with spoken forms.

Reply with ONLY this JSON (no markdown fences):
{"title":"<lesson title in English>","level":"{$level}","pattern":{"title":"<point, e.g. 'Asking with -ks: onks, saaks'>","summary":"<2-3 plain sentences>","examples":["<fi> = <en>","<fi> = <en>"]},"sentences":[{"fi":"...","en":"...","written":null,"glosses":{"word":"gloss"}}]}
PROMPT;

        $this->info("Drafting \"{$topic}\" ({$level}, {$count} sentences)…");

        $text = Llm::generate(
            'You are a Finnish curriculum writer specialized in colloquial spoken Finnish. Reply with only the requested JSON.',
            [['role' => 'user', 'content' => $prompt]],
            4000
        );

        if ($text === null) {
            $this->error('LLM call failed (status: '.var_export(Llm::$lastStatus, true).').');

            return self::FAILURE;
        }

        $text = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)));
        $draft = json_decode($text, true);

        if (! is_array($draft) || ! isset($draft['title'], $draft['pattern'], $draft['sentences'])) {
            $this->error("Could not parse the draft as lesson JSON. Raw output:\n".$text);

            return self::FAILURE;
        }

        File::ensureDirectoryExists(storage_path('app/lesson-drafts'));
        $path = storage_path('app/lesson-drafts/'.Str::slug($topic).'-'.now()->format('Ymd-His').'.json');
        File::put($path, json_encode($draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Draft written to: '.$path);
        $this->line('Review the Finnish carefully (puhekieli register!), edit in place, then:');
        $this->line('  php artisan lesson:import '.escapeshellarg($path));

        return self::SUCCESS;
    }
}
