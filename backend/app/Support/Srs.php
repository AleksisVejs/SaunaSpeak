<?php

namespace App\Support;

/**
 * The shared flashcard ladder (word bank + chat mistakes): new → learning →
 * review → mastered with expanding intervals; "again" lapses back to
 * learning, due immediately; "easy" skips a stage. Sentence progress in
 * Sauna Sessions has its own richer schedule (ease factors) and stays in
 * SessionController.
 */
class Srs
{
    /**
     * @return array{0: string, 1: int} [next status, interval in days]
     */
    public static function gradeStep(string $current, string $grade): array
    {
        return match ($grade) {
            'again' => ['learning', 0],
            'easy' => match ($current) {
                'new' => ['review', 4],
                'learning' => ['mastered', 15],
                'review' => ['mastered', 30],
                default => ['mastered', 60],
            },
            default => match ($current) {
                'new' => ['learning', 1],
                'learning' => ['review', 4],
                'review' => ['mastered', 15],
                default => ['mastered', 30],
            },
        };
    }
}
