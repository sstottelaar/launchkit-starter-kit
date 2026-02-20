<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $seo = GlobalSet::findByHandle('seo');
        $seoData = $seo?->inDefaultSite()->toArray() ?? [];

        if ($seoData['no_index_site'] ?? false) {
            return $this->emptySitemap();
        }

        $collections = config('sitemap.collections', ['pages', 'news']);
        $entries = Entry::query()
            ->whereIn('collection', $collections)
            ->whereStatus('published')
            ->get()
            ->filter(function ($entry) {
                if (filter_var($entry->get('no_index'), FILTER_VALIDATE_BOOLEAN)) {
                    return false;
                }

                $url = $entry->absoluteUrl();

                return $url !== null && $url !== '';
            });

        $urls = $entries->map(function ($entry) {
            $loc = $entry->absoluteUrl();
            $lastmod = $this->formatLastmod($entry);
            $changefreq = $entry->get('sitemap_changefreq') ?: 'weekly';
            $priority = $entry->get('sitemap_priority') ?: '0.5';

            return compact('loc', 'lastmod', 'changefreq', 'priority');
        })->values()->all();

        $xml = $this->buildXml($urls);

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function emptySitemap(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".
            '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function formatLastmod($entry): ?string
    {
        $updated = $entry->get('updated_at');

        if (! $updated) {
            return null;
        }

        $carbon = \Illuminate\Support\Carbon::createFromTimestamp($updated);

        return $carbon->toW3cString();
    }

    private function buildXml(array $urls): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($urls as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.e($url['loc']).'</loc>';
            if ($url['lastmod']) {
                $lines[] = '    <lastmod>'.e($url['lastmod']).'</lastmod>';
            }
            $lines[] = '    <changefreq>'.e($url['changefreq']).'</changefreq>';
            $lines[] = '    <priority>'.e($url['priority']).'</priority>';
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines);
    }
}
