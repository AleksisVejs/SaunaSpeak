<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProgress extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_LEARNING = 'learning';
    public const STATUS_REVIEW = 'review';
    public const STATUS_MASTERED = 'mastered';

    protected $table = 'user_progress';

    protected $fillable = ['user_id', 'sentence_id', 'status', 'ease', 'next_review_at'];

    protected function casts(): array
    {
        return [
            'next_review_at' => 'datetime',
        ];
    }

    public function sentence(): BelongsTo
    {
        return $this->belongsTo(Sentence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
