<?php

namespace App\Support;

use App\Models\Lesson;
use App\Models\Pattern;
use App\Models\Sentence;
use Illuminate\Support\Facades\DB;

/**
 * Imports one reviewed lesson-draft array (from `lesson:draft`) into the
 * course. Shared by the lesson:import command (local authoring) and
 * JsonLessonSeeder (ships database/lessons/*.json to production).
 */
class LessonImporter
{
    /**
     * Insert the lesson at the END OF ITS LEVEL BLOCK, not at the end of the
     * course - appending an A1 lesson after the A2 block would split the
     * level and render a second "Level A1 begins" divider on the path.
     *
     * @return Lesson the created lesson
     */
    public function import(array $data): Lesson
    {
        return DB::transaction(function () use ($data) {
            $pattern = Pattern::create([
                'title' => $data['pattern']['title'],
                'summary' => $data['pattern']['summary'],
                'examples' => $data['pattern']['examples'],
                'order_index' => (int) Pattern::max('order_index') + 1,
            ]);

            // Last slot of this level; unseen levels append after everything.
            $slot = (int) (Lesson::where('level', $data['level'])->max('order_index')
                ?: Lesson::max('order_index'));

            Lesson::where('order_index', '>', $slot)->increment('order_index');

            $lesson = Lesson::create([
                'title' => $data['title'],
                'level' => $data['level'],
                'order_index' => $slot + 1,
                'pattern_id' => $pattern->id,
            ]);

            foreach ($data['sentences'] as $row) {
                $lesson->sentences()->create([
                    'finnish_text' => $row['fi'],
                    'english_text' => $row['en'],
                    'written_text' => $row['written'] ?? null,
                    'word_glosses' => $row['glosses'] ?? null,
                ]);
            }

            return $lesson;
        });
    }

    /** @return list<string> human-readable problems, empty when valid */
    public function validate(mixed $data): array
    {
        $problems = [];

        if (! is_array($data)) {
            return ['Not valid JSON.'];
        }
        foreach (['title', 'level', 'pattern', 'sentences'] as $key) {
            if (empty($data[$key])) {
                $problems[] = "Missing \"{$key}\".";
            }
        }
        if (isset($data['pattern']) && (empty($data['pattern']['title']) || empty($data['pattern']['summary']) || empty($data['pattern']['examples']))) {
            $problems[] = 'Pattern needs title, summary and examples.';
        }
        foreach ((array) ($data['sentences'] ?? []) as $i => $row) {
            if (empty($row['fi']) || empty($row['en'])) {
                $problems[] = "Sentence #{$i} needs both \"fi\" and \"en\".";
            }
            if (! empty($row['fi']) && Sentence::where('finnish_text', $row['fi'])->exists()) {
                $problems[] = "Sentence #{$i} (\"{$row['fi']}\") already exists in the course.";
            }
        }

        return $problems;
    }
}
