<?php

namespace LensForLaravel\LensForLaravel\Services;

use Illuminate\Support\Str;
use LensForLaravel\LensForLaravel\DTOs\AuthenticatedScanContext;
use LensForLaravel\LensForLaravel\DTOs\Issue;
use LensForLaravel\LensForLaravel\Exceptions\McpException;
use LensForLaravel\LensForLaravel\Support\Wcag;

class AuditRunner
{
    public function __construct(
        protected AxeScanner $scanner,
        protected SiteCrawler $crawler,
        protected FileLocator $locator,
        protected AuthenticatedScanResolver $auth,
        protected ScanTargetPolicy $targets,
        protected SourceFileResolver $sources,
    ) {}

    public function run(array $request, callable $progress, callable $checkpoint): void
    {
        $settings = $request['settings'];
        $previous = [];
        foreach ($settings as $key => $value) {
            $previous[$key] = config('lens-for-laravel.'.$key);
            config(['lens-for-laravel.'.$key => $value]);
        }
        $previousStrict = config('lens-for-laravel.mcp.strict_navigation');
        config(['lens-for-laravel.mcp.strict_navigation' => true]);
        try {
            $this->auth->runIsolatedAsUser($request['as_user_id'], function (?AuthenticatedScanContext $auth) use ($request, $progress, $checkpoint) {
                $checkpoint();
                $urls = $request['mode'] === 'website'
                    ? $this->crawler->crawl($request['urls'][0], $request['max_pages'], $auth)
                    : $request['urls'];
                $urls = array_values(array_unique(array_map($this->targets->resolve(...), $urls)));
                if ($urls === []) {
                    throw new McpException('SCAN_FAILED', 'scan_failed');
                }
                $progress(null, [], count($urls));
                foreach ($urls as $url) {
                    $checkpoint();
                    try {
                        $issues = $request['mode'] === 'states'
                            ? $this->scanner->scanInteractiveStates($url, app(InteractionScriptParser::class)->parse($request['script']), $request['wcag'], $auth)
                            : $this->scanner->scan($url, $request['wcag'], $auth);
                        $records = [];
                        foreach ($issues as $issue) {
                            if (! Wcag::matchesLevel($issue->tags, $request['level'])) {
                                continue;
                            }
                            $records[] = $this->record($issue);
                        }
                    } catch (\Throwable $e) {
                        $checkpoint();
                        logger()->warning('Lens MCP page scan failed', ['exception' => $e::class]);
                        $progress($url, [], count($urls), true);

                        continue;
                    }
                    $checkpoint();
                    $progress($url, $records, count($urls));
                }
            });
        } finally {
            foreach ($previous as $key => $value) {
                config(['lens-for-laravel.'.$key => $value]);
            }
            config(['lens-for-laravel.mcp.strict_navigation' => $previousStrict]);
        }
    }

    public function record(Issue $issue): array
    {
        $location = $this->locator->locate($issue->htmlSnippet, $issue->selector);
        if ($location) {
            $issue->fileName = $location['file'];
            $issue->lineNumber = $location['line'];
            $issue->sourceType = $location['type'] ?? null;
        }
        $record = get_object_vars($issue);
        $record['issue_ref'] = (string) Str::uuid();
        $record['level'] = Wcag::level($issue->tags);
        $record['source_hash'] = null;
        if ($issue->fileName) {
            try {
                $record['source_hash'] = hash_file('sha256', $this->sources->resolve($issue->fileName)['path']);
            } catch (McpException) {
                // The measured issue remains usable even when its estimated source is unavailable.
            }
        }

        return $record;
    }
}
