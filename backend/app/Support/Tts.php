<?php

namespace App\Support;

/**
 * Text massaging shared by every TTS path (pre-generated lesson audio and
 * on-demand chat TTS). Display text is never touched - only what the
 * synthesizer hears.
 */
class Tts
{
    /**
     * Respellings for clipped puhekieli forms the neural voices misread.
     * Keys are matched as standalone words, case-insensitively.
     *
     * "sul" is also SUL (Suomen Urheiluliitto), so fi-FI voices spell it out
     * as an initialism; "sull" produces the intended /sul/. Verified against
     * fi-FI-HarriNeural word timings - add here if another form regresses.
     */
    private const RESPELL = [
        'sul' => 'sull',
    ];

    public static function respell(string $text): string
    {
        foreach (self::RESPELL as $from => $to) {
            $text = preg_replace('/(?<![\p{L}\p{N}])'.preg_quote($from, '/').'(?![\p{L}\p{N}])/iu', $to, $text);
        }

        return $text;
    }
}
