<?php

namespace App\Http\Controllers;

use App\Support\Listening;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kuuntelu: whole conversations at natural speed - the extensive-listening
 * side of the course, and deliberately part of the FREE path. Drills teach
 * items; only volume of connected speech teaches comprehension.
 */
class ListeningController extends Controller
{
    /** XP for listening a scene through the first time (once per scene). */
    private const XP_SCENE = 20;

    /** GET /api/listening - the catalog, with each scene's done flag. */
    public function index(Request $request): JsonResponse
    {
        $done = $request->user()->listening_done ?? [];

        return response()->json([
            'scenes' => array_map(
                fn (array $s) => $s + ['done' => isset($done[$s['id']])],
                Listening::index(),
            ),
        ]);
    }

    /** GET /api/listening/{id} - one scene with its lines and per-line audio. */
    public function show(Request $request, string $id): JsonResponse
    {
        $scene = Listening::find($id);
        abort_unless($scene !== null, 404);

        $done = $request->user()->listening_done ?? [];

        return response()->json(['scene' => $scene + ['done' => isset($done[$id])]]);
    }

    /**
     * POST /api/listening/{id}/complete - listened all the way through.
     * Idempotent; only the first completion pays XP.
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        abort_unless(Listening::find($id) !== null, 404);

        $user = $request->user();
        $done = $user->listening_done ?? [];
        $xp = 0;

        if (! isset($done[$id])) {
            $xp = self::XP_SCENE;
            $user->update(['listening_done' => array_merge($done, [$id => now()->toIso8601String()])]);
            $user->increment('xp', $xp);
        }

        $user->refresh();

        return response()->json([
            'listening_done' => $user->listening_done,
            'xp_gained' => $xp,
            'xp' => $user->xp,
        ]);
    }
}
