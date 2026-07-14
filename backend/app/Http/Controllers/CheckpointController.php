<?php

namespace App\Http\Controllers;

use App\Models\Sentence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Level checkpoints: a short, low-stakes cumulative recall quiz over
 * everything the learner has studied at a CEFR level. Passing awards a
 * badge on the journey path. Low-stakes cumulative testing (successive
 * relearning) is itself a learning event, not just a measurement - so
 * checkpoints can be retaken any time.
 */
class CheckpointController extends Controller
{
    private const QUIZ_SIZE = 10;
    private const MIN_STUDIED = 5;
    private const PASS_RATIO = 0.8;
    private const XP_PASS_BONUS = 100;

    /** GET /api/checkpoint/{level} - a random recall quiz over studied sentences. */
    public function show(Request $request, string $level): JsonResponse
    {
        $level = $this->validLevel($level);
        $user = $request->user();

        $studiedIds = $user->progress()->pluck('sentence_id');

        $pool = Sentence::whereIn('sentences.id', $studiedIds)
            ->join('lessons', 'lessons.id', '=', 'sentences.lesson_id')
            ->where('lessons.level', $level)
            ->get(['sentences.*']);

        if ($pool->count() < self::MIN_STUDIED) {
            return response()->json([
                'ready' => false,
                'studied' => $pool->count(),
                'needed' => self::MIN_STUDIED,
            ]);
        }

        return response()->json([
            'ready' => true,
            'sentences' => $pool->shuffle()->take(self::QUIZ_SIZE)->values(),
            'pass_ratio' => self::PASS_RATIO,
            'passed_at' => $user->checkpoints[$level] ?? null,
        ]);
    }

    /** POST /api/checkpoint/{level} - record the result; a pass earns the badge. */
    public function complete(Request $request, string $level): JsonResponse
    {
        $level = $this->validLevel($level);

        $data = $request->validate([
            'correct' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $passed = $data['correct'] / $data['total'] >= self::PASS_RATIO;
        $alreadyPassed = isset($user->checkpoints[$level]);
        $xp = 0;

        if ($passed && ! $alreadyPassed) {
            $user->update([
                'checkpoints' => array_merge($user->checkpoints ?? [], [$level => now()->toIso8601String()]),
            ]);
            $xp = self::XP_PASS_BONUS;
            $user->increment('xp', $xp);
        }

        return response()->json([
            'passed' => $passed,
            'xp_gained' => $xp,
            'user' => $user->fresh(),
        ]);
    }

    private function validLevel(string $level): string
    {
        validator(['level' => $level], ['level' => ['required', Rule::in(['A0', 'A1', 'A2', 'B1'])]])->validate();

        return $level;
    }
}
