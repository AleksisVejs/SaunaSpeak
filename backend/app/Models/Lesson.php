<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $fillable = ['title', 'level', 'order_index', 'pattern_id'];

    public function sentences(): HasMany
    {
        return $this->hasMany(Sentence::class);
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }
}
