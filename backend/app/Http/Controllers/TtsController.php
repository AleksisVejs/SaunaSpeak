<?php

namespace App\Http\Controllers;

use App\Services\ElevenLabs;
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
 *   0. ElevenLabs, but only when ELEVENLABS_FOR_CHAT is on. Off by default:
 *      chat is unbounded spend (a call per Väinö reply, per learner), which
 *      would drain a character budget that's better spent on lesson audio
 *      everyone hears. Cached clips are still served without re-spending.
 *   1. edge-tts (free; male fi-FI-HarriNeural - same voice as lesson audio -
 *      or female fi-FI-NooraNeural for female scenario personas); needs
 *      Python, so it works locally and on a VPS but not shared hosting.
 *   2. Google Cloud TTS (GOOGLE_TTS_API_KEY) - cPanel-friendly HTTP API.
 *      No male Finnish voice exists there, so the WaveNet voice is pitched
 *      down (services.tts.google_pitch) when standing in for a male speaker.
 *   3. None available → 503; the frontend stays silent by design (no
 *      browser-voice fallback).
 */
class TtsController extends Controller
{
    public function speak(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:300'],
            // Scenario characters speak in their own voice: female personas
            // (Marja, Liisa, Ritva, Sirpa) get the female neural voice.
            'voice' => ['sometimes', 'in:male,female'],
        ]);
        $female = ($data['voice'] ?? 'male') === 'female';

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

        // Female clips get their own cache slot; the bare hash stays the male
        // voice so every clip cached before voices existed remains valid.
        $hash = md5(($female ? 'female:' : '').$text);
        $file = public_path("audio/tts/{$hash}.mp3");
        $url = "/audio/tts/{$hash}.mp3";

        if (! File::exists($file)) {
            File::ensureDirectoryExists(dirname($file));

            if (! $this->viaElevenLabs($text, $file, $female)
                && ! $this->viaEdgeTts($text, $file, $female)
                && ! $this->viaGoogle($text, $file, $female)) {
                return response()->json(['url' => null], 503);
            }
        }

        return response()->json(['url' => $url]);
    }

    /** Premium voice for chat - opt-in only (ELEVENLABS_FOR_CHAT), see above. */
    private function viaElevenLabs(string $text, string $file, bool $female): bool
    {
        if (! config('services.elevenlabs.for_chat') || ! ElevenLabs::available()) {
            return false;
        }

        $voice = ElevenLabs::voiceId($female ? 'female' : 'male');
        if ($voice === null) {
            return false;
        }

        $audio = ElevenLabs::synthesize($text, $voice);
        if ($audio === null) {
            return false; // out of credits or misconfigured - fall through to edge-tts
        }

        File::put($file, $audio);

        return true;
    }

    /** Free neural voice via edge-tts; false where Python isn't available. */
    private function viaEdgeTts(string $text, string $file, bool $female = false): bool
    {
        try {
            // SystemRoot must be passed explicitly: children of PHP's built-in
            // server can lose it, which breaks Winsock (10106) and asyncio.
            // SystemDrive too - without it Windows drops shell caches into a
            // literal "%SystemDrive%" folder under the working directory.
            $result = Process::timeout(20)
                ->env([
                    'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
                    'SYSTEMROOT' => getenv('SYSTEMROOT') ?: 'C:\\Windows',
                    'SystemDrive' => getenv('SystemDrive') ?: 'C:',
                ])
                ->run([
                    config('services.tts.bin', 'edge-tts'),
                    '--voice', $female ? 'fi-FI-NooraNeural' : 'fi-FI-HarriNeural',
                    '--text', $text,
                    '--write-media', $file,
                ]);

            return $result->successful() && File::exists($file);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Google Cloud TTS fallback (plain HTTP, works on shared hosting). */
    private function viaGoogle(string $text, string $file, bool $female = false): bool
    {
        $key = config('services.tts.google_key');
        if (! $key) {
            return false;
        }

        try {
            // Key travels in a header, never the URL: query strings leak into
            // access logs and proxy caches, headers don't.
            $response = Http::withHeaders(['X-Goog-Api-Key' => $key])->timeout(15)->post(
                'https://texttospeech.googleapis.com/v1/text:synthesize',
                [
                    'input' => ['text' => $text],
                    'voice' => [
                        'languageCode' => 'fi-FI',
                        'name' => config('services.tts.google_voice', 'fi-FI-Wavenet-A'),
                    ],
                    'audioConfig' => [
                        'audioEncoding' => 'MP3',
                        // The WaveNet voice is female; it's only pitched down
                        // when it has to stand in for a male speaker.
                        'pitch' => $female ? 0.0 : (float) config('services.tts.google_pitch', -5.0),
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
