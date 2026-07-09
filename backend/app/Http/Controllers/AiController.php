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
        ]);

        if (Llm::available()) {
            $response = $this->correctWithAi($data['user_sentence'], $data['expected_sentence']);
            if ($response !== null) {
                return response()->json($response);
            }
        }

        return response()->json($this->mockCorrection($data['user_sentence'], $data['expected_sentence']));
    }

    private function correctWithAi(string $userSentence, string $expectedSentence): ?array
    {
        $prompt = "You are a friendly Finnish teacher who teaches everyday SPOKEN Finnish (puhekieli). The student tried to say: \"{$expectedSentence}\" and wrote: \"{$userSentence}\". Colloquial spoken forms (mä oon, sä oot, onks, emmä, tää, toi...) are CORRECT — never \"fix\" puhekieli into formal written Finnish (kirjakieli). Only correct real mistakes in meaning, word choice or endings. Reply with ONLY a JSON object: {\"corrected\": \"<corrected spoken-Finnish sentence>\", \"explanation\": \"<one short, encouraging sentence in English>\"}";

        $text = Llm::generate('You are a concise Finnish teacher. Reply with only the requested JSON.', [
            ['role' => 'user', 'content' => $prompt],
        ], 300);

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
            $user === $expected => 'Hienoa! Perfect — that\'s exactly how a Finn would say it.',
            $percent >= 80 => 'Almost there! Compare the small differences — often just one word ending.',
            default => 'Compare your version with the corrected sentence — word endings carry most of the meaning in Finnish.',
        };

        return [
            'corrected' => $expectedSentence,
            'explanation' => $explanation,
            'match' => $user === $expected,
            'source' => 'mock',
        ];
    }
}
