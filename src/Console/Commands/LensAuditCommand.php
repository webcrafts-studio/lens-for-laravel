<?php

namespace LensForLaravel\LensForLaravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LensForLaravel\LensForLaravel\DTOs\Issue;
use LensForLaravel\LensForLaravel\Exceptions\ScannerException;
use LensForLaravel\LensForLaravel\Services\AxeScanner;
use LensForLaravel\LensForLaravel\Services\BaselineManager;
use LensForLaravel\LensForLaravel\Services\FileLocator;
use LensForLaravel\LensForLaravel\Services\InteractionScriptParser;
use LensForLaravel\LensForLaravel\Services\SiteCrawler;
use LensForLaravel\LensForLaravel\Support\Wcag;

class LensAuditCommand extends Command
{
    protected $signature = 'lens:audit
                            {url?* : Target URLs to audit (defaults to app URL)}
                            {--a : Report only WCAG Level A violations}
                            {--aa : Report WCAG Level A and AA violations}
                            {--all : Report all violation levels including AAA and best-practice (default)}
                            {--wcag= : WCAG standard version: 2.0, 2.1, or 2.2 (defaults to configuration)}
                            {--threshold=0 : Exit code 1 if violation count exceeds this threshold}
                            {--crawl : Crawl the entire website and audit all discovered pages}
                            {--states= : Run interactive states from a script file (single URL only)}
                            {--baseline : Save the current filtered violations as the baseline and exit successfully}
                            {--baseline-file= : Baseline JSON path (defaults to storage/app/lens-for-laravel/baseline.json)}
                            {--fail-on-new : Compare results against the baseline and fail only when new violations are found}';

    protected $description = 'Run an accessibility audit using axe-core via Browsershot and report WCAG violations';

    public function handle(): int
    {
        $urlArgs = (array) $this->argument('url');
        $urls = empty($urlArgs) ? [url('/')] : $urlArgs;
        $threshold = (int) $this->option('threshold');
        $levelFilter = $this->resolveLevelFilter();
        $wcagVersion = $this->resolveWcagVersion();
        $multipleMode = count($urls) > 1;
        $statesFile = $this->resolveStatesFileOption();
        $statesMode = $statesFile !== null;
        $crawlMode = (bool) $this->option('crawl') && ! $multipleMode;

        if ($wcagVersion === null) {
            return self::FAILURE;
        }

        if ($this->input->hasParameterOption('--states') && $statesFile === null) {
            $this->components->error(__('lens-for-laravel::messages.errors.states_path_required'));

            return self::FAILURE;
        }

        if ($statesMode && ($multipleMode || $this->option('crawl'))) {
            $this->components->error(__('lens-for-laravel::messages.errors.states_single_url'));

            return self::FAILURE;
        }

        if ($this->option('baseline') && $this->option('fail-on-new')) {
            $this->components->error(__('lens-for-laravel::messages.errors.baseline_options_exclusive'));

            return self::FAILURE;
        }

        $this->renderHeader($urls[0], $levelFilter, $wcagVersion, $threshold, $crawlMode, $multipleMode, $statesMode);

        // ── Scan ──────────────────────────────────────────────────────────────
        if ($statesMode) {
            $issues = $this->runInteractiveStateScan($urls[0], $statesFile, $wcagVersion);
            $scannedUrls = [$urls[0]];

            if ($issues === null) {
                return self::FAILURE;
            }
        } elseif ($multipleMode) {
            $result = $this->runMultipleUrlScan($urls, $wcagVersion);

            if ($result === null) {
                return self::FAILURE;
            }

            [$issues, $scannedUrls] = $result;
        } elseif ($crawlMode) {
            $result = $this->runCrawlScan($urls[0], $wcagVersion);

            if ($result === null) {
                return self::FAILURE;
            }

            [$issues, $scannedUrls] = $result;
        } else {
            $issues = $this->runScan($urls[0], $wcagVersion);
            $scannedUrls = [$urls[0]];

            if ($issues === null) {
                return self::FAILURE;
            }
        }

        // ── Filter + render ───────────────────────────────────────────────────
        $filtered = $this->filterByLevel($issues, $levelFilter);
        $violationCount = $filtered->count();

        if ($violationCount === 0) {
            $this->newLine();
            $this->components->success('No violations found.');
        } elseif ($multipleMode || $crawlMode) {
            $this->renderCrawlTable($filtered);
        } else {
            $this->renderTable($filtered);
        }

        if ($violationCount > 0) {
            $this->renderSummary($filtered, $scannedUrls);
        }

        if ($this->option('baseline')) {
            return $this->writeBaseline($filtered, $scannedUrls, $levelFilter, $wcagVersion);
        }

        if ($this->option('fail-on-new')) {
            return $this->enforceBaseline($filtered);
        }

        if ($violationCount === 0) {
            return self::SUCCESS;
        }

        // ── CI/CD quality gate ────────────────────────────────────────────────
        if ($violationCount > $threshold) {
            $this->newLine();
            $this->components->error(
                __('lens-for-laravel::messages.errors.quality_gate_failed', [
                    'count' => $violationCount,
                    'threshold' => $threshold,
                ])
            );

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info(
            "Quality gate passed: {$violationCount} violation(s) found (threshold: {$threshold})"
        );

        return self::SUCCESS;
    }

    // ─── Interactive-state scan ────────────────────────────────────────────────

    private function runInteractiveStateScan(string $url, string $scriptFile, string $wcagVersion): ?Collection
    {
        $this->newLine();

        try {
            $path = $this->resolveFilePath($scriptFile);

            if (! is_file($path) || ! is_readable($path)) {
                $this->components->error(__('lens-for-laravel::messages.errors.interaction_file_unreadable', ['path' => $path]));

                return null;
            }

            $script = file_get_contents($path);
            if ($script === false) {
                $this->components->error(__('lens-for-laravel::messages.errors.interaction_file_read_failed', ['path' => $path]));

                return null;
            }

            if (strlen($script) > 10000) {
                $this->components->error(__('lens-for-laravel::messages.errors.script_too_large'));

                return null;
            }

            $states = app(InteractionScriptParser::class)->parse($script);
            $scanner = app(AxeScanner::class);
            $issues = null;
            $stateCount = count($states);

            $this->components->task(
                "Executing {$stateCount} interactive state(s) from {$scriptFile}",
                function () use ($url, $states, $wcagVersion, $scanner, &$issues) {
                    $issues = $scanner->scanInteractiveStates($url, $states, $wcagVersion);
                }
            );

            $this->components->task('Resolving source locations', fn () => $this->resolveSourceLocations($issues));

            return $issues;
        } catch (InvalidArgumentException $e) {
            $this->components->error(__('lens-for-laravel::messages.errors.invalid_interaction_script', ['message' => $e->getMessage()]));

            return null;
        } catch (ScannerException $e) {
            $this->newLine();
            $this->components->error(__('lens-for-laravel::messages.errors.interactive_scan_failed_detail', ['message' => $e->getMessage()]));
            $this->renderTroubleshooting();

            return null;
        }
    }

    // ─── Single-URL scan ────────────────────────────────────────────────────────

    private function runScan(string $url, string $wcagVersion): ?Collection
    {
        $this->newLine();

        try {
            $scanner = app(AxeScanner::class);
            $issues = null;

            $this->components->task('Launching Browsershot + axe-core', function () {});

            $this->components->task("Scanning <href={$url}>{$url}</>", function () use ($url, $wcagVersion, $scanner, &$issues) {
                $issues = $scanner->scan($url, $wcagVersion);
            });

            $this->components->task('Resolving source locations', fn () => $this->resolveSourceLocations($issues));

            return $issues;
        } catch (ScannerException $e) {
            $this->newLine();
            $this->components->error(__('lens-for-laravel::messages.errors.scan_failed_detail', ['message' => $e->getMessage()]));
            $this->renderTroubleshooting();

            return null;
        }
    }

    // ─── Multiple-URL scan ───────────────────────────────────────────────────────

    /**
     * Scan a provided list of URLs with axe-core and return all collected issues
     * along with the list of actually-scanned URLs.
     *
     * @param  string[]  $urls
     * @return array{0: Collection<Issue>, 1: string[]}|null
     */
    private function runMultipleUrlScan(array $urls, string $wcagVersion): ?array
    {
        $total = count($urls);
        $this->newLine();

        $scanner = app(AxeScanner::class);
        $locator = app(FileLocator::class);
        $allIssues = collect();
        $scannedUrls = [];
        $failedUrls = [];

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat("  <fg=gray>%message%</>\n  [%bar%] %current%/%max% (%percent:3s%%)");
        $bar->setMessage('Initializing...');
        $bar->start();

        foreach ($urls as $pageUrl) {
            $displayPath = mb_strimwidth($pageUrl, 0, 55, '…');
            $bar->setMessage($displayPath);
            $bar->display();

            try {
                $pageIssues = $scanner->scan($pageUrl, $wcagVersion);

                foreach ($pageIssues as $issue) {
                    $location = $locator->locate($issue->htmlSnippet, $issue->selector);
                    if ($location) {
                        $issue->fileName = $location['file'];
                        $issue->lineNumber = $location['line'];
                        $issue->sourceType = $location['type'] ?? null;
                    }
                }

                $allIssues = $allIssues->merge($pageIssues);
                $scannedUrls[] = $pageUrl;
            } catch (ScannerException $e) {
                $failedUrls[] = $pageUrl;
            }

            $bar->advance();
        }

        $bar->setMessage('Done.');
        $bar->finish();
        $this->newLine(2);

        if (! empty($failedUrls)) {
            $this->line(sprintf(
                '  <fg=yellow>⚠ %d page(s) could not be scanned and were skipped.</>',
                count($failedUrls)
            ));
            $this->newLine();
        }

        if (empty($scannedUrls)) {
            $this->components->error(__('lens-for-laravel::messages.errors.all_pages_failed'));
            $this->renderTroubleshooting();

            return null;
        }

        return [$allIssues, $scannedUrls];
    }

    // ─── Crawl scan ─────────────────────────────────────────────────────────────

    /**
     * Crawl the site, scan each discovered URL with axe-core, and return
     * all collected issues along with the list of actually-scanned URLs.
     *
     * @return array{0: Collection<Issue>, 1: string[]}|null
     */
    private function runCrawlScan(string $url, string $wcagVersion): ?array
    {
        $maxPages = (int) config('lens-for-laravel.crawl_max_pages', 50);

        $this->newLine();

        // ── Step 1: discover URLs ──────────────────────────────────────────────
        $urls = [];

        $this->components->task(
            "Crawling site (limit: {$maxPages} pages)",
            function () use ($url, $maxPages, &$urls) {
                $urls = app(SiteCrawler::class)->crawl($url, $maxPages);
            }
        );

        if (empty($urls)) {
            $this->newLine();
            $this->components->error(__('lens-for-laravel::messages.errors.no_internal_pages'));

            return null;
        }

        $discovered = count($urls);
        $this->line("  <fg=gray>Found {$discovered} page(s) to audit.</>");
        $this->newLine();

        // ── Step 2: axe-core scan per page ────────────────────────────────────
        $scanner = app(AxeScanner::class);
        $locator = app(FileLocator::class);
        $allIssues = collect();
        $scannedUrls = [];
        $failedUrls = [];

        $bar = $this->output->createProgressBar($discovered);
        $bar->setFormat("  <fg=gray>%message%</>\n  [%bar%] %current%/%max% (%percent:3s%%)");
        $bar->setMessage('Initializing...');
        $bar->start();

        foreach ($urls as $pageUrl) {
            $displayPath = mb_strimwidth(
                parse_url($pageUrl, PHP_URL_PATH) ?: '/',
                0,
                55,
                '…'
            );

            $bar->setMessage($displayPath);
            $bar->display();

            try {
                $pageIssues = $scanner->scan($pageUrl, $wcagVersion);

                foreach ($pageIssues as $issue) {
                    $location = $locator->locate($issue->htmlSnippet, $issue->selector);
                    if ($location) {
                        $issue->fileName = $location['file'];
                        $issue->lineNumber = $location['line'];
                        $issue->sourceType = $location['type'] ?? null;
                    }
                }

                $allIssues = $allIssues->merge($pageIssues);
                $scannedUrls[] = $pageUrl;
            } catch (ScannerException $e) {
                $failedUrls[] = $pageUrl;
            }

            $bar->advance();
        }

        $bar->setMessage('Done.');
        $bar->finish();

        $this->newLine(2);

        if (! empty($failedUrls)) {
            $this->line(sprintf(
                '  <fg=yellow>⚠ %d page(s) could not be scanned and were skipped.</>',
                count($failedUrls)
            ));
            $this->newLine();
        }

        if (empty($scannedUrls)) {
            $this->components->error(__('lens-for-laravel::messages.errors.browser_pages_failed'));
            $this->renderTroubleshooting();

            return null;
        }

        return [$allIssues, $scannedUrls];
    }

    // ─── Baseline quality gate ────────────────────────────────────────────────

    /**
     * @param  Collection<int, Issue>  $issues
     * @param  string[]  $scannedUrls
     */
    private function writeBaseline(Collection $issues, array $scannedUrls, string $levelFilter, string $wcagVersion): int
    {
        $baseline = app(BaselineManager::class);
        $path = $baseline->resolvePath($this->option('baseline-file'));

        try {
            $baseline->write($issues, $path, [
                'level_filter' => $levelFilter,
                'wcag_version' => $wcagVersion,
                'urls_scanned' => array_values($scannedUrls),
            ]);
        } catch (\RuntimeException $e) {
            $this->newLine();
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info("Baseline saved with {$issues->count()} issue(s): {$path}");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Issue>  $issues
     */
    private function enforceBaseline(Collection $issues): int
    {
        $baseline = app(BaselineManager::class);
        $path = $baseline->resolvePath($this->option('baseline-file'));

        try {
            $comparison = $baseline->compare($issues, $path);
        } catch (\RuntimeException $e) {
            $this->newLine();
            $this->components->error($e->getMessage());
            $this->line('  Create a baseline first with: php artisan lens:audit --baseline');

            return self::FAILURE;
        }

        $this->renderBaselineComparison($comparison, $path);

        $newCount = $comparison['new']->count();
        if ($newCount > 0) {
            $this->components->error(__('lens-for-laravel::messages.errors.baseline_gate_failed', ['count' => $newCount]));

            return self::FAILURE;
        }

        $this->components->info('Baseline gate passed: no new violations found.');

        return self::SUCCESS;
    }

    /**
     * @param  array{
     *     new: Collection<int, array<string, mixed>>,
     *     existing: Collection<int, array<string, mixed>>,
     *     fixed: Collection<int, array<string, mixed>>,
     *     baseline_count: int
     * }  $comparison
     */
    private function renderBaselineComparison(array $comparison, string $path): void
    {
        $this->newLine();
        $this->line('  <options=bold>Baseline comparison</>');
        $this->line('  ─────────────────────────────────────────────');
        $this->line("  Baseline file : {$path}");
        $this->line('  Baseline size : <options=bold>'.$comparison['baseline_count'].'</>');
        $this->line('  New           : <fg=red;options=bold>'.$comparison['new']->count().'</>');
        $this->line('  Existing      : '.$comparison['existing']->count());
        $this->line('  Fixed         : <fg=green>'.$comparison['fixed']->count().'</>');

        if ($comparison['new']->isNotEmpty()) {
            $this->newLine();
            $this->line('  <fg=red;options=bold>New violations:</>');

            $comparison['new']->take(10)->each(function (array $issue) {
                $this->line('  - '.$this->formatBaselineIssue($issue));
            });

            if ($comparison['new']->count() > 10) {
                $remaining = $comparison['new']->count() - 10;
                $this->line("  - ...and {$remaining} more");
            }
        }

        $this->newLine();
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function formatBaselineIssue(array $issue): string
    {
        $location = $issue['file_name'] ?? $issue['url'] ?? 'unknown location';

        if (($issue['line_number'] ?? null) !== null && ($issue['file_name'] ?? null) !== null) {
            $location .= ':'.$issue['line_number'];
        }

        $selector = $issue['selector'] ?? '';
        $selectorSuffix = $selector !== '' ? " ({$selector})" : '';

        return "{$issue['rule_id']} at {$location}{$selectorSuffix}";
    }

    // ─── Filtering ──────────────────────────────────────────────────────────────

    private function resolveLevelFilter(): string
    {
        if ($this->option('a')) {
            return 'a';
        }

        if ($this->option('aa')) {
            return 'aa';
        }

        return 'all';
    }

    private function resolveWcagVersion(): ?string
    {
        $option = $this->option('wcag');
        $version = is_string($option) && $option !== '' ? $option : Wcag::configuredVersion();

        try {
            Wcag::assertVersion($version);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return null;
        }

        return $version;
    }

    private function resolveStatesFileOption(): ?string
    {
        $option = $this->option('states');

        return is_string($option) && trim($option) !== '' ? trim($option) : null;
    }

    private function resolveFilePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @param  Collection<int, Issue>  $issues
     */
    private function resolveSourceLocations(Collection $issues): void
    {
        $locator = app(FileLocator::class);

        foreach ($issues as $issue) {
            $location = $locator->locate($issue->htmlSnippet, $issue->selector);
            if ($location) {
                $issue->fileName = $location['file'];
                $issue->lineNumber = $location['line'];
                $issue->sourceType = $location['type'] ?? null;
            }
        }
    }

    private function filterByLevel(Collection $issues, string $level): Collection
    {
        return match ($level) {
            'a', 'aa' => $issues->filter(fn (Issue $i) => Wcag::matchesLevel($i->tags, $level)),
            default => $issues,
        };
    }

    // ─── Rendering ──────────────────────────────────────────────────────────────

    private function renderHeader(string $url, string $levelFilter, string $wcagVersion, int $threshold, bool $crawlMode, bool $multipleMode = false, bool $statesMode = false): void
    {
        $levelLabel = match ($levelFilter) {
            'a' => 'WCAG A only',
            'aa' => 'WCAG A + AA',
            default => 'A + AA + AAA + Best Practice',
        };

        $modeLabel = match (true) {
            $statesMode => '<fg=yellow>INTERACTIVE_STATES</>',
            $multipleMode => '<fg=magenta>MULTIPLE_URLS</>',
            $crawlMode => '<fg=cyan>WHOLE_WEBSITE</>',
            default => '<fg=gray>SINGLE_URL</>',
        };

        $this->newLine();
        $this->line('  <options=bold>Lens For Laravel — Accessibility Audit</>');
        $this->line('  ─────────────────────────────────────────────');
        $this->line("  <fg=gray>URL</>       : {$url}");
        $this->line("  <fg=gray>Mode</>      : {$modeLabel}");
        $this->line("  <fg=gray>Standard</>  : WCAG {$wcagVersion}");
        $this->line("  <fg=gray>Levels</>    : {$levelLabel}");
        $this->line("  <fg=gray>Threshold</> : {$threshold}");
    }

    /**
     * Table for single-URL scan: one row per violation node.
     */
    private function renderTable(Collection $issues): void
    {
        $verbose = $this->output->isVerbose();
        $includeState = $issues->contains(fn (Issue $issue) => $issue->stateLabel !== null);

        $this->newLine();
        $this->line('  <options=bold>Diagnostic Report</>');
        if (! $verbose) {
            $this->line('  <fg=gray>Tip: run with -v to see full HTML nodes</>');
        }
        $this->newLine();

        $nodeHeader = $verbose ? 'Failing Node (full)' : 'Node';

        $rows = $issues->values()->map(function (Issue $issue) use ($verbose, $includeState) {
            $row = [
                $this->formatLevel($issue->tags),
                wordwrap($issue->id, 30, "\n", true),
                $this->formatImpact($issue->impact),
                $this->formatNode($issue->htmlSnippet, $verbose),
            ];

            if ($includeState) {
                $row[] = $issue->stateLabel ?? '—';
            }

            $row[] = $issue->fileName ? "{$issue->fileName}:{$issue->lineNumber}" : '—';

            return $row;
        })->all();

        $headers = ['Level', 'Rule ID', 'Impact', $nodeHeader];
        if ($includeState) {
            $headers[] = 'State';
        }
        $headers[] = 'Location';

        $this->table($headers, $rows);
    }

    /**
     * Table for crawl scan: issues grouped by rule ID to avoid infinite rows.
     * Shows an "Occurrences" column: total violations + how many pages affected.
     */
    private function renderCrawlTable(Collection $issues): void
    {
        $this->newLine();
        $this->line('  <options=bold>Diagnostic Report — aggregated across all pages</>');
        $this->line('  <fg=gray>Issues are grouped by rule ID. Use -v to see full node HTML.</>');
        $this->newLine();

        $verbose = $this->output->isVerbose();

        $grouped = $issues->groupBy('id')->sortByDesc(fn ($group) => $group->count());

        $rows = $grouped->map(function (Collection $group, string $ruleId) use ($verbose) {
            /** @var Issue $first */
            $first = $group->first();

            $totalOccurrences = $group->count();
            $affectedPages = $group->pluck('url')->filter()->unique()->count();

            $occurrences = $affectedPages > 1
                ? "{$totalOccurrences} ({$affectedPages} pages)"
                : (string) $totalOccurrences;

            $location = $first->fileName
                ? "{$first->fileName}:{$first->lineNumber}"
                : '—';

            return [
                $this->formatLevel($first->tags),
                wordwrap($ruleId, 28, "\n", true),
                $this->formatImpact($first->impact),
                $this->formatNode($first->htmlSnippet, $verbose),
                $occurrences,
                $location,
            ];
        })->values()->all();

        $this->table(
            ['Level', 'Rule ID', 'Impact', 'Node (example)', 'Occurrences', 'Location (first)'],
            $rows
        );
    }

    private function renderSummary(Collection $issues, array $scannedUrls): void
    {
        $total = $issues->count();
        $byImpact = $issues->groupBy('impact');
        $isCrawl = count($scannedUrls) > 1;

        $levelCounts = [
            'A' => $issues->filter(fn (Issue $i) => Wcag::level($i->tags) === 'a')->count(),
            'AA' => $issues->filter(fn (Issue $i) => Wcag::level($i->tags) === 'aa')->count(),
            'AAA' => $issues->filter(fn (Issue $i) => Wcag::level($i->tags) === 'aaa')->count(),
            'Best Practice' => $issues->filter(fn (Issue $i) => Wcag::level($i->tags) === 'other')->count(),
        ];

        $this->line('  <options=bold>Summary</>');
        $this->line('  ─────────────────────────────────────────────');

        if ($isCrawl) {
            $uniqueRules = $issues->pluck('id')->unique()->count();
            $this->line('  Pages scanned     : <options=bold>'.count($scannedUrls).'</>');
            $this->line("  Unique rules hit  : <options=bold>{$uniqueRules}</>");
        }

        $this->line("  Total violations  : <fg=red;options=bold>{$total}</>");

        $this->newLine();
        $this->line('  <fg=gray>By WCAG level:</>');
        foreach ($levelCounts as $label => $count) {
            if ($count > 0) {
                $this->line("  · {$label}: {$count}");
            }
        }

        $this->newLine();
        $this->line('  <fg=gray>By impact:</>');
        foreach (['critical', 'serious', 'moderate', 'minor'] as $impact) {
            $count = $byImpact->get($impact, collect())->count();
            if ($count > 0) {
                $this->line("  · {$this->formatImpact($impact)}: {$count}");
            }
        }

        $this->newLine();
    }

    // ─── Formatters ─────────────────────────────────────────────────────────────

    private function formatLevel(array $tags): string
    {
        if (Wcag::level($tags) === 'a') {
            return '<fg=red;options=bold>A</>';
        }
        if (Wcag::level($tags) === 'aa') {
            return '<fg=yellow>AA</>';
        }
        if (Wcag::level($tags) === 'aaa') {
            return '<fg=blue>AAA</>';
        }

        return '<fg=gray>BP</>';
    }

    private function formatImpact(string $impact): string
    {
        return match ($impact) {
            'critical' => '<fg=red;options=bold>critical</>',
            'serious' => '<fg=red>serious</>',
            'moderate' => '<fg=yellow>moderate</>',
            'minor' => '<fg=gray>minor</>',
            default => $impact,
        };
    }

    /**
     * Format the HTML snippet for the Node column.
     *
     * Default: [tag] "extracted text" truncated to 30 chars.
     * Verbose (-v): raw HTML unchanged.
     */
    private function formatNode(string $html, bool $verbose): string
    {
        if ($verbose) {
            return $html;
        }

        preg_match('/^<(\w+)/i', ltrim($html), $tagMatch);
        $tag = $tagMatch[1] ?? '?';

        $text = $this->extractNodeText($html, $tag);
        $label = "[{$tag}]".($text !== '' ? " \"{$text}\"" : '');

        return Str::limit($label, 27);
    }

    /**
     * Pull the most meaningful human-readable text from an HTML snippet.
     *
     * Priority:
     *   img/area  → alt → basename(src)
     *   input/textarea/select → aria-label → placeholder → value
     *   any → aria-label → title → stripped inner text
     */
    private function extractNodeText(string $html, string $tag): string
    {
        if (in_array($tag, ['img', 'area'])) {
            if (preg_match('/\balt=["\']([^"\']*)["\']/', $html, $m) && $m[1] !== '') {
                return $m[1];
            }
            if (preg_match('/\bsrc=["\']([^"\']*)["\']/', $html, $m)) {
                return basename(parse_url($m[1], PHP_URL_PATH) ?? $m[1]);
            }

            return '';
        }

        if (in_array($tag, ['input', 'textarea', 'select'])) {
            if (preg_match('/\baria-label=["\']([^"\']+)["\']/', $html, $m)) {
                return $m[1];
            }
            if (preg_match('/\bplaceholder=["\']([^"\']+)["\']/', $html, $m)) {
                return $m[1];
            }
            if (preg_match('/\bvalue=["\']([^"\']+)["\']/', $html, $m)) {
                return $m[1];
            }

            return '';
        }

        if (preg_match('/\baria-label=["\']([^"\']+)["\']/', $html, $m)) {
            return $m[1];
        }

        if (preg_match('/\btitle=["\']([^"\']+)["\']/', $html, $m)) {
            return $m[1];
        }

        return trim(strip_tags($html));
    }

    private function renderTroubleshooting(): void
    {
        $this->newLine();
        $this->line('  <fg=yellow>Troubleshooting:</>');
        $this->line('  · Ensure Node.js and npm are installed');
        $this->line('  · Run: npm install -g puppeteer');
        $this->line('  · Or configure Browsershot with a local Chromium path in your environment');
        $this->newLine();
    }
}
