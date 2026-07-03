<?php

namespace LensForLaravel\LensForLaravel\Services;

use Illuminate\Support\Collection;
use LensForLaravel\LensForLaravel\DTOs\Issue;
use LensForLaravel\LensForLaravel\Exceptions\ScannerException;
use LensForLaravel\LensForLaravel\Support\Wcag;
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
    public function scan(string $url, ?string $wcagVersion = null): Collection
    {
        try {
            $wcagVersion ??= Wcag::configuredVersion();
            $tagsJson = json_encode(Wcag::tags($wcagVersion), JSON_THROW_ON_ERROR);

            // Configure Browsershot. Note that in some environments you may need
            // to configure the Node/NPM/Puppeteer path explicitly.
            $browsershot = $this->configureBrowsershot($this->browsershotForUrl($url));

            // We need to inject the axe-core library and run it.
            // Spatie Browsershot allows evaluating JavaScript on the page.
            $script = <<<JS
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
                            values: {$tagsJson}
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
     * Scan named interactive states after executing browser actions.
     *
     * @param  array<int, array{label: string, actions: array<int, array<string, mixed>>}>  $states
     * @return Collection<Issue>
     *
     * @throws ScannerException
     */
    public function scanInteractiveStates(string $url, array $states, ?string $wcagVersion = null): Collection
    {
        try {
            $wcagVersion ??= Wcag::configuredVersion();
            $browsershot = $this->configureBrowsershot($this->browsershotForUrl($url));

            $statesJson = json_encode($states, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $tagsJson = json_encode(Wcag::tags($wcagVersion), JSON_THROW_ON_ERROR);
            $script = $this->interactiveScanScript($statesJson, $tagsJson);
            $payload = json_decode($browsershot->evaluate($script), true);

            $issues = collect();
            foreach (($payload['states'] ?? []) as $stateResult) {
                $stateIssues = $this->mapViolationsToIssues(
                    is_array($stateResult['violations'] ?? null) ? $stateResult['violations'] : [],
                    $url
                );

                $stateIssues->each(function (Issue $issue) use ($stateResult) {
                    $issue->stateLabel = $stateResult['label'] ?? null;
                });

                $issues = $issues->merge($stateIssues);
            }

            return $issues;
        } catch (Throwable $e) {
            throw new ScannerException('Failed to run interactive Axe-core scan: '.$e->getMessage(), 0, $e);
        }
    }

    protected function browsershotForUrl(string $url): Browsershot
    {
        return Browsershot::url($url);
    }

    protected function configureBrowsershot(Browsershot $browsershot): Browsershot
    {
        $browsershot
            ->noSandbox()
            ->waitUntilNetworkIdle()
            ->setExtraHttpHeaders([
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
            ]);

        $scanWaitMs = (int) config('lens-for-laravel.scan_wait_ms', 0);
        if ($scanWaitMs > 0) {
            $browsershot->setDelay($scanWaitMs);
        }

        return app(HttpsClientConfiguration::class)->configureBrowser($browsershot);
    }

    protected function interactiveScanScript(string $statesJson, string $tagsJson): string
    {
        return <<<JS
            (async () => {
                const states = {$statesJson};
                const wait = (ms) => new Promise(resolve => setTimeout(resolve, ms));

                async function ensureAxe() {
                    if (typeof window.axe !== 'undefined') return;

                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.8.2/axe.min.js';
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }

                async function runAxe() {
                    await ensureAxe();
                    const results = await window.axe.run({
                        runOnly: {
                            type: 'tag',
                            values: {$tagsJson}
                        }
                    });

                    return results.violations;
                }

                function dispatch(el, eventName) {
                    el.dispatchEvent(new Event(eventName, { bubbles: true }));
                }

                async function clickWithoutNavigation(el) {
                    const preventDefault = (event) => {
                        event.preventDefault();
                    };

                    document.addEventListener('click', preventDefault, true);
                    document.addEventListener('submit', preventDefault, true);

                    try {
                        el.click();
                        await wait(150);
                    } finally {
                        document.removeEventListener('click', preventDefault, true);
                        document.removeEventListener('submit', preventDefault, true);
                    }
                }

                async function runAction(action) {
                    if (action.type === 'wait') {
                        await wait(action.ms);
                        return;
                    }

                    const el = document.querySelector(action.selector);
                    if (!el) {
                        throw new Error(`Selector not found: \${action.selector}`);
                    }

                    el.scrollIntoView({ block: 'center', inline: 'nearest' });
                    await wait(50);

                    if (action.type === 'click') {
                        await clickWithoutNavigation(el);
                        return;
                    }

                    if (action.type === 'type') {
                        el.focus();
                        el.value = action.value;
                        dispatch(el, 'input');
                        dispatch(el, 'change');
                        await wait(100);
                        return;
                    }

                    if (action.type === 'select') {
                        el.value = action.value;
                        dispatch(el, 'input');
                        dispatch(el, 'change');
                        await wait(100);
                        return;
                    }

                    if (action.type === 'check' || action.type === 'uncheck') {
                        el.checked = action.type === 'check';
                        dispatch(el, 'input');
                        dispatch(el, 'change');
                        await wait(100);
                        return;
                    }

                    throw new Error(`Unsupported action: \${action.type}`);
                }

                const results = [];

                for (const state of states) {
                    for (const action of state.actions || []) {
                        try {
                            await runAction(action);
                        } catch (error) {
                            throw new Error(`[\${state.label}] \${error.message || String(error)}`);
                        }
                    }

                    results.push({
                        label: state.label,
                        violations: await runAxe()
                    });
                }

                return JSON.stringify({ states: results });
            })();
JS;
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
