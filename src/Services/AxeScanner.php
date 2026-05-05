<?php

namespace LensForLaravel\LensForLaravel\Services;

use Illuminate\Support\Collection;
use LensForLaravel\LensForLaravel\DTOs\Issue;
use LensForLaravel\LensForLaravel\Exceptions\ScannerException;
use Spatie\Browsershot\Browsershot;
use Throwable;

class AxeScanner
{
    /**
     * Scan a given URL for accessibility violations using Axe-core via Browsershot.
     *
     * @return Collection<Issue>
     *
     * @throws ScannerException
     */
    public function scan(string $url): Collection
    {
        try {
            // Configure Browsershot. Note that in some environments you may need
            // to configure the Node/NPM/Puppeteer path explicitly.
            $browsershot = Browsershot::url($url)
                ->noSandbox()
                ->waitUntilNetworkIdle(); // Wait for the page to fully load

            $scanWaitMs = (int) config('lens-for-laravel.scan_wait_ms', 0);
            if ($scanWaitMs > 0) {
                $browsershot->setDelay($scanWaitMs);
            }

            $ignoreHttpsErrors = config('lens-for-laravel.ignore_https_errors', false);
            if ($ignoreHttpsErrors) {
                $browsershot->ignoreHttpsErrors();
            }

            // We need to inject the axe-core library and run it.
            // Spatie Browsershot allows evaluating JavaScript on the page.
            $script = <<<'JS'
                (async () => {
                    // Fetch and inject axe-core if it's not already present
                    if (typeof window.axe === 'undefined') {
                        await new Promise((resolve, reject) => {
                            const script = document.createElement('script');
                            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.8.2/axe.min.js';
                            script.onload = resolve;
                            script.onerror = reject;
                            document.head.appendChild(script);
                        });
                    }
                    
                    // Run axe with best-practice rules enabled
                    const results = await window.axe.run({
                        runOnly: {
                            type: 'tag',
                            values: ['wcag2a', 'wcag2aa', 'wcag2aaa', 'best-practice']
                        }
                    });
                    return JSON.stringify(results.violations);
                })();
JS;

            $violations = json_decode($browsershot->evaluate($script), true);

            return $this->mapViolationsToIssues(is_array($violations) ? $violations : [], $url);
        } catch (Throwable $e) {
            throw new ScannerException('Failed to run Axe-core scan: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Map the raw JSON array of violations from Axe to our DTO Collection.
     *
     * @return Collection<Issue>
     */
    protected function mapViolationsToIssues(array $violations, string $url): Collection
    {
        $issues = collect();

        foreach ($violations as $violation) {
            // Axe groups nodes (HTML elements) under each violation type.
            // We want an individual Issue DTO for every HTML element that failed.
            foreach ($violation['nodes'] ?? [] as $node) {
                $issues->push(new Issue(
                    id: $violation['id'],
                    impact: $violation['impact'] ?? 'unknown',
                    description: $violation['description'],
                    helpUrl: $violation['helpUrl'] ?? '',
                    htmlSnippet: $node['html'] ?? '',
                    selector: implode(', ', $node['target'] ?? []),
                    tags: $violation['tags'] ?? [],
                    url: $url
                ));
            }
        }

        return $issues;
    }
}
