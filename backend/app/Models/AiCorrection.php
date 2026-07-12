<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCorrection extends Model
{
    protected $fillable = [
        'hash', 'expected_sentence', 'user_sentence', 'corrected', 'explanation', 'hits',
    ];

    /** Stable cache key: case/punctuation variants of the same attempt share one entry. */
    public static function keyFor(string $expected, string $attempt): string
    {
        $normalize = fn (string $s) => preg_replace('/\s+/u', ' ', mb_strtolower(trim($s)));

        return sha1($normalize($expected).'|'.$normalize($attempt));
    }
}
