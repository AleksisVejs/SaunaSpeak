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
        // Anthropic (paid) — used first when set.
        'key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL', 'claude-haiku-4-5-20251001'),
        // Google Gemini — free tier at https://aistudio.google.com/apikey
        'gemini_key' => env('GEMINI_API_KEY'),
        'gemini_model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    // Absolute path to the edge-tts binary for on-demand chat TTS. Web server
    // processes often have a leaner PATH than your shell, so a full path is
    // the reliable option. Leave unset where edge-tts isn't installed —
    // the /api/tts endpoint then returns 503 and the browser voice takes over.
    'tts' => [
        'bin' => env('EDGE_TTS_BIN', 'edge-tts'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
