<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWord extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_LEARNING = 'learning';
    public const STATUS_REVIEW = 'review';
    public const STATUS_MASTERED = 'mastered';

    protected $fillable = ['user_id', 'word', 'gloss', 'sentence_id', 'status', 'next_review_at', 'reviews'];

    protected function casts(): array
    {
        return ['next_review_at' => 'datetime'];
    }

    public function sentence(): BelongsTo
    {
        return $this->belongsTo(Sentence::class);
    }

    /** New words (never reviewed) or words whose review time has arrived. */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('next_review_at')->orWhere('next_review_at', '<=', now());
        });
    }
}
