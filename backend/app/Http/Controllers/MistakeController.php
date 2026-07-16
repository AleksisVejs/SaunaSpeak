<?php

namespace App\Http\Controllers;

use App\Models\ReviewLog;
use App\Models\UserMistake;
use App\Support\Srs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Flashcard review over the learner's own chat mistakes (see UserMistake).
 * Cards are captured automatically by ChatController whenever a character
 * corrects the learner; here they get the same review ladder as the word
 * bank. Reviewing stays available even if Löyly+ lapses - it's the
 * learner's own data.
 */
class MistakeController extends Controller
{
    /** How many cards to serve in one review sitting. */
    private const REVIEW_SIZE = 20;

    /** GET /api/mistakes/review - a deck of due cards, freshest mistakes first. */
    public function review(Request $request): JsonResponse
    {
        $cards = $request->user()->mistakes()
            ->due()
            ->orderByRaw('next_review_at is null desc') // brand-new mistakes first
            ->orderBy('next_review_at')
            ->limit(self::REVIEW_SIZE)
            ->get(['id', 'attempt', 'corrected', 'source', 'status', 'next_review_at']);

        return response()->json([
            'cards' => $cards,
            'due_count' => $request->user()->mistakes()->due()->count(),
        ]);
    }

    /** POST /api/mistakes/{id}/grade - reschedule on the shared flashcard ladder. */
    public function grade(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'grade' => ['required', 'in:again,good,easy'],
        ]);

        $mistake = $request->user()->mistakes()->whereKey($id)->firstOrFail();

        [$nextStatus, $intervalDays] = Srs::gradeStep(
            $mistake->status ?? UserMistake::STATUS_NEW,
            $data['grade'],
        );

        $mistake->update([
            'status' => $nextStatus,
            'next_review_at' => now()->addDays($intervalDays),
            'reviews' => $mistake->reviews + 1,
        ]);

        ReviewLog::create([
            'user_id' => $request->user()->id,
            'kind' => 'mistake',
            'grade' => $data['grade'],
            'created_at' => now(),
        ]);

        return response()->json([
            'mistake' => $mistake,
            'due_count' => $request->user()->mistakes()->due()->count(),
        ]);
    }

    /** DELETE /api/mistakes/{id} - drop a card (e.g. a correction that missed). */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->mistakes()->whereKey($id)->delete();

        return response()->json(['ok' => true]);
    }
}
