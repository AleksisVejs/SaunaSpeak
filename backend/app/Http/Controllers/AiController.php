<?php

namespace App\Http\Controllers;

use App\Services\Llm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    /**
     * POST /api/ai/correct
     * Compares the user's attempt against the expected sentence.
     * Uses Anthropic or Gemini via App\Services\Llm when a key is configured;
     * otherwise returns a mock response with the same shape so the frontend
     * contract never changes.
     */
    public function correct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_sentence' => ['required', 'string', 'max:500'],
            'expected_sentence' => ['required', 'string', 'max:500'],
            'expected_translation' => ['nullable', 'string', 'max:500'],
        ]);

        // AI explanations are Löyly+; free users get the similarity mock below.
        if (Llm::available() && $request->user()->isPremium()) {
            $response = $this->correctWithAi(
                $data['user_sentence'],
                $data['expected_sentence'],
                $data['expected_translation'] ?? null
            );
            if ($response !== null) {
                return response()->json($response);
            }
        }

        return response()->json($this->mockCorrection($data['user_sentence'], $data['expected_sentence']));
    }

    private function correctWithAi(string $userSentence, string $expectedSentence, ?string $expectedTranslation = null): ?array
    {
        $meaning = $expectedTranslation !== null && $expectedTranslation !== ''
            ? " (meaning: \"{$expectedTranslation}\")"
            : '';

        // The correction must be anchored to the TARGET sentence. Without the
        // hard rule below, a far-off attempt gets grammar-fixed into a fluent
        // sentence that means something entirely different from the exercise
        // (e.g. target "Emmä tiiä", attempt "Mä en ystava" → "Mä en oo sun
        // ystävä"), which reads as the app teaching the wrong answer.
        $prompt = <<<PROMPT
You are a friendly Finnish teacher who teaches everyday SPOKEN Finnish (puhekieli).

The exercise asked the student to produce this target sentence: "{$expectedSentence}"{$meaning}
The student wrote: "{$userSentence}"

Rules:
- "corrected" MUST be the target sentence (or a natural spoken-Finnish variant with exactly the same meaning). NEVER build the corrected sentence from the student's words if the result would mean something different from the target.
- Colloquial spoken forms (mä oon, sä oot, onks, emmä, tää, toi...) are CORRECT - never "fix" puhekieli into formal written Finnish (kirjakieli).
- If the attempt was close, point out the small difference (a word ending, a missing word).
- If the attempt means something different from the target, briefly say what the student's sentence actually meant (if anything) and steer them back to the target.

Reply with ONLY a JSON object: {"corrected": "<spoken-Finnish target sentence>", "explanation": "<one short, encouraging sentence in English>"}
PROMPT;

        // Corrections use the precision-tuned model (bake-off winner for
        // error detection); chat keeps the register-tuned default.
        $text = Llm::generate('You are a concise Finnish teacher. Reply with only the requested JSON.', [
            ['role' => 'user', 'content' => $prompt],
        ], 300, config('services.ai.openrouter_model_correct'));

        if ($text !== null) {
            // Tolerate models that wrap JSON in markdown fences.
            $text = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)));
            $parsed = json_decode($text, true);

            if (is_array($parsed) && isset($parsed['corrected'], $parsed['explanation'])) {
                return [
                    'corrected' => $parsed['corrected'],
                    'explanation' => $parsed['explanation'],
                    'source' => 'ai',
                ];
            }
        }

        return null;
    }

    private function mockCorrection(string $userSentence, string $expectedSentence): array
    {
        $normalize = fn (string $s) => preg_replace('/[^\p{L}\p{N} ]/u', '', mb_strtolower(trim($s)));
        $user = $normalize($userSentence);
        $expected = $normalize($expectedSentence);

        similar_text($user, $expected, $percent);

        $explanation = match (true) {
            $user === $expected => 'Hienoa! Perfect - that\'s exactly how a Finn would say it.',
            $percent >= 80 => 'Almost there! Compare the small differences - often just one word ending.',
            default => 'Compare your version with the corrected sentence - word endings carry most of the meaning in Finnish.',
        };

        return [
            'corrected' => $expectedSentence,
            'explanation' => $explanation,
            'match' => $user === $expected,
            'source' => 'mock',
        ];
    }
}
