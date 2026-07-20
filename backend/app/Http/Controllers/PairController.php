<?php

namespace App\Http\Controllers;

use App\Support\MinimalPairs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kuulo: front/back vowel discrimination (y/u, ä/a, ö/o).
 *
 * Free path. Three natives independently said this contrast - not vowel
 * length, not double consonants - is what actually stops them understanding
 * a learner, so it isn't an upsell.
 *
 * Perception only: the learner hears a word and picks which of the pair it
 * was. There is no speaking step, deliberately. Discrimination comes before
 * production (Logan, Lively & Pisoni 1991), and asking someone to produce a
 * contrast they cannot yet hear just fossilises the wrong sound.
 */
class PairController extends Controller
{
    /** XP for clearing a set the first time (once per set). */
    private const XP_SET = 20;

    /** GET /api/pairs - the catalog, with each set's done flag. */
    public function index(Request $request): JsonResponse
    {
        $done = $request->user()->pairs_done ?? [];

        return response()->json([
            'sets' => array_map(
                fn (array $s) => $s + ['done' => isset($done[$s['id']])],
                MinimalPairs::index(),
            ),
        ]);
    }

    /** GET /api/pairs/{id} - one set with its pairs and audio. */
    public function show(Request $request, string $id): JsonResponse
    {
        $set = MinimalPairs::find($id);
        abort_unless($set !== null, 404);

        $done = $request->user()->pairs_done ?? [];

        return response()->json(['set' => $set + ['done' => isset($done[$id])]]);
    }

    /**
     * POST /api/pairs/{id}/complete - worked through the set.
     * Idempotent; only the first clear pays XP.
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        abort_unless(MinimalPairs::find($id) !== null, 404);

        $user = $request->user();
        $done = $user->pairs_done ?? [];
        $xp = 0;

        if (! isset($done[$id])) {
            $xp = self::XP_SET;
            $user->update(['pairs_done' => array_merge($done, [$id => now()->toIso8601String()])]);
            $user->increment('xp', $xp);
        }

        $user->refresh();

        return response()->json([
            'pairs_done' => $user->pairs_done,
            'xp_gained' => $xp,
            'xp' => $user->xp,
        ]);
    }
}
