<?php

namespace App\Http\Controllers;

use App\Services\Llm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sauna Chat: free conversation practice with Väinö, an old-school Finn on
 * the sauna bench. Producing your own sentences (not just recalling prompted
 * ones) is what forces you to notice the gaps in your Finnish - that's the
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
            // silently degrading to the mock - a looping mock reads as "broken".
            if (in_array(Llm::$lastStatus, [402, 429], true)) {
                return response()->json([
                    'reply' => 'Huh, nyt on liikaa löylyä! Väinö hengähtää hetken - kokeile kohta uudestaan. 🧖',
                    'translation' => Llm::$lastStatus === 402
                        ? 'Phew, too much steam! (AI credits are used up - top up to keep chatting.)'
                        : 'Phew, too much steam! Väinö is catching his breath - try again in a minute. (AI rate limit reached.)',
                    'correction' => null,
                    'source' => 'rate_limited',
                ]);
            }
        }

        return response()->json($this->mockReply($data['messages']));
    }

    private function chatWithAi(array $messages, string $level): ?array
    {
        // Flash-class models read bare digits/emoji as small talk no matter
        // what the prompt says, so Väinö answered "123" with "I'm fine,
        // thanks". Tag letter-less input server-side (per-request only; the
        // client keeps its own history) so the non-language rule fires.
        $last = count($messages) - 1;
        if ($messages[$last]['role'] === 'user' && ! preg_match('/\p{L}/u', $messages[$last]['content'])) {
            $messages[$last]['content'] .= "\n[note: this message is not real language - apply your non-language rule]";
        }

        $system = <<<PROMPT
You are Väinö, a warm, dry-witted Finnish man in his fifties relaxing on the sauna bench with a language learner. A classic mökki-Finn: loves löyly, lake swims, makkara and comfortable silences - but patient and encouraging with learners. RULES:
- Speak everyday SPOKEN Finnish (puhekieli): mä, sä, onks, emmä, tää, toi, -ks questions, mennään-forms. NEVER stiff kirjakieli.
- The learner is roughly {$level} level. Keep replies SHORT (1-2 simple sentences) and use common words. Slightly stretch their level, never flood it.
- ALWAYS respond to what the learner ACTUALLY wrote. Never ignore their message, never pretend they said something they didn't, never fall back to unrelated small talk.
- The "assistant" messages in the conversation are YOURS. Never answer a question you asked yourself (if you asked "mitä kuuluu?", don't reply "hyvää kuuluu, kiitos" - react to THEIR answer). Never invent things about your own day that weren't already said.
- If they ask a language question in any language ("how do you say X", "what does Y mean", "say 123 in Finnish"), ANSWER it - you love teaching. Give the spoken-Finnish answer inside your reply.
- If they write in English, respond to their point and show how to say it in simple spoken Finnish, then nudge them to try.
- If their message isn't real language ("123", "asdf", bare emoji), do NOT reply as if it were normal chat. Acknowledge it with dry humor and make it useful. Example - learner: "123" → reply: "No, numeroita! Suomeks toi on satakakskytkolme. Mikä sun lempinumero on?"
- Colloquial forms from the learner are CORRECT. Only flag real errors (wrong word, broken ending, wrong meaning).
- After reacting, keep the chat going with a simple question back. Sauna, weather, food, weekend - everyday topics.
- Reply with ONLY a JSON object:
{"reply":"<your Finnish reply>","translation":"<English translation of your reply>","correction":<null, or "<gently corrected version of the learner's LAST message>" if it had a real error>}
PROMPT;

        // Lower temperature than the provider default: flash-class models at
        // high temperature invent things the learner never said.
        $text = Llm::generate($system, $messages, 400, null, 0.6);
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
