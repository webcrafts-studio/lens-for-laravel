<?php

namespace LensForLaravel\LensForLaravel\Services;

use Illuminate\Support\Collection;
use LensForLaravel\LensForLaravel\DTOs\AuthenticatedScanContext;
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
    public function scan(string $url, ?string $wcagVersion = null, ?AuthenticatedScanContext $auth = null): Collection
    {
        try {
            $wcagVersion ??= Wcag::configuredVersion();
            $tagsJson = json_encode(Wcag::tags($wcagVersion), JSON_THROW_ON_ERROR);

            // Configure Browsershot. Note that in some environments you may need
            // to configure the Node/NPM/Puppeteer path explicitly.
            $browsershot = $this->configureBrowsershot(
                $this->browsershotForUrl($this->freshScanUrl($url))
            );
            $this->applyAuthCookies($browsershot, $auth);

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

            $script = str_replace('(async () => {', '(async () => {'.$this->navigationGuard($url), $script);
            $raw = $browsershot->evaluate($script);
            if (! is_array(json_decode($raw, false, 512, JSON_THROW_ON_ERROR))) {
                throw new \RuntimeException(__('lens-for-laravel::messages.mcp.invalid_scan_result'));
            }
            $violations = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($violations) || ! array_is_list($violations)) {
                throw new \RuntimeException(__('lens-for-laravel::messages.mcp.invalid_scan_result'));
            }

            return $this->mapViolationsToIssues($violations, $url);
        } catch (Throwable $e) {
            throw new ScannerException(__('lens-for-laravel::messages.errors.axe_scan_failed', ['message' => $e->getMessage()]), 0, $e);
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
    public function scanInteractiveStates(string $url, array $states, ?string $wcagVersion = null, ?AuthenticatedScanContext $auth = null): Collection
    {
        try {
            $wcagVersion ??= Wcag::configuredVersion();
            $browsershot = $this->configureBrowsershot(
                $this->browsershotForUrl($this->freshScanUrl($url))
            );
            $this->applyAuthCookies($browsershot, $auth);

            $statesJson = json_encode($states, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $tagsJson = json_encode(Wcag::tags($wcagVersion), JSON_THROW_ON_ERROR);
            $script = $this->interactiveScanScript($statesJson, $tagsJson);
            $script = str_replace('(async () => {', '(async () => {'.$this->navigationGuard($url), $script);
            $payload = json_decode($browsershot->evaluate($script), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload) || ! isset($payload['states']) || ! is_array($payload['states'])
                || ! array_is_list($payload['states']) || count($payload['states']) !== count($states)
                || array_column($payload['states'], 'label') !== array_column($states, 'label')) {
                throw new \RuntimeException(__('lens-for-laravel::messages.mcp.invalid_scan_result'));
            }

            $issues = collect();
            foreach (($payload['states'] ?? []) as $stateResult) {
                if (! isset($stateResult['violations']) || ! is_array($stateResult['violations']) || ! array_is_list($stateResult['violations'])) {
                    throw new \RuntimeException(__('lens-for-laravel::messages.mcp.invalid_scan_result'));
                }
                $stateIssues = $this->mapViolationsToIssues(
                    $stateResult['violations'],
                    $url
                );

                $stateIssues->each(function (Issue $issue) use ($stateResult) {
                    $issue->stateLabel = $stateResult['label'] ?? null;
                });

                $issues = $issues->merge($stateIssues);
            }

            return $issues;
        } catch (Throwable $e) {
            throw new ScannerException(__('lens-for-laravel::messages.errors.axe_state_scan_failed', ['message' => $e->getMessage()]), 0, $e);
        }
    }

    protected function navigationGuard(string $url): string
    {
        if (! config('lens-for-laravel.mcp.strict_navigation', false)) {
            return '';
        }
        $expected = json_encode($url, JSON_THROW_ON_ERROR);

        return "const expected = new URL({$expected}); const actual = new URL(location.href); expected.searchParams.delete('__lens_scan'); actual.searchParams.delete('__lens_scan'); expected.hash = ''; actual.hash = ''; if (actual.href !== expected.href) throw new Error('Lens navigation changed');";
    }

    public function preview(string $url, string $selector, array $states = [], ?AuthenticatedScanContext $auth = null): string
    {
        $browser = $this->configureBrowsershot($this->browsershotForUrl($this->freshScanUrl($url)))->windowSize(1280, 800);
        $this->applyAuthCookies($browser, $auth);
        $actions = json_encode(array_merge(...array_map(fn ($s) => $s['actions'], $states ?: [['actions' => []]])), JSON_THROW_ON_ERROR);
        $target = json_encode($selector, JSON_THROW_ON_ERROR);
        $guard = $this->navigationGuard($url);
        $script = <<<JS
        window.__lensPreviewReady = false;
        (async () => {
            {$guard}
            const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
            for (const action of {$actions}) {
                if (action.type === 'wait') { await wait(action.ms); continue; }
                const el = document.querySelector(action.selector);
                if (!el) throw new Error('Lens selector unavailable');
                if (action.type === 'click') {
                    const prevent = event => event.preventDefault();
                    document.addEventListener('click', prevent, true);
                    document.addEventListener('submit', prevent, true);
                    try { el.click(); await wait(150); }
                    finally { document.removeEventListener('click', prevent, true); document.removeEventListener('submit', prevent, true); }
                } else {
                    if (action.type === 'check' || action.type === 'uncheck') el.checked = action.type === 'check';
                    else { el.focus(); el.value = action.value; }
                    el.dispatchEvent(new Event('input', {bubbles:true}));
                    el.dispatchEvent(new Event('change', {bubbles:true}));
                    await wait(100);
                }
            }
            const el = document.querySelector({$target});
            if (!el) throw new Error('Lens selector unavailable');
            el.scrollIntoView({block:'center'});
            el.style.outline = '3px solid #e11d48';
            window.__lensPreviewReady = true;
        })().catch(() => { window.__lensPreviewFailed = true; });
        JS;

        return $browser->setOption('addScriptTag', json_encode(['content' => $script], JSON_THROW_ON_ERROR))
            ->waitForFunction('window.__lensPreviewReady === true')->screenshot();
    }

    protected function browsershotForUrl(string $url): Browsershot
    {
        return Browsershot::url($url);
    }

    /**
     * Attach server-issued session cookies so Chromium scans the
     * authenticated page. The cookies always come from
     * AuthenticatedScanResolver, never from client input.
     */
    protected function applyAuthCookies(Browsershot $browsershot, ?AuthenticatedScanContext $auth): void
    {
        if ($auth !== null && ! $auth->isEmpty()) {
            $browsershot->useCookies($auth->cookies);
        }
    }

    /**
     * Give every browser navigation a unique URL so page caches, reverse
     * proxies, and service workers cannot return HTML from an earlier scan.
     * The original URL is still used for issue and history identity.
     */
    protected function freshScanUrl(string $url): string
    {
        $fragment = '';
        $fragmentPosition = strpos($url, '#');

        if ($fragmentPosition !== false) {
            $fragment = substr($url, $fragmentPosition);
            $url = substr($url, 0, $fragmentPosition);
        }

        $separator = str_contains($url, '?')
            ? (str_ends_with($url, '?') || str_ends_with($url, '&') ? '' : '&')
            : '?';

        return $url.$separator.'__lens_scan='.bin2hex(random_bytes(8)).$fragment;
    }

    protected function configureBrowsershot(Browsershot $browsershot): Browsershot
    {
        $browsershot
            ->noSandbox()
            ->waitUntilNetworkIdle()
            ->setExtraHttpHeaders([
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        if (config('lens-for-laravel.mcp.strict_navigation', false)) {
            $browsershot->timeout(max(5, (int) config('lens-for-laravel.mcp.browser_timeout', 120)));
        }

        $scanWaitMs = (int) config('lens-for-laravel.scan_wait_ms', 0);
        if ($scanWaitMs > 0) {
            $browsershot->setDelay($scanWaitMs);
        }

        return app(HttpsClientConfiguration::class)->configureBrowser($browsershot);
    }

    protected function interactiveScanScript(string $statesJson, string $tagsJson): string
    {
        $selectorNotFound = json_encode(
            __('lens-for-laravel::messages.errors.selector_not_found', ['selector' => '']),
            JSON_THROW_ON_ERROR
        );
        $unsupportedAction = json_encode(
            __('lens-for-laravel::messages.errors.unsupported_interaction', ['action' => '']),
            JSON_THROW_ON_ERROR
        );

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
                        throw new Error({$selectorNotFound} + action.selector);
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

                    throw new Error({$unsupportedAction} + action.type);
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
            if (! is_array($violation) || ! isset($violation['id'], $violation['description'], $violation['nodes']) || ! is_array($violation['nodes']) || ! array_is_list($violation['nodes'])) {
                throw new \RuntimeException(__('lens-for-laravel::messages.mcp.invalid_scan_result'));
            }
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
