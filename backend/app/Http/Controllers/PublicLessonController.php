<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Sentence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Read-only lesson previews for the public /lessons pages - the SEO surface.
 * No auth, no progress data: just the curriculum itself, which is free
 * anyway. Slugs are derived from titles (unique across the catalog) so the
 * seeder stays the single source of truth.
 */
class PublicLessonController extends Controller
{
    public function index(): JsonResponse
    {
        $lessons = Lesson::withCount('sentences')
            ->with(['sentences' => fn ($q) => $q->orderBy('id')->limit(1)])
            ->orderBy('order_index')
            ->get()
            ->map(fn (Lesson $lesson) => [
                'slug' => Str::slug($lesson->title),
                'title' => $lesson->title,
                'level' => $lesson->level,
                'sentence_count' => (int) $lesson->sentences_count,
                // First sentence as the card's teaser line.
                'teaser' => $lesson->sentences->first()?->finnish_text,
            ]);

        return response()->json(['lessons' => $lessons])
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * GET /api/public/try-audio?texts[]=... - current audio for the guest
     * /try demo. The page ships with committed TTS MP3s so it works without
     * the backend; this lets it upgrade to the human recording the moment
     * one is approved, matched by exact Finnish text.
     */
    public function tryAudio(Request $request): JsonResponse
    {
        $texts = collect((array) $request->query('texts'))
            ->filter(fn ($t) => is_string($t) && $t !== '' && mb_strlen($t) <= 120)
            ->take(10)
            ->values();

        $audio = Sentence::whereIn('finnish_text', $texts)
            ->whereNotNull('audio_url')
            ->get(['finnish_text', 'audio_url'])
            // The same line can appear in several lessons; prefer a human take.
            ->sortBy(fn ($s) => str_starts_with($s->audio_url, '/audio/human/') ? 0 : 1)
            ->unique('finnish_text')
            ->pluck('audio_url', 'finnish_text');

        return response()->json(['audio' => $audio])
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function show(string $slug): JsonResponse
    {
        $lessons = Lesson::orderBy('order_index')->get();
        $index = $lessons->search(fn (Lesson $l) => Str::slug($l->title) === $slug);
        abort_if($index === false, 404);

        $lesson = $lessons[$index];
        $lesson->load(['sentences' => fn ($q) => $q->orderBy('id'), 'pattern']);

        $neighbor = fn (?Lesson $l) => $l ? [
            'slug' => Str::slug($l->title),
            'title' => $l->title,
            'level' => $l->level,
        ] : null;

        return response()->json([
            'lesson' => [
                'slug' => $slug,
                'title' => $lesson->title,
                'level' => $lesson->level,
                'pattern' => $lesson->pattern?->only('title', 'summary', 'examples'),
                'sentences' => $lesson->sentences->map(fn ($s) => [
                    'finnish_text' => $s->finnish_text,
                    'written_text' => $s->written_text,
                    'english_text' => $s->english_text,
                    'word_glosses' => $s->word_glosses,
                    'audio_url' => $s->audio_url,
                ])->values(),
            ],
            // Prev/next for internal linking - crawlers walk the whole path.
            'previous' => $neighbor($lessons[$index - 1] ?? null),
            'next' => $neighbor($lessons[$index + 1] ?? null),
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
