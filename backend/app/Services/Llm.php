<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Minimal LLM client used by chat and correction endpoints.
 * Provider priority: Anthropic (AI_API_KEY) → Gemini (GEMINI_API_KEY, free
 * tier at aistudio.google.com) → null (callers fall back to mock replies).
 */
class Llm
{
    /** True when any provider is configured. */
    public static function available(): bool
    {
        return (bool) (config('services.ai.key') || config('services.ai.gemini_key'));
    }

    /**
     * Run one completion. $messages entries: ['role' => 'user'|'assistant', 'content' => string].
     * Returns the raw text reply, or null on any failure.
     */
    public static function generate(string $system, array $messages, int $maxTokens = 400): ?string
    {
        if ($key = config('services.ai.key')) {
            return self::anthropic($key, $system, $messages, $maxTokens);
        }

        if ($key = config('services.ai.gemini_key')) {
            return self::gemini($key, $system, $messages, $maxTokens);
        }

        return null;
    }

    private static function anthropic(string $key, string $system, array $messages, int $maxTokens): ?string
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
            ])->timeout(20)->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.ai.model', 'claude-haiku-4-5-20251001'),
                'max_tokens' => $maxTokens,
                'system' => $system,
                'messages' => $messages,
            ]);

            return $response->successful() ? $response->json('content.0.text') : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function gemini(string $key, string $system, array $messages, int $maxTokens): ?string
    {
        // Gemini uses role "model" for the assistant and a parts[] wrapper.
        $contents = array_map(fn ($m) => [
            'role' => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ], $messages);

        $model = config('services.ai.gemini_model', 'gemini-2.5-flash');

        try {
            $response = Http::timeout(20)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
                [
                    'system_instruction' => ['parts' => [['text' => $system]]],
                    'contents' => $contents,
                    'generationConfig' => ['maxOutputTokens' => $maxTokens],
                ],
            );

            return $response->successful()
                ? $response->json('candidates.0.content.parts.0.text')
                : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
