<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\File;

/**
 * public/audio/eleven/ holds hundreds of REAL committed clips now, and audio
 * resolves human > eleven > edge-tts. Any test that asserts the edge-tts tier
 * (or generates its own eleven clips) has to run against an empty eleven dir,
 * or the live course audio leaks in and breaks the assertion.
 *
 * So move the real dir aside for the duration of a test and move it back
 * after - never delete it. A blanket delete here would wipe the generated
 * course audio the instant anyone ran `php artisan test`.
 */
trait StashesElevenAudio
{
    private ?string $elevenStashPath = null;

    protected function stashElevenClips(): void
    {
        $live = public_path('audio/eleven');
        $this->elevenStashPath = public_path('audio/eleven__live_backup');

        if (File::isDirectory($live)) {
            File::deleteDirectory($this->elevenStashPath);
            File::moveDirectory($live, $this->elevenStashPath);
        }
    }

    protected function restoreElevenClips(): void
    {
        // Drop whatever the test generated, then give the real clips back.
        File::deleteDirectory(public_path('audio/eleven'));

        if ($this->elevenStashPath !== null && File::isDirectory($this->elevenStashPath)) {
            File::moveDirectory($this->elevenStashPath, public_path('audio/eleven'));
        }
    }
}
