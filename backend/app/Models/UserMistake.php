<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A correction the learner received in Sauna Chat, kept as a flashcard.
 * Reviewing your own errors (error-driven retrieval practice) is worth more
 * than rereading them: the card front shows what you said, and you produce
 * the natural spoken form the character taught you.
 */
class UserMistake extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_LEARNING = 'learning';
    public const STATUS_REVIEW = 'review';
    public const STATUS_MASTERED = 'mastered';

    protected $fillable = ['user_id', 'attempt', 'corrected', 'hash', 'source', 'status', 'next_review_at', 'reviews'];

    protected function casts(): array
    {
        return ['next_review_at' => 'datetime'];
    }

    /** Stable dedup key: case/whitespace variants of one target sentence share a card. */
    public static function keyFor(string $corrected): string
    {
        return sha1(preg_replace('/\s+/u', ' ', mb_strtolower(trim($corrected))));
    }

    /** Fresh cards (never reviewed) or cards whose review time has arrived. */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('next_review_at')->orWhere('next_review_at', '<=', now());
        });
    }
}
