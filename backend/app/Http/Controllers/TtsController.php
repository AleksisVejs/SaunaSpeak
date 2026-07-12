<?php

namespace App\Http\Controllers;

use App\Support\Tts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * On-demand TTS for dynamic text (Sauna Chat replies), cached by content hash
 * so each unique reply is synthesized once. Provider chain:
 *
 *   1. edge-tts (free, male fi-FI-HarriNeural - same voice as lesson audio);
 *      needs Python, so it works locally and on a VPS but not shared hosting.
 *   2. Google Cloud TTS (GOOGLE_TTS_API_KEY) - cPanel-friendly HTTP API.
 *      No male Finnish voice exists there, so the WaveNet voice is pitched
 *      down (services.tts.google_pitch) to sit closer to the lesson audio.
 *   3. Neither available → 503; the frontend stays silent by design (no
 *      browser-voice fallback).
 */
class TtsController extends Controller
{
    public function speak(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:300'],
        ]);

        // Strip emoji and pictographs - TTS engines read them aloud.
        $text = trim(preg_replace(
            '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}\x{FE0F}\x{200D}]/u',
            '',
            $data['text'],
        ));

        if ($text === '') {
            return response()->json(['url' => null]);
        }

        // Respell before hashing so fixed pronunciations get fresh cache slots.
        $text = Tts::respell($text);

        $hash = md5($text);
        $file = public_path("audio/tts/{$hash}.mp3");
        $url = "/audio/tts/{$hash}.mp3";

        if (! File::exists($file)) {
            File::ensureDirectoryExists(dirname($file));

            if (! $this->viaEdgeTts($text, $file) && ! $this->viaGoogle($text, $file)) {
                return response()->json(['url' => null], 503);
            }
        }

        return response()->json(['url' => $url]);
    }

    /** Free male neural voice via edge-tts; false where Python isn't available. */
    private function viaEdgeTts(string $text, string $file): bool
    {
        try {
            // SystemRoot must be passed explicitly: children of PHP's built-in
            // server can lose it, which breaks Winsock (10106) and asyncio.
            $result = Process::timeout(20)
                ->env([
                    'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
                    'SYSTEMROOT' => getenv('SYSTEMROOT') ?: 'C:\\Windows',
                ])
                ->run([
                    config('services.tts.bin', 'edge-tts'),
                    '--voice', 'fi-FI-HarriNeural',
                    '--text', $text,
                    '--write-media', $file,
                ]);

            return $result->successful() && File::exists($file);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Google Cloud TTS fallback (plain HTTP, works on shared hosting). */
    private function viaGoogle(string $text, string $file): bool
    {
        $key = config('services.tts.google_key');
        if (! $key) {
            return false;
        }

        try {
            $response = Http::timeout(15)->post(
                "https://texttospeech.googleapis.com/v1/text:synthesize?key={$key}",
                [
                    'input' => ['text' => $text],
                    'voice' => [
                        'languageCode' => 'fi-FI',
                        'name' => config('services.tts.google_voice', 'fi-FI-Wavenet-A'),
                    ],
                    'audioConfig' => [
                        'audioEncoding' => 'MP3',
                        'pitch' => (float) config('services.tts.google_pitch', -5.0),
                    ],
                ],
            );

            $b64 = $response->json('audioContent');
            if ($response->successful() && $b64) {
                File::put($file, base64_decode($b64));

                return true;
            }
        } catch (\Throwable) {
            // fall through
        }

        return false;
    }
}
