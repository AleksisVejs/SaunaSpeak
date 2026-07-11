<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'ai' => [
        // Anthropic (paid) - used first when set.
        'key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL', 'claude-haiku-4-5-20251001'),
        // OpenRouter (prepaid credits, no free-tier quota walls) - second priority.
        // gemini-2.5-flash: strong puhekieli, ~€4 ≈ thousands of chat turns.
        'openrouter_key' => env('OPENROUTER_API_KEY'),
        // Chat: gemini-2.5-flash won the register bake-off (authentic puhekieli).
        'openrouter_model' => env('OPENROUTER_MODEL', 'google/gemini-2.5-flash'),
        // Corrections: deepseek-v4-flash caught every planted error in testing
        // and costs ~1/15th on output - right tool for the high-volume endpoint.
        'openrouter_model_correct' => env('OPENROUTER_MODEL_CORRECT', 'deepseek/deepseek-v4-flash'),
        // Google Gemini - free tier at https://aistudio.google.com/apikey
        'gemini_key' => env('GEMINI_API_KEY'),
        // flash-lite: higher free-tier rate limits, plenty for A0-A2 chat.
        'gemini_model' => env('GEMINI_MODEL', 'gemini-2.5-flash-lite'),
    ],

    // Absolute path to the edge-tts binary for on-demand chat TTS. Web server
    // processes often have a leaner PATH than your shell, so a full path is
    // the reliable option. Leave unset where edge-tts isn't installed -
    // the /api/tts endpoint then returns 503 and the browser voice takes over.
    // Stripe subscription billing for Löyly+. All three unset → billing is
    // disabled and every feature is free (development / pre-launch mode).
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'price_id' => env('STRIPE_PRICE_ID'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'tts' => [
        'bin' => env('EDGE_TTS_BIN', 'edge-tts'),
        // Google Cloud TTS fallback for hosts that can't run edge-tts (cPanel).
        // Needs a GCP API key with the Text-to-Speech API enabled - this is a
        // different key than GEMINI_API_KEY (AI Studio). Google has no male
        // Finnish voice, so we pitch the female WaveNet voice down by default.
        'google_key' => env('GOOGLE_TTS_API_KEY'),
        'google_voice' => env('GOOGLE_TTS_VOICE', 'fi-FI-Wavenet-A'),
        'google_pitch' => env('GOOGLE_TTS_PITCH', -5.0),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
