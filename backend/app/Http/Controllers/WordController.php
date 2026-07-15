<?php

namespace App\Http\Controllers;

use App\Models\UserWord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WordController extends Controller
{
    /** How many cards to serve in one flashcard review. */
    private const REVIEW_SIZE = 20;

    /** GET /api/words - the user's word bank, newest first. */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'words' => $request->user()->words()->with('sentence:id,finnish_text,audio_url')->latest()->get(),
            'due_count' => $request->user()->words()->due()->count(),
        ]);
    }

    /**
     * POST /api/words - save a tapped word. Idempotent per (user, word),
     * so tapping the same word twice never duplicates it.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'max:64'],
            'gloss' => ['nullable', 'string', 'max:255'],
            'sentence_id' => ['nullable', 'integer', 'exists:sentences,id'],
        ]);

        $word = $request->user()->words()->firstOrCreate(
            ['word' => mb_strtolower(trim($data['word']))],
            ['gloss' => $data['gloss'] ?? null, 'sentence_id' => $data['sentence_id'] ?? null],
        );

        return response()->json(['word' => $word], $word->wasRecentlyCreated ? 201 : 200);
    }

    /** GET /api/words/review - a deck of due flashcards (new + due), oldest due first. */
    public function review(Request $request): JsonResponse
    {
        $cards = $request->user()->words()
            ->due()
            ->orderByRaw('next_review_at is null desc') // brand-new words first
            ->orderBy('next_review_at')
            ->limit(self::REVIEW_SIZE)
            ->get();

        return response()->json([
            'cards' => $cards,
            'due_count' => $request->user()->words()->due()->count(),
        ]);
    }

    /**
     * POST /api/words/{id}/grade - record a flashcard answer and reschedule it
     * with the same spaced-repetition ladder used for sentences.
     */
    public function grade(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'grade' => ['required', 'in:again,good,easy'],
        ]);

        $word = $request->user()->words()->whereKey($id)->firstOrFail();
        $current = $word->status ?? UserWord::STATUS_NEW;

        [$nextStatus, $intervalDays] = match ($data['grade']) {
            'again' => [UserWord::STATUS_LEARNING, 0],
            'easy' => match ($current) {
                UserWord::STATUS_NEW => [UserWord::STATUS_REVIEW, 4],
                UserWord::STATUS_LEARNING => [UserWord::STATUS_MASTERED, 15],
                UserWord::STATUS_REVIEW => [UserWord::STATUS_MASTERED, 30],
                default => [UserWord::STATUS_MASTERED, 60],
            },
            default => match ($current) {
                UserWord::STATUS_NEW => [UserWord::STATUS_LEARNING, 1],
                UserWord::STATUS_LEARNING => [UserWord::STATUS_REVIEW, 4],
                UserWord::STATUS_REVIEW => [UserWord::STATUS_MASTERED, 15],
                default => [UserWord::STATUS_MASTERED, 30],
            },
        };

        $word->update([
            'status' => $nextStatus,
            'next_review_at' => now()->addDays($intervalDays),
            'reviews' => $word->reviews + 1,
        ]);

        \App\Models\ReviewLog::create([
            'user_id' => $request->user()->id,
            'kind' => 'word',
            'grade' => $data['grade'],
            'created_at' => now(),
        ]);

        return response()->json([
            'word' => $word,
            'due_count' => $request->user()->words()->due()->count(),
        ]);
    }

    /** DELETE /api/words/{id} - remove a word from the bank. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->words()->whereKey($id)->delete();

        return response()->json(['ok' => true]);
    }
}
