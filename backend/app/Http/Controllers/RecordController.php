<?php

namespace App\Http\Controllers;

use App\Models\Sentence;
use App\Support\Listening;
use App\Support\Transforms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * The recording studio backend, in two roles:
 *
 *  - RECORDER (is_recorder): sees the queue of TTS-voiced items and uploads
 *    takes. Takes land in public/audio/pending/ and change nothing yet.
 *  - ADMIN: reviews pending takes (listen, compare with the TTS version) and
 *    approves or rejects. Approving moves the file to public/audio/human/
 *    and flips the sentence's audio_url / the word manifest entry - only
 *    then does the app play the human voice.
 *
 * TTS files stay on disk as the instant-revert fallback, and audio:generate
 * never overwrites a human recording.
 */
class RecordController extends Controller
{
    /** Browser MediaRecorder output → stored extension. */
    private const EXT = [
        'audio/webm' => 'webm',
        'video/webm' => 'webm', // audio-only webm often sniffs as video/webm
        'audio/ogg' => 'ogg',
        'audio/mp4' => 'm4a',
        'video/mp4' => 'm4a', // Safari's audio-only mp4 sniffs as video/mp4
        'audio/mpeg' => 'mp3',
        'audio/x-wav' => 'wav',
        'audio/wav' => 'wav',
    ];

    /** Resolved once per request cycle: can we transcode to mp3? */
    private static ?bool $ffmpeg = null;

    private function ensureRecorder(Request $request): void
    {
        abort_unless($request->user()->is_recorder || $request->user()->is_admin, 403, 'Recording rights required.');
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()->is_admin, 403, 'Admins only.');
    }

    // ------------------------------------------------------------------
    //  Recorder side
    // ------------------------------------------------------------------

    /**
     * GET /api/record/queue?q=... - items that neither have an approved human
     * take nor one waiting for review, in course order, plus progress counts.
     *
     * Taivutus phrases ride in the sentences queue rather than a tab of their
     * own: to a recorder they're just another Finnish sentence to read. Only
     * phrases the course doesn't already say appear here - the rest reuse
     * their course sentence's recording, so nothing is ever read twice.
     *
     * `q` filters server-side because the queue is sliced before it's sent:
     * filtering client-side would only ever search the first slice.
     */
    public function queue(Request $request): JsonResponse
    {
        $this->ensureRecorder($request);

        $q = trim((string) $request->query('q'));
        $matches = fn (string ...$fields) => $q === '' || array_filter(
            $fields,
            fn ($f) => mb_stripos($f, $q) !== false,
        ) !== [];

        // "Robot first": a clip already voiced by ElevenLabs sounds decent, so
        // a native's time is better spent replacing the plain edge-tts ones.
        // On by default; the recorder can switch it off to also see the
        // ElevenLabs clips (which a human take still improves on).
        $robotFirst = $request->boolean('robot_first', true);
        $isEleven = fn (?string $url) => str_starts_with((string) $url, '/audio/eleven/');

        $pendingSentenceIds = array_keys($this->pendingSentences());
        $pendingWordBases = array_keys($this->pendingWords());
        $pendingPhraseBases = array_keys($this->pendingPhrases());

        $sentences = Sentence::join('lessons', 'lessons.id', '=', 'sentences.lesson_id')
            ->orderBy('lessons.order_index')
            ->orderBy('sentences.id')
            ->get(['sentences.id', 'sentences.finnish_text', 'sentences.english_text', 'sentences.audio_url'])
            ->map(fn (Sentence $s) => [
                'kind' => 'sentence',
                'id' => $s->id,
                'finnish_text' => $s->finnish_text,
                'english_text' => $s->english_text,
                'audio_url' => $s->audio_url,
                'tier' => $isEleven($s->audio_url) ? 'eleven' : 'tts',
            ]);

        $phrases = collect(Transforms::ownPhrases())->map(fn (array $p) => [
            'kind' => 'phrase',
            'id' => $p['base'],
            'finnish_text' => $p['text'],
            'english_text' => 'Taivutus drill',
            'audio_url' => $p['audio_url'],
            'tier' => $isEleven($p['audio_url']) ? 'eleven' : 'tts',
        ]);

        // Phrases first: the queue is sliced at 100, and behind 550-odd course
        // sentences they'd be invisible until the very end of the project.
        // They're also the shorter job - 42 lines finishes in one sitting.
        $all = $phrases->concat($sentences);

        $open = $all
            ->reject(fn (array $s) => str_starts_with((string) $s['audio_url'], '/audio/human/')
                || ($s['kind'] === 'sentence' && in_array($s['id'], $pendingSentenceIds, true))
                || ($s['kind'] === 'phrase' && in_array($s['id'], $pendingPhraseBases, true)))
            ->values();

        // How much of what's left is still robot vs already ElevenLabs - lets
        // the studio show "40 robot · 12 ElevenLabs" and hide the toggle when
        // there's no ElevenLabs coverage to filter.
        $sentenceEleven = $open->filter(fn (array $s) => $s['tier'] === 'eleven')->count();

        $openSentences = $robotFirst
            ? $open->filter(fn (array $s) => $s['tier'] === 'tts')->values()
            : $open;

        $manifest = $this->manifest();
        $openWordEntries = collect($manifest)
            ->reject(fn ($url, $word) => str_starts_with($url, '/audio/human/')
                || in_array($this->wordBase($word), $pendingWordBases, true))
            ->map(fn ($url, $word) => ['word' => $word, 'audio_url' => $url, 'tier' => $isEleven($url) ? 'eleven' : 'tts'])
            ->sortKeys()
            ->values();

        $wordEleven = $openWordEntries->filter(fn (array $w) => $w['tier'] === 'eleven')->count();

        $openWords = $robotFirst
            ? $openWordEntries->filter(fn (array $w) => $w['tier'] === 'tts')->values()
            : $openWordEntries;

        // Counts describe the whole corpus; only the returned slice is filtered,
        // so searching never makes the progress bar lie.
        return response()->json([
            'sentence_total' => $all->count(),
            'sentence_done' => $all->count() - $open->count(),
            'sentence_pending' => count($pendingSentenceIds) + count($pendingPhraseBases),
            'sentence_eleven' => $sentenceEleven,
            'sentences' => $openSentences
                ->filter(fn (array $s) => $matches($s['finnish_text'], (string) $s['english_text']))
                ->take(100)
                ->values(),
            'sentence_matches' => $openSentences->filter(fn (array $s) => $matches($s['finnish_text'], (string) $s['english_text']))->count(),
            'word_total' => count($manifest),
            'word_done' => count($manifest) - $openWordEntries->count(),
            'word_pending' => count($pendingWordBases),
            'word_eleven' => $wordEleven,
            'words' => $openWords
                ->filter(fn (array $w) => $matches($w['word']))
                ->take(200)
                ->values(),
            'word_matches' => $openWords->filter(fn (array $w) => $matches($w['word']))->count(),
        ]);
    }

    /** POST /api/record/phrase/{base} - submit a take for a Taivutus phrase. */
    public function storePhrase(Request $request, string $base): JsonResponse
    {
        $this->ensureRecorder($request);
        abort_unless(Transforms::hasPhrase($base), 404, 'Unknown phrase.');

        $file = $this->validAudio($request);
        $stored = $this->store($file, public_path('audio/pending'), $base);

        return response()->json(['phrase' => $base, 'pending_url' => "/audio/pending/{$stored}"]);
    }

    /** DELETE /api/record/phrase/{base} - retire a LIVE phrase take, back to TTS. */
    public function revertPhrase(Request $request, string $base): JsonResponse
    {
        $this->ensureAdmin($request);
        abort_unless(Transforms::hasPhrase($base), 404, 'Unknown phrase.');

        foreach (File::glob(public_path("audio/human/{$base}.*")) as $old) {
            File::delete($old);
        }

        return response()->json(['phrase' => $base]);
    }

    /** POST /api/record/sentence/{id} - submit a take for review. */
    public function storeSentence(Request $request, int $id): JsonResponse
    {
        $this->ensureRecorder($request);
        Sentence::findOrFail($id);

        $file = $this->validAudio($request);
        $stored = $this->store($file, public_path('audio/pending'), "sentence-{$id}");

        return response()->json(['sentence_id' => $id, 'pending_url' => "/audio/pending/{$stored}"]);
    }

    /**
     * GET /api/record/listening - the conversation queue, grouped by scene and
     * then by SPEAKER.
     *
     * The grouping is the point. A scene is two people talking, so its lines
     * must be recorded by two different voices - a flat list of lines is how
     * you end up with a "dialogue" where both speakers are the same person.
     * Each speaker block carries its own name, voice and progress so it's
     * obvious at a glance who still needs recording, and by whom.
     */
    public function listeningQueue(Request $request): JsonResponse
    {
        $this->ensureRecorder($request);

        $pending = $this->pendingListening();
        $scenes = [];

        foreach (Listening::all() as $scene) {
            $speakers = [];

            foreach ($scene['lines'] as $index => $line) {
                $key = $line['who'];
                $meta = $scene['speakers'][$key] ?? ['name' => $key, 'voice' => 'male'];
                $base = Listening::baseName($scene['id'], $index);

                // Three states, and they must be distinguishable: live (an
                // approved human take is playing), pending (recorded, waiting
                // for review) and tts (still the synthetic voice).
                $live = str_starts_with((string) $line['audio_url'], '/audio/human/');
                $state = $live ? 'live' : (isset($pending[$base]) ? 'pending' : 'tts');

                $speakers[$key] ??= [
                    'key' => $key,
                    'name' => $meta['name'] ?? $key,
                    'voice' => $meta['voice'] ?? 'male',
                    'role' => $meta['role'] ?? '',
                    'lines' => [],
                    'total' => 0,
                    'done' => 0,
                    'pending' => 0,
                ];

                $speakers[$key]['lines'][] = [
                    'index' => $index,
                    'fi' => $line['fi'],
                    'en' => $line['en'],
                    'current_url' => $line['audio_url'],
                    'pending_url' => isset($pending[$base]) ? '/audio/pending/'.basename($pending[$base]) : null,
                    'state' => $state,
                ];
                $speakers[$key]['total']++;
                if ($state === 'live') {
                    $speakers[$key]['done']++;
                } elseif ($state === 'pending') {
                    $speakers[$key]['pending']++;
                }
            }

            $scenes[] = [
                'id' => $scene['id'],
                'emoji' => $scene['emoji'] ?? '🎧',
                'title' => $scene['title'],
                'level' => $scene['level'] ?? 'A1',
                'speakers' => array_values($speakers),
                'total' => count($scene['lines']),
                'done' => array_sum(array_column($speakers, 'done')),
            ];
        }

        return response()->json([
            'scenes' => $scenes,
            'line_total' => array_sum(array_column($scenes, 'total')),
            'line_done' => array_sum(array_column($scenes, 'done')),
        ]);
    }

    /** POST /api/record/listening/{scene}/{index} - submit a take for review. */
    public function storeListening(Request $request, string $scene, int $index): JsonResponse
    {
        $this->ensureRecorder($request);
        abort_unless(Listening::hasLine($scene, $index), 404, 'Unknown line.');

        $file = $this->validAudio($request);
        $stored = $this->store($file, public_path('audio/pending'), Listening::baseName($scene, $index));

        return response()->json([
            'scene' => $scene,
            'index' => $index,
            'pending_url' => "/audio/pending/{$stored}",
        ]);
    }

    /** DELETE /api/record/listening/{scene}/{index} - retire a LIVE take, back to TTS. */
    public function revertListening(Request $request, string $scene, int $index): JsonResponse
    {
        $this->ensureAdmin($request);
        abort_unless(Listening::hasLine($scene, $index), 404, 'Unknown line.');

        $base = Listening::baseName($scene, $index);
        foreach (File::glob(public_path("audio/human/{$base}.*")) as $old) {
            File::delete($old);
        }

        return response()->json([
            'scene' => $scene,
            'index' => $index,
            'audio_url' => Listening::audioUrl($scene, $index),
        ]);
    }

    /** POST /api/record/word - submit a word take for review. */
    public function storeWord(Request $request): JsonResponse
    {
        $this->ensureRecorder($request);

        $word = mb_strtolower(trim((string) $request->input('word')));
        abort_unless($word !== '' && array_key_exists($word, $this->manifest()), 422, 'Unknown word.');

        $file = $this->validAudio($request);
        $stored = $this->store($file, public_path('audio/pending/words'), $this->wordBase($word));

        return response()->json(['word' => $word, 'pending_url' => "/audio/pending/words/{$stored}"]);
    }

    /**
     * GET /api/record/submitted - the recorder's own picture: takes waiting
     * for review and takes already live. From here anything can be
     * re-recorded (a new take goes back through review).
     */
    public function submitted(Request $request): JsonResponse
    {
        $this->ensureRecorder($request);

        return response()->json($this->submissionState());
    }

    // ------------------------------------------------------------------
    //  Admin side: review, approve, reject, revert
    // ------------------------------------------------------------------

    /** GET /api/admin/recordings - everything pending review AND everything live. */
    public function pending(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json($this->submissionState());
    }

    /**
     * The full recording picture, shared by the recorder's "my takes" view
     * and the admin review panel: pending takes (with the current audio for
     * comparison) and live human recordings.
     */
    private function submissionState(): array
    {
        $sentenceFiles = $this->pendingSentences();
        $pendingSentences = Sentence::whereIn('id', array_keys($sentenceFiles))
            ->get(['id', 'finnish_text', 'english_text', 'audio_url'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'finnish_text' => $s->finnish_text,
                'english_text' => $s->english_text,
                'current_url' => $s->audio_url,
                'pending_url' => '/audio/pending/'.basename($sentenceFiles[$s->id]),
            ])
            ->values();

        $manifest = $this->manifest();
        $baseToWord = collect($manifest)->keys()->mapWithKeys(fn ($w) => [$this->wordBase($w) => $w]);
        $pendingWords = collect($this->pendingWords())
            ->map(function ($path, $base) use ($baseToWord, $manifest) {
                $word = $baseToWord[$base] ?? null;

                return $word === null ? null : [
                    'word' => $word,
                    'current_url' => $manifest[$word],
                    'pending_url' => '/audio/pending/words/'.basename($path),
                ];
            })
            ->filter()
            ->values();

        $liveSentences = Sentence::where('audio_url', 'like', '/audio/human/%')
            ->orderBy('id')
            ->get(['id', 'finnish_text', 'english_text', 'audio_url'])
            ->values();

        $liveWords = collect($manifest)
            ->filter(fn ($url) => str_starts_with($url, '/audio/human/'))
            ->map(fn ($url, $word) => ['word' => $word, 'audio_url' => $url])
            ->values();

        [$pendingListening, $liveListening] = $this->listeningSubmissionState();
        [$pendingPhrases, $livePhrases] = $this->phraseSubmissionState();

        return [
            'sentences' => $pendingSentences,
            'words' => $pendingWords,
            'listening' => $pendingListening,
            'phrases' => $pendingPhrases,
            'live_sentences' => $liveSentences,
            'live_words' => $liveWords,
            'live_listening' => $liveListening,
            'live_phrases' => $livePhrases,
        ];
    }

    /**
     * Taivutus phrase takes, pending and live. Reviewed alongside sentences -
     * they're the same job: one Finnish sentence, read out loud.
     *
     * @return array{0: array, 1: array} [pending, live]
     */
    private function phraseSubmissionState(): array
    {
        $pendingFiles = $this->pendingPhrases();
        $pending = [];
        $live = [];

        foreach (Transforms::ownPhrases() as $phrase) {
            $row = ['base' => $phrase['base'], 'text' => $phrase['text']];

            if (isset($pendingFiles[$phrase['base']])) {
                $pending[] = $row + [
                    'current_url' => $phrase['audio_url'],
                    'pending_url' => '/audio/pending/'.basename($pendingFiles[$phrase['base']]),
                ];
            } elseif (str_starts_with((string) $phrase['audio_url'], '/audio/human/')) {
                $live[] = $row + ['audio_url' => $phrase['audio_url']];
            }
        }

        return [$pending, $live];
    }

    /**
     * Conversation lines waiting for review, and the ones already live. Each
     * carries its speaker so a reviewer can hear whether the voice actually
     * matches the character - a scene recorded by one person for both parts
     * is the failure this whole split exists to prevent.
     *
     * @return array{0: array, 1: array} [pending, live]
     */
    private function listeningSubmissionState(): array
    {
        $pendingFiles = $this->pendingListening();
        $pending = [];
        $live = [];

        foreach (Listening::all() as $scene) {
            foreach ($scene['lines'] as $index => $line) {
                $meta = $scene['speakers'][$line['who']] ?? [];
                $base = Listening::baseName($scene['id'], $index);

                $row = [
                    'scene' => $scene['id'],
                    'scene_title' => $scene['title'],
                    'index' => $index,
                    'fi' => $line['fi'],
                    'en' => $line['en'],
                    'speaker' => $meta['name'] ?? $line['who'],
                    'voice' => $meta['voice'] ?? 'male',
                ];

                if (isset($pendingFiles[$base])) {
                    $pending[] = $row + [
                        'current_url' => $line['audio_url'],
                        'pending_url' => '/audio/pending/'.basename($pendingFiles[$base]),
                    ];
                } elseif (str_starts_with((string) $line['audio_url'], '/audio/human/')) {
                    $live[] = $row + ['audio_url' => $line['audio_url']];
                }
            }
        }

        return [$pending, $live];
    }

    /**
     * POST /api/admin/recordings/approve - promote pending takes to live.
     * Body: { type: sentence|word, key: id|word } or { all: true }.
     */
    public function approve(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        if ($request->boolean('all')) {
            foreach (array_keys($this->pendingSentences()) as $id) {
                $this->approveSentence($id);
            }
            foreach (array_keys($this->pendingWords()) as $base) {
                $this->approveWordBase($base);
            }
            foreach (array_keys($this->pendingListening()) as $base) {
                $this->approveListeningBase($base);
            }
            foreach (array_keys($this->pendingPhrases()) as $base) {
                $this->approvePhraseBase($base);
            }

            return response()->json(['ok' => true]);
        }

        [$type, $key] = $this->validReviewTarget($request);

        match ($type) {
            'sentence' => $this->approveSentence((int) $key),
            'listening' => $this->approveListeningBase($this->listeningBaseFromKey($key)),
            'phrase' => $this->approvePhraseBase($this->phraseBaseFromKey($key)),
            default => $this->approveWordBase($this->wordBase($key)),
        };

        return response()->json(['ok' => true]);
    }

    /** POST /api/admin/recordings/reject - drop a pending take (back to the recorder's queue). */
    public function reject(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        [$type, $key] = $this->validReviewTarget($request);

        $glob = match ($type) {
            'sentence' => public_path("audio/pending/sentence-{$key}.*"),
            'listening' => public_path('audio/pending/'.$this->listeningBaseFromKey($key).'.*'),
            'phrase' => public_path('audio/pending/'.$this->phraseBaseFromKey($key).'.*'),
            default => public_path('audio/pending/words/'.$this->wordBase($key).'.*'),
        };

        foreach (File::glob($glob) as $file) {
            File::delete($file);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * A phrase key IS its base name ("phrase-ab12cd34ef") - but it lands in a
     * glob, so it must be proven to name a real phrase before it goes near the
     * filesystem, not merely look plausible.
     */
    private function phraseBaseFromKey(string $key): string
    {
        abort_unless(Transforms::hasPhrase($key), 404, 'Unknown phrase.');

        return $key;
    }

    /** Review key "kahvilassa:3" → the shared base name for that line. */
    private function listeningBaseFromKey(string $key): string
    {
        [$scene, $index] = array_pad(explode(':', $key, 2), 2, null);
        abort_unless($scene !== null && $index !== null && ctype_digit($index), 422, 'Bad listening key.');
        abort_unless(Listening::hasLine($scene, (int) $index), 404, 'Unknown line.');

        return Listening::baseName($scene, (int) $index);
    }

    /** DELETE /api/record/sentence/{id} - retire a LIVE human take, back to TTS. */
    public function revertSentence(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);
        $sentence = Sentence::findOrFail($id);

        foreach (File::glob(public_path("audio/human/sentence-{$id}.*")) as $old) {
            File::delete($old);
        }

        $sentence->update([
            'audio_url' => File::exists(public_path("audio/sentence-{$id}.mp3")) ? "/audio/sentence-{$id}.mp3" : null,
        ]);

        return response()->json(['sentence_id' => $id, 'audio_url' => $sentence->audio_url]);
    }

    /** DELETE /api/record/word?word=... - retire a LIVE human word take, back to TTS. */
    public function revertWord(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $word = mb_strtolower(trim((string) $request->input('word')));
        $manifest = $this->manifest();
        abort_unless($word !== '' && array_key_exists($word, $manifest), 422, 'Unknown word.');

        foreach (File::glob(public_path('audio/human/words/'.$this->wordBase($word).'.*')) as $old) {
            File::delete($old);
        }

        $ttsName = $this->wordBase($word).'.mp3';
        if (File::exists(public_path("audio/words/{$ttsName}"))) {
            $manifest[$word] = "/audio/words/{$ttsName}";
        } else {
            unset($manifest[$word]); // frontend falls back to browser TTS
        }
        $this->writeManifest($manifest);

        return response()->json(['word' => $word, 'audio_url' => $manifest[$word] ?? null]);
    }

    // ------------------------------------------------------------------
    //  Internals
    // ------------------------------------------------------------------

    private function approveSentence(int $id): void
    {
        $pending = $this->pendingSentences()[$id] ?? null;
        if ($pending === null) {
            return;
        }

        $sentence = Sentence::find($id);
        if ($sentence === null) {
            File::delete($pending); // orphaned take for a deleted sentence

            return;
        }

        $dir = public_path('audio/human');
        File::ensureDirectoryExists($dir);
        foreach (File::glob("{$dir}/sentence-{$id}.*") as $old) {
            File::delete($old);
        }

        $name = basename($pending);
        File::move($pending, "{$dir}/{$name}");
        $sentence->update(['audio_url' => "/audio/human/{$name}"]);
    }

    private function approveWordBase(string $base): void
    {
        $pending = $this->pendingWords()[$base] ?? null;
        if ($pending === null) {
            return;
        }

        $manifest = $this->manifest();
        $word = collect($manifest)->keys()->first(fn ($w) => $this->wordBase($w) === $base);
        if ($word === null) {
            File::delete($pending); // orphaned take for a word no longer in the course

            return;
        }

        $dir = public_path('audio/human/words');
        File::ensureDirectoryExists($dir);
        foreach (File::glob("{$dir}/{$base}.*") as $old) {
            File::delete($old);
        }

        $name = basename($pending);
        File::move($pending, "{$dir}/{$name}");
        $manifest[$word] = "/audio/human/words/{$name}";
        $this->writeManifest($manifest);
    }

    /** @return array{0: string, 1: string} [type, key] */
    private function validReviewTarget(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'in:sentence,word,listening,phrase'],
            'key' => ['required', 'string', 'max:64'],
        ]);

        return [$data['type'], mb_strtolower(trim($data['key']))];
    }

    /** @return array<int, string> sentence id → pending file path */
    private function pendingSentences(): array
    {
        $map = [];
        foreach (File::glob(public_path('audio/pending/sentence-*.*')) as $path) {
            if (preg_match('/sentence-(\d+)\./', basename($path), $m)) {
                $map[(int) $m[1]] = $path;
            }
        }

        return $map;
    }

    /** @return array<string, string> word base (slug-hash) → pending file path */
    private function pendingWords(): array
    {
        $map = [];
        foreach (File::glob(public_path('audio/pending/words/*.*')) as $path) {
            $map[pathinfo($path, PATHINFO_FILENAME)] = $path;
        }

        return $map;
    }

    /**
     * @return array<string, string> listening base ("listening-{scene}-{i}") → pending path
     *
     * Conversation takes share the pending/ directory with sentence takes;
     * the "listening-" prefix is what keeps the two globs from colliding.
     */
    private function pendingListening(): array
    {
        $map = [];
        foreach (File::glob(public_path('audio/pending/listening-*.*')) as $path) {
            $map[pathinfo($path, PATHINFO_FILENAME)] = $path;
        }

        return $map;
    }

    /**
     * @return array<string, string> phrase base → pending file path
     *
     * Phrase takes share audio/pending/ with sentence and conversation takes;
     * the "phrase-" prefix is what keeps the three globs apart.
     */
    private function pendingPhrases(): array
    {
        $map = [];
        foreach (File::glob(public_path('audio/pending/phrase-*.*')) as $path) {
            $map[pathinfo($path, PATHINFO_FILENAME)] = $path;
        }

        return $map;
    }

    private function approvePhraseBase(string $base): void
    {
        $pending = $this->pendingPhrases()[$base] ?? null;
        if ($pending === null) {
            return;
        }

        if (! Transforms::hasPhrase($base)) {
            File::delete($pending); // orphaned take for a phrase no longer drilled

            return;
        }

        $dir = public_path('audio/human');
        File::ensureDirectoryExists($dir);
        foreach (File::glob("{$dir}/{$base}.*") as $old) {
            File::delete($old);
        }

        // No DB write: Transforms resolves audio from disk on read, so moving
        // the file IS the approval - and every drill saying this sentence
        // picks it up at once.
        File::move($pending, "{$dir}/".basename($pending));
    }

    private function approveListeningBase(string $base): void
    {
        $pending = $this->pendingListening()[$base] ?? null;
        if ($pending === null) {
            return;
        }

        $parsed = Listening::parseBaseName($base);
        if ($parsed === null || ! Listening::hasLine($parsed[0], $parsed[1])) {
            File::delete($pending); // orphaned take for a line that no longer exists

            return;
        }

        $dir = public_path('audio/human');
        File::ensureDirectoryExists($dir);
        foreach (File::glob("{$dir}/{$base}.*") as $old) {
            File::delete($old);
        }

        // No DB write needed: Listening::audioUrl() prefers audio/human/ on
        // sight, so moving the file IS the approval.
        File::move($pending, "{$dir}/".basename($pending));
    }

    /** Same slug+hash scheme audio:generate uses - the files pair up. */
    private function wordBase(string $word): string
    {
        return Str::slug($word).'-'.substr(md5($word), 0, 6);
    }

    private function validAudio(Request $request): UploadedFile
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:15360'], // 15 MB; takes are seconds long
        ]);

        $file = $request->file('audio');
        abort_unless(isset(self::EXT[$file->getMimeType()]), 422, 'Unsupported audio format.');

        return $file;
    }

    /**
     * Store a take as {base}.mp3 when ffmpeg can transcode, else in the
     * browser's native format. Any previous take of the same item (any
     * extension) is replaced. Returns the stored filename.
     */
    private function store(UploadedFile $file, string $dir, string $base): string
    {
        File::ensureDirectoryExists($dir);
        foreach (File::glob("{$dir}/{$base}.*") as $old) {
            File::delete($old);
        }

        if ($this->ffmpegAvailable()) {
            $target = "{$dir}/{$base}.mp3";
            $result = Process::run([
                config('services.ffmpeg.bin', 'ffmpeg'),
                '-y', '-i', $file->getRealPath(),
                '-vn', '-codec:a', 'libmp3lame', '-qscale:a', '4',
                $target,
            ]);
            if ($result->successful() && File::exists($target)) {
                return "{$base}.mp3";
            }
        }

        $ext = self::EXT[$file->getMimeType()];
        $file->move($dir, "{$base}.{$ext}");

        return "{$base}.{$ext}";
    }

    private function ffmpegAvailable(): bool
    {
        if (self::$ffmpeg === null) {
            try {
                self::$ffmpeg = Process::run([config('services.ffmpeg.bin', 'ffmpeg'), '-version'])->successful();
            } catch (\Throwable) {
                self::$ffmpeg = false;
            }
        }

        return self::$ffmpeg;
    }

    private function manifest(): array
    {
        $path = public_path('audio/words.json');

        return File::exists($path) ? (json_decode(File::get($path), true) ?: []) : [];
    }

    private function writeManifest(array $manifest): void
    {
        File::put(
            public_path('audio/words.json'),
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        );
    }
}
