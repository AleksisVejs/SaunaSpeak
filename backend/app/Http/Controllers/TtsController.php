<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * On-demand TTS for dynamic text (Sauna Chat replies) with the same male
 * Finnish neural voice used for all lesson audio (fi-FI-HarriNeural via
 * edge-tts). Clips are cached by content hash, so each unique reply is
 * synthesized once. Returns 503 where edge-tts isn't installed (e.g. shared
 * hosting) — the frontend then falls back to browser speech.
 */
class TtsController extends Controller
{
    public function speak(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:300'],
        ]);

        // Strip emoji and pictographs — TTS engines read them aloud.
        $text = trim(preg_replace(
            '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}\x{FE0F}\x{200D}]/u',
            '',
            $data['text'],
        ));

        if ($text === '') {
            return response()->json(['url' => null]);
        }

        $hash = md5($text);
        $file = public_path("audio/tts/{$hash}.mp3");
        $url = "/audio/tts/{$hash}.mp3";

        if (! File::exists($file)) {
            File::ensureDirectoryExists(dirname($file));

            try {
                $result = Process::timeout(20)->run([
                    config('services.tts.bin', 'edge-tts'),
                    '--voice', 'fi-FI-HarriNeural',
                    '--text', $text,
                    '--write-media', $file,
                ]);

                if (! $result->successful() || ! File::exists($file)) {
                    return response()->json(['url' => null], 503);
                }
            } catch (\Throwable) {
                return response()->json(['url' => null], 503);
            }
        }

        return response()->json(['url' => $url]);
    }
}
