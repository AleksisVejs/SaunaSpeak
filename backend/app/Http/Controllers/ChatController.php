<?php

namespace App\Http\Controllers;

use App\Services\Llm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sauna Chat: free conversation practice with Väinö, an old-school Finn on
 * the sauna bench. Producing your own sentences (not just recalling prompted
 * ones) is what forces you to notice the gaps in your Finnish — that's the
 * output hypothesis, and it's the one practice mode drills can't replace.
 *
 * Uses Anthropic or Gemini via App\Services\Llm when a key is configured;
 * otherwise a small scripted fallback keeps the UI usable in demos and tests.
 */
class ChatController extends Controller
{
    private const MAX_TURNS = 30;

    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:'.self::MAX_TURNS],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $mastered = $user->progress()->where('status', 'mastered')->count();
        $level = $mastered >= 40 ? 'A2' : ($mastered >= 15 ? 'A1' : 'A0');

        if (Llm::available()) {
            $response = $this->chatWithAi($data['messages'], $level);
            if ($response !== null) {
                return response()->json($response);
            }

            // Rate limited / out of credits: say so in character instead of
            // silently degrading to the mock — a looping mock reads as "broken".
            if (in_array(Llm::$lastStatus, [402, 429], true)) {
                return response()->json([
                    'reply' => 'Huh, nyt on liikaa löylyä! Väinö hengähtää hetken — kokeile kohta uudestaan. 🧖',
                    'translation' => Llm::$lastStatus === 402
                        ? 'Phew, too much steam! (AI credits are used up — top up to keep chatting.)'
                        : 'Phew, too much steam! Väinö is catching his breath — try again in a minute. (AI rate limit reached.)',
                    'correction' => null,
                    'source' => 'rate_limited',
                ]);
            }
        }

        return response()->json($this->mockReply($data['messages']));
    }

    private function chatWithAi(array $messages, string $level): ?array
    {
        $system = <<<PROMPT
You are Väinö, a warm, dry-witted Finnish man in his fifties relaxing on the sauna bench with a language learner. A classic mökki-Finn: loves löyly, lake swims, makkara and comfortable silences — but patient and encouraging with learners. RULES:
- Speak everyday SPOKEN Finnish (puhekieli): mä, sä, onks, emmä, tää, toi, -ks questions, mennään-forms. NEVER stiff kirjakieli.
- The learner is roughly {$level} level. Keep replies SHORT (1-2 simple sentences) and use common words. Slightly stretch their level, never flood it.
- Colloquial forms from the learner are CORRECT. Only flag real errors (wrong word, broken ending, wrong meaning).
- Keep the chat going: react, then ask a simple question back. Sauna, weather, food, weekend — everyday topics.
- Reply with ONLY a JSON object:
{"reply":"<your Finnish reply>","translation":"<English translation of your reply>","correction":<null, or "<gently corrected version of the learner's LAST message>" if it had a real error>}
PROMPT;

        $text = Llm::generate($system, $messages);
        if ($text === null) {
            return null;
        }

        // Tolerate models that wrap JSON in markdown fences.
        $text = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)));
        $parsed = json_decode($text, true);

        if (is_array($parsed) && isset($parsed['reply'], $parsed['translation'])) {
            return [
                'reply' => $parsed['reply'],
                'translation' => $parsed['translation'],
                'correction' => $parsed['correction'] ?? null,
                'source' => 'ai',
            ];
        }

        return null;
    }

    /** Keyed to conversation length so the demo chat feels alive without a key. */
    private function mockReply(array $messages): array
    {
        $script = [
            ['reply' => 'No moi! Onks sulla ollu hyvä päivä?', 'translation' => 'Well hi! Have you had a good day?'],
            ['reply' => 'Kiva kuulla! Käytsä usein saunassa?', 'translation' => 'Nice to hear! Do you go to the sauna often?'],
            ['reply' => 'Sauna on paras. Mitä sä syöt saunan jälkeen?', 'translation' => 'Sauna is the best. What do you eat after sauna?'],
            ['reply' => 'Hyvä valinta! Mitä sä teet viikonloppuna?', 'translation' => 'Good choice! What are you doing on the weekend?'],
            ['reply' => 'Kuulostaa kivalta. Heitetäänks lisää löylyä?', 'translation' => 'Sounds nice. Shall we throw more steam?'],
        ];

        $turn = intdiv(count($messages) - 1, 2);

        return $script[min($turn, count($script) - 1)] + ['correction' => null, 'source' => 'mock'];
    }
}
