<?php

namespace LensForLaravel\LensForLaravel\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Spatie\Browsershot\Browsershot;
use Throwable;

class SiteCrawler
{
    protected array $visited = [];

    protected array $toVisit = [];

    protected string $baseUrl = '';

    protected string $host = '';

    protected string $scheme = '';

    /**
     * Crawl the website starting from the given URL and return all discovered internal page URLs.
     *
     * Strategy:
     *  1. Try to seed URLs from sitemap.xml / sitemap_index.xml (instant, covers all product pages etc.)
     *  2. BFS crawl by following <a href> links discovered via plain HTTP.
     *  3. Optionally render JavaScript in Chromium before falling back to plain HTTP.
     */
    public function crawl(string $url, int $maxPages = 50): array
    {
        $this->baseUrl = rtrim($url, '/');

        $parsed = parse_url($this->baseUrl);
        $this->host = $parsed['host'] ?? '';
        $this->scheme = $parsed['scheme'] ?? 'https';

        $this->toVisit = [$this->baseUrl];
        $this->visited = [];

        // Seed additional URLs from sitemap before BFS crawl.
        // This is the key to discovering dynamically-routed pages (products, blog posts, etc.)
        // that would never appear in `artisan route:list`.
        $this->seedFromSitemap();

        while (! empty($this->toVisit) && count($this->visited) < $maxPages) {
            $currentUrl = array_shift($this->toVisit);

            if (in_array($currentUrl, $this->visited)) {
                continue;
            }

            $this->visited[] = $currentUrl;

            try {
                $links = $this->extractLinks($currentUrl);

                foreach ($links as $link) {
                    if ($this->isInternalPage($link)
                        && ! in_array($link, $this->visited)
                        && ! in_array($link, $this->toVisit)) {
                        $this->toVisit[] = $link;
                    }
                }
            } catch (Throwable) {
                // If one page fails, continue with the rest.
            }
        }

        return $this->visited;
    }

    /**
     * Attempt to populate the crawl queue from sitemap.xml / sitemap_index.xml.
     * Many Laravel apps (spatie/laravel-sitemap etc.) expose all their URLs here,
     * including slugged product/category/post pages.
     */
    protected function seedFromSitemap(): void
    {
        $candidates = [
            $this->baseUrl.'/sitemap.xml',
            $this->baseUrl.'/sitemap_index.xml',
            $this->baseUrl.'/sitemaps/sitemap.xml',
        ];

        foreach ($candidates as $sitemapUrl) {
            try {
                $response = $this->httpRequest(5)->get($sitemapUrl);

                if (! $response->successful()) {
                    continue;
                }

                $contentType = $response->header('Content-Type') ?? '';
                if (! str_contains($contentType, 'xml') && ! str_contains($contentType, 'text')) {
                    continue;
                }

                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($response->body());
                libxml_clear_errors();

                if ($xml === false) {
                    continue;
                }

                // Sitemap index — each <sitemap> points to another sitemap file
                foreach ($xml->sitemap ?? [] as $child) {
                    $this->parseSitemapXml((string) $child->loc);
                }

                // Regular sitemap — each <url><loc> is a page URL
                foreach ($xml->url ?? [] as $entry) {
                    $loc = rtrim((string) $entry->loc, '/');
                    if ($loc && $this->isInternalPage($loc) && ! in_array($loc, $this->toVisit)) {
                        $this->toVisit[] = $loc;
                    }
                }

                // Found a valid sitemap — no need to check further candidates.
                return;
            } catch (Throwable) {
                continue;
            }
        }
    }

    protected function parseSitemapXml(string $sitemapUrl): void
    {
        try {
            $response = $this->httpRequest(5)->get($sitemapUrl);

            if (! $response->successful()) {
                return;
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response->body());
            libxml_clear_errors();

            if ($xml === false) {
                return;
            }

            foreach ($xml->url ?? [] as $entry) {
                $loc = rtrim((string) $entry->loc, '/');
                if ($loc && $this->isInternalPage($loc) && ! in_array($loc, $this->toVisit)) {
                    $this->toVisit[] = $loc;
                }
            }
        } catch (Throwable) {
            // Ignore broken child sitemaps.
        }
    }

    /**
     * Fetch a page via HTTP and extract all internal <a href> links.
     * This deliberately avoids headless Chrome — plain HTTP is sufficient for link discovery.
     */
    protected function extractLinks(string $url): array
    {
        if (config('lens-for-laravel.crawler_render_javascript', false)) {
            $browserLinks = $this->extractLinksWithBrowser($url);

            if (! empty($browserLinks)) {
                return $browserLinks;
            }
        }

        $response = $this->httpRequest(10)
            ->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
            ->get($url);

        if (! $response->successful()) {
            return [];
        }

        $contentType = $response->header('Content-Type') ?? '';
        if (! str_contains($contentType, 'html')) {
            return [];
        }

        return $this->parseLinksFromHtml($response->body(), $url);
    }

    protected function extractLinksWithBrowser(string $url): array
    {
        try {
            $json = app(HttpsClientConfiguration::class)
                ->configureBrowser(Browsershot::url($url))
                ->noSandbox()
                ->waitUntilNetworkIdle()
                ->evaluate(<<<'JS'
                    JSON.stringify(
                        Array.from(document.querySelectorAll('a[href]'))
                            .map((anchor) => anchor.getAttribute('href'))
                            .filter(Boolean)
                    )
                JS);

            $hrefs = json_decode($json, true);
            if (! is_array($hrefs)) {
                return [];
            }

            return array_values(array_unique(array_filter(array_map(
                fn ($href) => is_string($href) ? $this->toAbsoluteUrl(trim($href), $url) : null,
                $hrefs
            ))));
        } catch (Throwable) {
            return [];
        }
    }

    protected function httpRequest(int $timeout): PendingRequest
    {
        return app(HttpsClientConfiguration::class)->configureHttp(
            Http::timeout($timeout)
        );
    }

    protected function parseLinksFromHtml(string $html, string $baseUrl): array
    {
        $dom = new DOMDocument;

        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $anchors = $xpath->query('//a[@href]');

        $links = [];

        foreach ($anchors as $anchor) {
            $href = trim($anchor->getAttribute('href'));

            if (empty($href)) {
                continue;
            }

            $absolute = $this->toAbsoluteUrl($href, $baseUrl);

            if ($absolute !== null) {
                $links[] = $absolute;
            }
        }

        return array_unique($links);
    }

    /**
     * Resolve a potentially relative href to an absolute URL.
     * Returns null for non-navigable hrefs (javascript:, mailto:, tel:, #fragment-only).
     */
    protected function toAbsoluteUrl(string $href, string $pageUrl): ?string
    {
        if (str_starts_with($href, 'javascript:')
            || str_starts_with($href, 'mailto:')
            || str_starts_with($href, 'tel:')
            || str_starts_with($href, '#')) {
            return null;
        }

        // Strip inline fragment
        $href = strtok($href, '#');

        if (! $href) {
            return null;
        }

        // Already absolute
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return rtrim($href, '/');
        }

        // Root-relative (e.g. /products/my-slug)
        if (str_starts_with($href, '/')) {
            return rtrim($this->scheme.'://'.$this->host.$href, '/');
        }

        // Path-relative — resolve against the current page's directory
        $base = parse_url($pageUrl);
        $dir = rtrim(dirname($base['path'] ?? '/'), '/');

        return rtrim($this->scheme.'://'.$this->host.$dir.'/'.$href, '/');
    }

    /**
     * Return true only for internal, HTML-navigable pages.
     * Excludes static assets (images, CSS, JS, fonts, archives, etc.).
     */
    protected function isInternalPage(string $url): bool
    {
        $parsed = parse_url($url);

        if (($parsed['host'] ?? null) !== $this->host) {
            return false;
        }

        $path = $parsed['path'] ?? '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $assetExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'avif',
            'pdf', 'zip', 'tar', 'gz',
            'css', 'js', 'map',
            'woff', 'woff2', 'ttf', 'eot', 'otf',
            'mp4', 'mp3', 'avi', 'mov',
            'xml', 'json', 'txt', 'csv',
        ];

        return ! in_array($ext, $assetExtensions, true);
    }
}
