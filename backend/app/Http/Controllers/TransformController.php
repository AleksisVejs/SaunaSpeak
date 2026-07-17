<?php

namespace App\Http\Controllers;

use App\Support\Transforms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Taivutus drills: change the sentence, don't recall it. Free path - this is
 * the generative half of the method, not an add-on.
 */
class TransformController extends Controller
{
    /** XP for clearing a set the first time (once per set). */
    private const XP_SET = 25;

    /** GET /api/transforms - the catalog, with each set's done flag. */
    public function index(Request $request): JsonResponse
    {
        $done = $request->user()->transforms_done ?? [];

        return response()->json([
            'sets' => array_map(
                fn (array $s) => $s + ['done' => isset($done[$s['id']])],
                Transforms::index(),
            ),
        ]);
    }

    /** GET /api/transforms/{id} - one set with its items. */
    public function show(Request $request, string $id): JsonResponse
    {
        $set = Transforms::find($id);
        abort_unless($set !== null, 404);

        $done = $request->user()->transforms_done ?? [];

        return response()->json(['set' => $set + ['done' => isset($done[$id])]]);
    }

    /**
     * POST /api/transforms/{id}/complete - worked through the set.
     * Idempotent; only the first clear pays XP.
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        abort_unless(Transforms::find($id) !== null, 404);

        $user = $request->user();
        $done = $user->transforms_done ?? [];
        $xp = 0;

        if (! isset($done[$id])) {
            $xp = self::XP_SET;
            $user->update(['transforms_done' => array_merge($done, [$id => now()->toIso8601String()])]);
            $user->increment('xp', $xp);
        }

        $user->refresh();

        return response()->json([
            'transforms_done' => $user->transforms_done,
            'xp_gained' => $xp,
            'xp' => $user->xp,
        ]);
    }
}
