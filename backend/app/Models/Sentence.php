<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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

    /** The base name of every clip that speaks this sentence. */
    public function audioBase(): string
    {
        return self::audioBaseFor($this->finnish_text);
    }

    /**
     * Audio is keyed by TEXT, never by row id.
     *
     * Clips ship through git while ids are handed out by each database's
     * autoincrement, so the two drift the moment local and production import
     * lessons in a different order - and they did: a lesson seeded on the
     * server but not locally pushed every later id one block apart, which
     * silently pointed eight production sentences at the wrong recording and
     * left eight more with a URL to a file that was never generated. A name
     * derived from the words being spoken cannot drift from them, so a local
     * `migrate:fresh` (which renumbers everything) is now harmless.
     *
     * Same slug+hash scheme as the per-word clips: the slug keeps the file
     * recognizable by eye, the hash keeps it unique where the slug collides
     * (punctuation and the a/ä, o/ö pairs all flatten into the same slug).
     *
     * The "sentence-" prefix stays: pending takes, human takes and ElevenLabs
     * clips all share one directory with the listening and phrase clips, and
     * the review queues tell them apart by globbing that prefix.
     */
    public static function audioBaseFor(string $text): string
    {
        $slug = trim(Str::limit(Str::slug($text), 40, ''), '-');

        return 'sentence-'.($slug === '' ? '' : $slug.'-').substr(md5($text), 0, 6);
    }
}
