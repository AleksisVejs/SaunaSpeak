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
    /** HTTP status of the last failed call (e.g. 429 = rate limited), or null. */
    public static ?int $lastStatus = null;

    /** True when any provider is configured. */
    public static function available(): bool
    {
        return (bool) (
            config('services.ai.key')
            || config('services.ai.openrouter_key')
            || config('services.ai.gemini_key')
        );
    }

    /**
     * Run one completion. $messages entries: ['role' => 'user'|'assistant', 'content' => string].
     * Returns the raw text reply, or null on any failure.
     * $model overrides the configured model (honored by the OpenRouter provider).
     */
    public static function generate(string $system, array $messages, int $maxTokens = 400, ?string $model = null, ?float $temperature = null): ?string
    {
        self::$lastStatus = null;

        if ($key = config('services.ai.key')) {
            return self::anthropic($key, $system, $messages, $maxTokens, $temperature);
        }

        if ($key = config('services.ai.openrouter_key')) {
            return self::openrouter($key, $system, $messages, $maxTokens, $model, $temperature);
        }

        if ($key = config('services.ai.gemini_key')) {
            return self::gemini($key, $system, $messages, $maxTokens, $temperature);
        }

        return null;
    }

    /** OpenRouter: OpenAI-compatible API over prepaid credits. */
    private static function openrouter(string $key, string $system, array $messages, int $maxTokens, ?string $model = null, ?float $temperature = null): ?string
    {
        try {
            $payload = [
                'model' => $model ?? config('services.ai.openrouter_model', 'google/gemini-2.5-flash'),
                // Reasoning models spend invisible thinking tokens inside
                // max_tokens - disable it and keep headroom, or long
                // prompts come back as truncated (unparseable) JSON.
                'reasoning' => ['enabled' => false],
                'max_tokens' => max(2048, $maxTokens),
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ...$messages,
                ],
            ];
            if ($temperature !== null) {
                $payload['temperature'] = $temperature;
            }

            $response = Http::withToken($key)
                ->withHeaders([
                    // Shown in OpenRouter's usage dashboard.
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => 'SaunaSpeak',
                ])
                ->timeout(25)
                ->post('https://openrouter.ai/api/v1/chat/completions', $payload);

            if (! $response->successful()) {
                // 402 = credits exhausted, 429 = rate limited.
                self::$lastStatus = $response->status();

                return null;
            }

            return $response->json('choices.0.message.content');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function anthropic(string $key, string $system, array $messages, int $maxTokens, ?float $temperature = null): ?string
    {
        try {
            $payload = [
                'model' => config('services.ai.model', 'claude-haiku-4-5-20251001'),
                'max_tokens' => $maxTokens,
                'system' => $system,
                'messages' => $messages,
            ];
            if ($temperature !== null) {
                $payload['temperature'] = $temperature;
            }

            $response = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
            ])->timeout(20)->post('https://api.anthropic.com/v1/messages', $payload);

            if (! $response->successful()) {
                self::$lastStatus = $response->status();

                return null;
            }

            return $response->json('content.0.text');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function gemini(string $key, string $system, array $messages, int $maxTokens, ?float $temperature = null): ?string
    {
        // Gemini uses role "model" for the assistant and a parts[] wrapper.
        $contents = array_map(fn ($m) => [
            'role' => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ], $messages);

        $model = config('services.ai.gemini_model', 'gemini-2.5-flash');

        // Gemini 2.5 spends output tokens on internal "thinking" before the
        // visible reply - disable it and keep a generous cap, or long replies
        // come back truncated mid-JSON.
        $payload = [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => max(1024, $maxTokens),
                'thinkingConfig' => ['thinkingBudget' => 0],
            ],
        ];
        if ($temperature !== null) {
            $payload['generationConfig']['temperature'] = $temperature;
        }

        try {
            // Key in a header, not the URL query string: query strings get
            // captured by access logs and proxy caches, headers don't.
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
            $request = Http::withHeaders(['x-goog-api-key' => $key])->timeout(20);
            $response = $request->post($url, $payload);

            // Models that don't accept thinkingConfig reject the request; retry without it.
            if ($response->clientError()) {
                unset($payload['generationConfig']['thinkingConfig']);
                $response = $request->post($url, $payload);
            }

            if (! $response->successful()) {
                self::$lastStatus = $response->status();

                return null;
            }

            return $response->json('candidates.0.content.parts.0.text');
        } catch (\Throwable) {
            return null;
        }
    }
}
