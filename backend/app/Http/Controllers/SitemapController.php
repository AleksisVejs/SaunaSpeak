<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * /sitemap.xml, generated from the database so the /lessons/{slug} preview
 * pages appear the moment a lesson ships - no hand-maintained XML to forget.
 * (Replaces the static file that used to live in frontend/public.)
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $site = 'https://saunaspeak.com';

        $static = [
            ['loc' => '/', 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => '/try', 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => '/lessons', 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => '/pricing', 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => '/compare', 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => '/register', 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => '/privacy', 'changefreq' => 'yearly', 'priority' => '0.2'],
            ['loc' => '/terms', 'changefreq' => 'yearly', 'priority' => '0.2'],
            ['loc' => '/login', 'changefreq' => 'monthly', 'priority' => '0.3'],
        ];

        $urls = collect($static)->map(fn ($u) => $u + ['lastmod' => null]);

        $urls = $urls->concat(
            Lesson::orderBy('order_index')->get()->map(fn (Lesson $l) => [
                'loc' => '/lessons/'.Str::slug($l->title),
                'changefreq' => 'monthly',
                'priority' => '0.6',
                'lastmod' => $l->updated_at?->toDateString(),
            ])
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.$site.htmlspecialchars($u['loc'], ENT_XML1)."</loc>\n";
            if ($u['lastmod']) {
                $xml .= '    <lastmod>'.$u['lastmod']."</lastmod>\n";
            }
            $xml .= '    <changefreq>'.$u['changefreq']."</changefreq>\n";
            $xml .= '    <priority>'.$u['priority']."</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
