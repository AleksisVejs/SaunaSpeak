<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sentence extends Model
{
    protected $fillable = [
        'lesson_id', 'finnish_text', 'written_text', 'english_text',
        'word_glosses', 'speaker', 'context_text', 'audio_url', 'image_url',
    ];

    protected $casts = ['word_glosses' => 'array'];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
