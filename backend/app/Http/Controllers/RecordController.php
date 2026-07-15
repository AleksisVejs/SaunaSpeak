<?php

namespace App\Http\Controllers;

use App\Models\Sentence;
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
     * GET /api/record/queue - items that neither have an approved human take
     * nor one waiting for review, in course order, plus progress counts.
     */
    public function queue(Request $request): JsonResponse
    {
        $this->ensureRecorder($request);

        $pendingSentenceIds = array_keys($this->pendingSentences());
        $pendingWordBases = array_keys($this->pendingWords());

        $sentences = Sentence::join('lessons', 'lessons.id', '=', 'sentences.lesson_id')
            ->orderBy('lessons.order_index')
            ->orderBy('sentences.id')
            ->get(['sentences.id', 'sentences.finnish_text', 'sentences.english_text', 'sentences.audio_url']);

        $openSentences = $sentences
            ->reject(fn ($s) => str_starts_with((string) $s->audio_url, '/audio/human/')
                || in_array($s->id, $pendingSentenceIds, true))
            ->values();

        $manifest = $this->manifest();
        $openWords = collect($manifest)
            ->reject(fn ($url, $word) => str_starts_with($url, '/audio/human/')
                || in_array($this->wordBase($word), $pendingWordBases, true))
            ->keys()
            ->sort()
            ->values();

        return response()->json([
            'sentence_total' => $sentences->count(),
            'sentence_done' => $sentences->count() - $openSentences->count(),
            'sentence_pending' => count($pendingSentenceIds),
            'sentences' => $openSentences->take(100),
            'word_total' => count($manifest),
            'word_done' => count($manifest) - $openWords->count(),
            'word_pending' => count($pendingWordBases),
            'words' => $openWords->map(fn ($w) => ['word' => $w, 'audio_url' => $manifest[$w]])->take(200),
        ]);
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

        return [
            'sentences' => $pendingSentences,
            'words' => $pendingWords,
            'live_sentences' => $liveSentences,
            'live_words' => $liveWords,
        ];
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

            return response()->json(['ok' => true]);
        }

        [$type, $key] = $this->validReviewTarget($request);
        $type === 'sentence' ? $this->approveSentence((int) $key) : $this->approveWordBase($this->wordBase($key));

        return response()->json(['ok' => true]);
    }

    /** POST /api/admin/recordings/reject - drop a pending take (back to the recorder's queue). */
    public function reject(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        [$type, $key] = $this->validReviewTarget($request);
        $glob = $type === 'sentence'
            ? public_path("audio/pending/sentence-{$key}.*")
            : public_path('audio/pending/words/'.$this->wordBase($key).'.*');

        foreach (File::glob($glob) as $file) {
            File::delete($file);
        }

        return response()->json(['ok' => true]);
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
            'type' => ['required', 'in:sentence,word'],
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
