<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Serves the built SPA shell with per-route head tags baked in.
 *
 * The Vue router rewrites canonical/title/description after hydration, but a
 * crawler's first pass sees raw HTML - and a single static shell means every
 * route would ship the homepage's canonical, declaring itself a duplicate of
 * '/'. Injecting here fixes the first pass without a prerender build; the
 * client-side rewrite still handles in-app navigation.
 *
 * Canonical is always self-referencing (the requested path), so it stays
 * correct for routes this class knows nothing about. Title and description
 * are upgraded only for the public pages worth a search snippet - they
 * mirror the meta in frontend/src/router/index.js, so change both together.
 */
class SpaController extends Controller
{
    private const SITE = 'https://saunaspeak.com';

    private const DEFAULT_DESCRIPTION = "Learn spoken Finnish (puhekieli) - 'mä oon', not 'minä olen'. Daily 5-minute sessions with audio, spaced repetition, AI conversation practice and real-life roleplay. The learning path is free, forever.";

    /** Public routes worth their own search snippet: path => [title, description]. */
    private const META = [
        '/' => [
            'SaunaSpeak - Learn Spoken Finnish (Puhekieli), the Finnish Finns Actually Speak',
            self::DEFAULT_DESCRIPTION,
        ],
        '/try' => [
            'Try spoken Finnish - no account needed - SaunaSpeak',
            'Hear and learn real spoken-Finnish sentences right now - no account, no signup. This is the Finnish Finns actually speak.',
        ],
        '/lessons' => [
            'Spoken Finnish lessons, free to read - SaunaSpeak',
            'Browse every SaunaSpeak lesson: real spoken Finnish (puhekieli) with the written form, word-by-word explanations and audio - from your first words to city slang, free to read.',
        ],
        '/pricing' => [
            'Pricing - the learning path is free forever - SaunaSpeak',
            'SaunaSpeak pricing: the full spoken-Finnish learning path is free forever. Löyly+ (€4.99/month) adds AI conversation practice and real-life roleplay.',
        ],
        '/compare' => [
            'Best apps to learn Finnish, compared honestly (2026) - SaunaSpeak',
            'SaunaSpeak vs Duolingo vs Pimsleur vs SuomiSpeak - an honest comparison of Finnish learning apps: spoken Finnish (puhekieli), free tiers, AI practice, grammar depth and price.',
        ],
        '/privacy' => [
            'Privacy policy - SaunaSpeak',
            'What SaunaSpeak stores, why, and your rights over your data.',
        ],
        '/terms' => [
            'Terms of service - SaunaSpeak',
            'The terms for using SaunaSpeak, the spoken-Finnish learning app.',
        ],
    ];

    public function __invoke(Request $request): Response
    {
        $spa = public_path('index.html');

        if (! file_exists($spa)) {
            return response(view('welcome')->render());
        }

        $path = '/'.trim($request->path(), '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');

        $html = file_get_contents($spa);
        [$title, $description] = $this->metaFor($path);

        // Self-referencing canonical: right for every route, including ones
        // not in META. The shell ships without a canonical tag so there is
        // nothing to collide with.
        $canonical = self::SITE.($path === '/' ? '/' : $path);
        $tag = '<link rel="canonical" href="'.e($canonical).'" />';

        $html = str_replace('</head>', '  '.$tag."\n  </head>", $html);

        if ($title !== null) {
            $html = preg_replace(
                '#<title>.*?</title>#s',
                '<title>'.e($title).'</title>',
                $html,
                1
            );
        }

        if ($description !== null) {
            $html = preg_replace(
                '#<meta name="description" content=".*?"\s*/?>#s',
                '<meta name="description" content="'.e($description).'" />',
                $html,
                1
            );
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            // The shell names hashed asset bundles, so it must never be
            // cached past a deploy.
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    /** @return array{0: ?string, 1: ?string} */
    private function metaFor(string $path): array
    {
        if (isset(self::META[$path])) {
            return self::META[$path];
        }

        if (str_starts_with($path, '/lessons/')) {
            return $this->lessonMeta(substr($path, strlen('/lessons/')));
        }

        // App routes (robots.txt disallows them) keep the shell's own tags.
        return [null, null];
    }

    /** @return array{0: ?string, 1: ?string} */
    private function lessonMeta(string $slug): array
    {
        // Slugs are derived from titles rather than stored, same as
        // SitemapController - the lessons table is small enough to scan.
        $lesson = Lesson::all()->first(fn (Lesson $l) => Str::slug($l->title) === $slug);

        if (! $lesson) {
            return [null, null];
        }

        $sentences = $lesson->sentences()->get();
        $count = $sentences->count();
        $first = $sentences->first();

        $description = $first
            ? "Learn \"{$first->finnish_text}\" and ".max($count - 1, 0).' more real spoken-Finnish (puhekieli) sentences with audio, written Finnish and word-by-word explanations.'
            : self::DEFAULT_DESCRIPTION;

        return [
            $lesson->title.' - spoken Finnish lesson - SaunaSpeak',
            $description,
        ];
    }
}
