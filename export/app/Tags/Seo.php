<?php

namespace App\Tags;

use Statamic\Facades\Antlers;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Tags\Tags;

class Seo extends Tags
{
    /**
     * The {{ seo:meta }} tag.
     * Outputs meta tags for title, description, Open Graph, favicon, JSON-LD, noindex, and hreflang.
     */
    public function meta(): string
    {
        $entry = $this->context->get('page') ?? $this->context->get('id');
        $seo = GlobalSet::findByHandle('seo');
        $seoData = $seo?->inDefaultSite()->toArray() ?? [];

        $title = $this->resolveTitle($entry, $seoData);
        $description = $this->resolveDescription($entry, $seoData);
        $image = $this->resolveImage($entry, $seoData);
        $url = $this->resolveUrl($entry);
        $siteTitle = $this->parseAntlers($seoData['site_title'] ?? config('app.name'));
        $noIndex = $this->resolveNoIndex($entry, $seoData);

        $tags = [];

        if ($noIndex) {
            $tags[] = '<meta name="robots" content="noindex, nofollow">';
        }

        if ($title) {
            $tags[] = '<title>'.e($title).'</title>';
            $tags[] = '<meta name="description" content="'.e($description ?: '').'">';
        }

        $favicon = $this->resolveAssetUrl($seoData['favicon'] ?? null);
        if ($favicon) {
            $path = parse_url($favicon, PHP_URL_PATH);
            $type = ($path && str_ends_with($path, '.ico')) ? 'image/x-icon' : 'image/png';
            $tags[] = '<link rel="icon" href="'.e($favicon).'" type="'.e($type).'">';
        }

        if ($url) {
            $tags[] = '<link rel="canonical" href="'.e($url).'">';
        }

        $tags = array_merge($tags, $this->buildHreflangTags($entry, $seoData, $url));

        if ($title) {
            $tags[] = '<meta property="og:title" content="'.e($title).'">';
            $tags[] = '<meta property="og:type" content="website">';
            $tags[] = '<meta property="og:url" content="'.e($url ?: URL::makeAbsolute('/')).'">';
            if ($siteTitle) {
                $tags[] = '<meta property="og:site_name" content="'.e($siteTitle).'">';
            }
        }

        if ($description) {
            $tags[] = '<meta property="og:description" content="'.e($description).'">';
        }

        if ($image) {
            $tags[] = '<meta property="og:image" content="'.e($image).'">';
        }

        if ($title || $description || $image) {
            $tags[] = '<meta name="twitter:card" content="summary_large_image">';
            if ($title) {
                $tags[] = '<meta name="twitter:title" content="'.e($title).'">';
            }
            if ($description) {
                $tags[] = '<meta name="twitter:description" content="'.e($description).'">';
            }
            if ($image) {
                $tags[] = '<meta name="twitter:image" content="'.e($image).'">';
            }
        }

        $organizationSchema = $this->buildOrganizationSchema($seoData);
        if ($organizationSchema) {
            $tags[] = '<script type="application/ld+json">'.json_encode($organizationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'</script>';
        }

        return implode("\n        ", array_filter($tags));
    }

    private function resolveNoIndex(mixed $entry, array $seoData): bool
    {
        if ($seoData['no_index_site'] ?? false) {
            return true;
        }

        $entryNoIndex = $this->getEntryValue($entry, 'no_index');
        if ($entryNoIndex !== null) {
            return filter_var($entryNoIndex, FILTER_VALIDATE_BOOLEAN);
        }

        return false;
    }

    private function resolveSiteHandle(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        if (is_object($value) && method_exists($value, 'handle')) {
            return $value->handle();
        }
        if (is_array($value)) {
            $first = $value[0] ?? $value['value'] ?? null;

            return $first ? $this->resolveSiteHandle($first) : null;
        }

        return trim((string) $value) ?: null;
    }

    private function buildHreflangTags(mixed $entry, array $seoData, ?string $canonicalUrl): array
    {
        if (! Site::multiEnabled() || ! Site::hasMultiple()) {
            return [];
        }

        $xDefaultHandle = $this->resolveSiteHandle($seoData['x_default_site'] ?? null) ?: Site::current()->handle();
        $tags = [];

        foreach (Site::all() as $site) {
            $locale = $site->lang() ?? $site->locale();
            $siteUrl = $site->absoluteUrl();
            $alternateUrl = $this->resolveAlternateUrl($entry, $site, $canonicalUrl, $siteUrl);

            if (! $alternateUrl) {
                continue;
            }

            $tags[] = '<link rel="alternate" hreflang="'.e($locale).'" href="'.e($alternateUrl).'">';

            if ($site->handle() === $xDefaultHandle) {
                $tags[] = '<link rel="alternate" hreflang="x-default" href="'.e($alternateUrl).'">';
            }
        }

        return $tags;
    }

    private function resolveAlternateUrl(mixed $entry, $site, ?string $canonicalUrl, string $siteBaseUrl): ?string
    {
        if (! $entry || ! is_object($entry)) {
            return rtrim($siteBaseUrl, '/').'/';
        }

        if (method_exists($entry, 'in') && $localized = $entry->in($site->handle())) {
            if (method_exists($localized, 'absoluteUrl')) {
                return $localized->absoluteUrl();
            }
        }

        return null;
    }

    private function buildOrganizationSchema(array $seoData): ?array
    {
        $name = trim($seoData['organization_name'] ?? '');
        $logo = $this->resolveAssetUrl($seoData['organization_logo'] ?? null);

        if (! $name && ! $logo) {
            return null;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $name ?: config('app.name'),
        ];

        if ($logo) {
            $schema['logo'] = $logo;
        }

        $schema['url'] = URL::makeAbsolute('/');

        return $schema;
    }

    private function resolveAssetUrl(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        $asset = is_array($value) ? ($value[0] ?? $value) : $value;
        if (is_object($asset) && method_exists($asset, 'url')) {
            return URL::makeAbsolute($asset->url());
        }
        if (is_array($asset) && isset($asset['url'])) {
            return URL::makeAbsolute($asset['url']);
        }

        return null;
    }

    private function parseAntlers(?string $value): string
    {
        if (! $value || ! str_contains($value, '{{')) {
            return (string) $value;
        }

        return (string) Antlers::parse($value, $this->context->all());
    }

    private function resolveTitle(mixed $entry, array $seoData): string
    {
        $entryTitle = $this->getEntryValue($entry, 'seo_title') ?: $this->getEntryValue($entry, 'title');
        if ($entryTitle) {
            return $this->formatTitle($entryTitle, $seoData);
        }

        $siteTitle = $this->parseAntlers($seoData['site_title'] ?? config('app.name'));

        return $siteTitle ?: config('app.name');
    }

    private function resolveTitleSeparator(mixed $value): string
    {
        $value = is_array($value) ? ($value['value'] ?? $value[0] ?? 'pipe') : $value;
        $value = (string) ($value ?? 'pipe');

        return match ($value) {
            'dash' => ' - ',
            'dot' => ' · ',
            'endash' => ' – ',
            'emdash' => ' — ',
            default => ' | ',
        };
    }

    private function formatTitle(string $title, array $seoData): string
    {
        $siteTitle = $this->parseAntlers($seoData['site_title'] ?? config('app.name'));
        if (! $siteTitle) {
            return $title;
        }

        $separator = $this->resolveTitleSeparator($seoData['title_separator'] ?? 'pipe');

        return $title.$separator.$siteTitle;
    }

    private function resolveDescription(mixed $entry, array $seoData): string
    {
        $entryDesc = $this->getEntryValue($entry, 'seo_description')
            ?: $this->getEntryValue($entry, 'teaser_text');

        return trim($entryDesc ?: ($seoData['site_description'] ?? ''));
    }

    private function resolveImage(mixed $entry, array $seoData): ?string
    {
        $entryImage = $this->getEntryImage($entry, 'seo_image')
            ?: $this->getEntryImage($entry, 'teaser_image');

        if ($entryImage) {
            return $entryImage;
        }

        return $this->resolveAssetUrl($seoData['site_image'] ?? null);
    }

    private function resolveUrl(mixed $entry): ?string
    {
        if (! $entry) {
            return URL::makeAbsolute('/');
        }

        if (is_object($entry)) {
            if (method_exists($entry, 'absoluteUrl')) {
                return $entry->absoluteUrl();
            }
            if (method_exists($entry, 'url')) {
                return URL::makeAbsolute($entry->url());
            }
        }

        if (is_array($entry) && isset($entry['url'])) {
            return URL::makeAbsolute($entry['url']);
        }

        return null;
    }

    private function getEntryValue(mixed $entry, string $key): ?string
    {
        if (! $entry) {
            return null;
        }

        if (is_array($entry)) {
            return $entry[$key] ?? null;
        }

        if (is_object($entry) && method_exists($entry, 'get')) {
            $value = $entry->get($key);

            return $value !== null ? (string) $value : null;
        }

        return null;
    }

    private function getEntryImage(mixed $entry, string $key): ?string
    {
        if (! $entry) {
            return null;
        }

        if (is_object($entry) && method_exists($entry, 'value')) {
            $asset = $entry->value($key);
            if (! $asset) {
                return null;
            }
            $asset = is_array($asset) ? ($asset[0] ?? $asset) : $asset;
            if (is_object($asset) && method_exists($asset, 'url')) {
                return URL::makeAbsolute($asset->url());
            }
            if (is_array($asset) && isset($asset['url'])) {
                return URL::makeAbsolute($asset['url']);
            }
        }

        return null;
    }
}
