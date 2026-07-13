<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserWord;
use App\Services\Llm;
use App\Support\Scenarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Sauna Chat: free conversation practice with Väinö, an old-school Finn on
 * the sauna bench. Producing your own sentences (not just recalling prompted
 * ones) is what forces you to notice the gaps in your Finnish - that's the
 * output hypothesis, and it's the one practice mode drills can't replace.
 *
 * Tilanteet (scenario mode): the same chat engine playing a character in an
 * everyday situation - cashier, barista, neighbor - with a concrete mission
 * for the learner to accomplish. The model reports mission completion via
 * `goal_reached` in its JSON reply.
 *
 * Uses Anthropic or Gemini via App\Services\Llm when a key is configured;
 * otherwise a small scripted fallback keeps the UI usable in demos and tests.
 */
class ChatController extends Controller
{
    private const MAX_TURNS = 30;

    /** Only the recent context goes to the LLM - Väinö doesn't need turn 1
     *  to answer turn 29, and resending everything scales cost quadratically. */
    private const CONTEXT_WINDOW = 12;

    /** Per-user daily message budget: bounds worst-case spend per learner. */
    private const DAILY_LIMIT = 200;

    /** How readable the learner's intake goal is to the model. */
    private const GOAL_LABELS = [
        'move' => 'they are moving to Finland',
        'travel' => 'they want to travel around Finland',
        'family' => 'they have Finnish family or friends',
        'casual' => 'they are learning for fun',
    ];

    /** GET /api/scenarios - the Tilanteet catalog, recommended-first. */
    public function scenarios(Request $request): JsonResponse
    {
        $goal = $request->user()->preferences['goal'] ?? null;

        $public = array_map(fn (array $s) => [
            'id' => $s['id'],
            'emoji' => $s['emoji'],
            'title' => $s['title'],
            'tagline' => $s['tagline'],
            'persona' => $s['persona'],
            'mission' => $s['mission'],
            'difficulty' => $s['difficulty'],
            'opener' => $s['opener_fi'],
            'opener_translation' => $s['opener_en'],
            'recommended' => $s['recommended'],
        ], Scenarios::forGoal(is_string($goal) ? $goal : null));

        return response()->json(['scenarios' => $public]);
    }

    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:'.self::MAX_TURNS],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:500'],
            'scenario' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', Scenarios::ids())],
        ]);

        $user = $request->user();
        $scenario = Scenarios::find($data['scenario'] ?? null);

        $budgetKey = 'chat-budget:'.$user->id.':'.today()->toDateString();
        Cache::add($budgetKey, 0, now()->endOfDay());
        if (Cache::increment($budgetKey) > self::DAILY_LIMIT) {
            return response()->json([
                'reply' => 'No nyt riittää löylyt tälle päivälle! Väinö menee järveen. Nähdään huomenna. 🌊',
                'translation' => 'That\'s enough steam for one day! Väinö is off to the lake. See you tomorrow. (Daily chat limit reached - it resets at midnight.)',
                'correction' => null,
                'source' => 'daily_cap',
            ]);
        }
        $mastered = $user->progress()->where('status', 'mastered')->count();
        $level = $mastered >= 40 ? 'A2' : ($mastered >= 15 ? 'A1' : 'A0');

        $system = $scenario
            ? $this->scenarioPrompt($scenario, $level, $this->learnerContext($user))
            : $this->vainoPrompt($level, $this->learnerContext($user));

        if (Llm::available()) {
            $response = $this->chatWithAi($system, $data['messages'], (bool) $scenario);
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

        return response()->json(
            $scenario ? $this->mockScenarioReply($data['messages']) : $this->mockReply($data['messages'])
        );
    }

    /**
     * What the model should know about this learner: name, why they're
     * learning, and up to three words they keep getting wrong so the chat
     * can resurface them where a drill can't - in live use.
     */
    private function learnerContext(User $user): string
    {
        $lines = [];

        if ($first = Str::of($user->name)->trim()->explode(' ')->first()) {
            $lines[] = "The learner's name is {$first} - greet and address them by name now and then, not every turn.";
        }

        $goal = $user->preferences['goal'] ?? null;
        if (is_string($goal) && isset(self::GOAL_LABELS[$goal])) {
            $lines[] = 'Why they learn Finnish: '.self::GOAL_LABELS[$goal].'. Let topics drift there when natural.';
        }

        // The words most overdue for review are the ones slipping away.
        $struggling = $user->words()
            ->whereIn('status', [UserWord::STATUS_LEARNING, UserWord::STATUS_REVIEW])
            ->due()
            ->orderByRaw('next_review_at is null, next_review_at asc')
            ->limit(3)
            ->pluck('word')
            ->all();

        if ($struggling !== []) {
            $words = implode(', ', $struggling);
            $lines[] = "Words they are struggling to remember: {$words}. When it fits naturally, work ONE into a reply or question (never more than one per turn, never forced).";
        }

        return $lines === [] ? '' : "\nABOUT THIS LEARNER:\n- ".implode("\n- ", $lines);
    }

    /** Rules shared by Väinö and every scenario character. */
    private function sharedRules(string $level): string
    {
        return <<<RULES
- Speak everyday SPOKEN Finnish (puhekieli): mä, sä, onks, emmä, tää, toi, -ks questions, mennään-forms. NEVER stiff kirjakieli.
- The learner is roughly {$level} level. Keep replies SHORT (1-2 simple sentences) and use common words. Slightly stretch their level, never flood it.
- ALWAYS respond to what the learner ACTUALLY wrote. Never ignore their message, never pretend they said something they didn't.
- The "assistant" messages in the conversation are YOURS. Never answer a question you asked yourself.
- If they ask a language question in any language ("how do you say X", "what does Y mean"), ANSWER it - then slip back into the conversation.
- If they write in English, respond to their point and show how to say it in simple spoken Finnish, then nudge them to try.
- If their message isn't real language ("123", "asdf", bare emoji), do NOT reply as if it were normal chat. Acknowledge it with dry humor and make it useful.
- Colloquial forms from the learner are CORRECT. Only flag real errors (wrong word, broken ending, wrong meaning).
RULES;
    }

    private function vainoPrompt(string $level, string $learnerContext): string
    {
        $rules = $this->sharedRules($level);

        return <<<PROMPT
You are Väinö, a warm, dry-witted Finnish man in his fifties relaxing on the sauna bench with a language learner. A classic mökki-Finn: loves löyly, lake swims, makkara and comfortable silences - but patient and encouraging with learners. RULES:
{$rules}
- After reacting, keep the chat going with a simple question back. Sauna, weather, food, weekend - everyday topics.
{$learnerContext}
- Reply with ONLY a JSON object:
{"reply":"<your Finnish reply>","translation":"<English translation of your reply>","correction":<null, or "<gently corrected version of the learner's LAST message>" if it had a real error>}
PROMPT;
    }

    private function scenarioPrompt(array $scenario, string $level, string $learnerContext): string
    {
        $rules = $this->sharedRules($level);

        return <<<PROMPT
You are {$scenario['persona']}, {$scenario['role']}. This is a ROLEPLAY to help a Finnish learner practice a real-life situation.
SCENE: {$scenario['scene']}
THE LEARNER'S MISSION: {$scenario['mission']}
RULES:
{$rules}
- Stay in character and inside the scene. If the learner drifts off-topic, react briefly, then steer back to the situation.
- Don't do the mission for them: let THEM ask, order and pay. Nudge with a natural in-character question if they're stuck.
- MISSION COMPLETE when {$scenario['goal_check']}. Then set goal_reached to true and wrap up warmly in character (thank them, say goodbye).
{$learnerContext}
- Reply with ONLY a JSON object:
{"reply":"<your Finnish reply>","translation":"<English translation of your reply>","correction":<null, or "<gently corrected version of the learner's LAST message>" if it had a real error>,"goal_reached":<true once the mission is complete, else false>}
PROMPT;
    }

    private function chatWithAi(string $system, array $messages, bool $scenario): ?array
    {
        // Flash-class models read bare digits/emoji as small talk no matter
        // what the prompt says, so Väinö answered "123" with "I'm fine,
        // thanks". Tag letter-less input server-side (per-request only; the
        // client keeps its own history) so the non-language rule fires.
        // Trim to the recent window (keep the tail - that's the live thread).
        // Anthropic requires a user-first history, so shed a leading assistant turn.
        $messages = array_slice($messages, -self::CONTEXT_WINDOW);
        if ($messages !== [] && $messages[0]['role'] === 'assistant') {
            array_shift($messages);
        }

        $last = count($messages) - 1;
        if ($messages[$last]['role'] === 'user' && ! preg_match('/\p{L}/u', $messages[$last]['content'])) {
            $messages[$last]['content'] .= "\n[note: this message is not real language - apply your non-language rule]";
        }

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
            $response = [
                'reply' => $parsed['reply'],
                'translation' => $parsed['translation'],
                'correction' => $parsed['correction'] ?? null,
                'source' => 'ai',
            ];
            if ($scenario) {
                $response['goal_reached'] = (bool) ($parsed['goal_reached'] ?? false);
            }

            return $response;
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

    /** Scenario demo script: generic clerk-ish beats that fit any situation,
     *  reaching the goal on the third exchange so the completion UI is testable. */
    private function mockScenarioReply(array $messages): array
    {
        $script = [
            ['reply' => 'Joo, onnistuu! Se maksaa kolme euroa.', 'translation' => 'Sure, no problem! That costs three euros.', 'goal_reached' => false],
            ['reply' => 'Kiitos! Tässä, ole hyvä. Tarviitko kuitin?', 'translation' => 'Thanks! Here you go. Do you need a receipt?', 'goal_reached' => false],
            ['reply' => 'Selvä homma. Kiitti ja moikka - hyvää päivänjatkoa!', 'translation' => 'All done. Thanks and bye - have a nice day!', 'goal_reached' => true],
            ['reply' => 'Moikka moi! Tervetuloa uudestaan!', 'translation' => 'Bye bye! Welcome back again!', 'goal_reached' => true],
        ];

        $turn = intdiv(count($messages) - 1, 2);

        return $script[min($turn, count($script) - 1)] + ['correction' => null, 'source' => 'mock'];
    }
}
