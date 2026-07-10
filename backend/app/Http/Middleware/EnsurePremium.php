<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates Löyly+ features (Sauna Chat, chat TTS, insights). Returns 402 with a
 * machine-readable code so the frontend can show the upgrade screen.
 */
class EnsurePremium
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isPremium()) {
            return response()->json([
                'message' => 'This is a Löyly+ feature.',
                'code' => 'premium_required',
            ], 402);
        }

        return $next($request);
    }
}
