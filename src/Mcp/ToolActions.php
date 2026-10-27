<?php

namespace LensForLaravel\LensForLaravel\Mcp;

use LensForLaravel\LensForLaravel\DTOs\Issue;
use LensForLaravel\LensForLaravel\Exceptions\McpException;
use LensForLaravel\LensForLaravel\Services\AiFixAvailability;
use LensForLaravel\LensForLaravel\Services\AuthenticatedScanResolver;
use LensForLaravel\LensForLaravel\Services\AxeScanner;
use LensForLaravel\LensForLaravel\Services\BaselineManager;
use LensForLaravel\LensForLaravel\Services\InteractionScriptParser;
use LensForLaravel\LensForLaravel\Services\McpAvailability;
use LensForLaravel\LensForLaravel\Services\ReportExporter;
use LensForLaravel\LensForLaravel\Services\ScanTargetPolicy;
use LensForLaravel\LensForLaravel\Support\Wcag;

class ToolActions
{
    public function __construct(protected RunService $runs, protected RunStore $store, protected SourceContext $context) {}

    public function call(string $name, array $input): array
    {
        return match ($name) {
            'lens_status' => $this->status(),
            'lens_scan' => $this->runs->start($input),
            'lens_get_run' => $this->run($input['run_id']),
            'lens_cancel_run' => $this->runs->summary(app(RunProcessManager::class)->cancel($input['run_id'])),
            'lens_list_issues' => $this->runs->issues($input['run_id'], $input),
            'lens_issue_context' => $this->context->get($input['run_id'], $input['issue_ref']),
            'lens_record_change' => $this->context->record($input['run_id'], $input['issue_ref']),
            'lens_verify' => $this->runs->verify($input['run_id'], $input['async'] ?? true),
            'lens_compare' => $this->runs->compare($input['base_run_id'], $input['current_run_id'], (int) ($input['page'] ?? 1), (int) ($input['per_page'] ?? 25)),
            'lens_history' => app(HistoryRepository::class)->read($input),
            'lens_prepare_patch' => $this->context->prepare($input['run_id'], $input['issue_ref'], $input['replacement']),
            'lens_apply_patch' => $this->context->apply($input['run_id'], $input['patch_id']),
            'lens_preview' => $this->preview($input['run_id'], $input['issue_ref']),
            'lens_export' => $this->export($input['run_id'], $input['format'] ?? 'json'),
            'lens_baseline' => $this->baseline($input['run_id'], $input['action'] ?? 'compare'),
            default => throw new McpException('UNKNOWN_TOOL', 'invalid_arguments'),
        };
    }

    public function status(): array
    {
        return ['mcp' => app(McpAvailability::class)->status(), 'laravel' => app()->version(), 'php' => PHP_VERSION,
            'history_available' => app(HistoryRepository::class)->available(), 'auth_enabled' => (bool) config('lens-for-laravel.auth_enabled', false),
            'ai_fix' => app(AiFixAvailability::class)->status(), 'source_writes_enabled' => (bool) config('lens-for-laravel.mcp.allow_writes', false),
            'defaults' => ['wcag' => Wcag::configuredVersion(), 'crawl_max_pages' => (int) config('lens-for-laravel.crawl_max_pages', 50)],
            'limits' => ['max_concurrent_runs' => (int) config('lens-for-laravel.mcp.max_concurrent_runs', 2),
                'run_timeout_seconds' => (int) config('lens-for-laravel.mcp.run_timeout', 900), 'context_bytes' => 6000],
            'requirements' => __('lens-for-laravel::messages.mcp.requirements'), 'verification' => 'automated_checks_only'];
    }

    private function run(string $id): array
    {
        $summary = $this->runs->get($id);
        if ($summary['parent_run_id'] && in_array($summary['status'], ['completed', 'partial'], true)) {
            $summary['comparison'] = $this->runs->compare($summary['parent_run_id'], $id);
        }

        return $summary;
    }

    public function preview(string $id, string $ref): array
    {
        $run = $this->store->read($id);
        $issue = $this->runs->issue($id, $ref);
        $url = app(ScanTargetPolicy::class)->resolve($issue['url']);
        $states = [];
        if ($issue['stateLabel'] !== null) {
            foreach (app(InteractionScriptParser::class)->parse($run['request']['script'] ?? '') as $state) {
                $states[] = $state;
                if ($state['label'] === $issue['stateLabel']) {
                    break;
                }
            }
        }
        $previous = [];
        foreach ([...$run['request']['settings'], 'mcp.strict_navigation' => true] as $key => $value) {
            $previous[$key] = config('lens-for-laravel.'.$key);
            config(['lens-for-laravel.'.$key => $value]);
        }
        try {
            $bytes = app(AuthenticatedScanResolver::class)->runIsolatedAsUser($run['request']['as_user_id'],
                fn ($auth) => app(AxeScanner::class)->preview($url, $issue['selector'], $states, $auth));
        } finally {
            foreach ($previous as $key => $value) {
                config(['lens-for-laravel.'.$key => $value]);
            }
        }
        if (strlen($bytes) > 2097152) {
            throw new McpException('RESULT_LIMIT', 'result_limit');
        }

        return ['run_id' => $id, 'issue_ref' => $ref, 'state' => $issue['stateLabel'], '_image' => $bytes];
    }

    public function export(string $id, string $format): array
    {
        $run = $this->store->read($id);
        if (! in_array($run['status'], ['completed', 'partial'], true)) {
            throw new McpException('RUN_NOT_READY', 'run_not_ready');
        }
        if ($format === 'pdf') {
            $bytes = app(ReportExporter::class)->pdf($run['issues'], $run['request']['urls'][0], $run['request']['wcag']);
        } else {
            $bytes = json_encode(['summary' => $this->runs->summary($run), 'issues' => $run['issues']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        $path = $this->store->path($id, '.export.'.$format);
        if (is_link($path) || file_put_contents($path, $bytes, LOCK_EX) === false || ! chmod($path, 0600)) {
            throw new McpException('STORE_FAILED', 'storage_failed');
        }

        return ['run_id' => $id, 'format' => $format, 'path' => $path, 'bytes' => strlen($bytes), 'status' => $run['status']];
    }

    private function baseline(string $id, string $action): array
    {
        $run = $this->store->read($id);
        if ($run['status'] !== 'completed') {
            throw new McpException('RUN_NOT_READY', 'run_not_ready');
        }
        $issues = collect($run['issues'])->map(fn ($i) => new Issue($i['id'], $i['impact'], $i['description'], $i['helpUrl'], $i['htmlSnippet'], $i['selector'], $i['tags'], $i['url'], $i['fileName'], $i['lineNumber'], $i['sourceType'], $i['stateLabel']));
        $manager = app(BaselineManager::class);
        $path = $manager->defaultPath();
        if ($action === 'save') {
            if (! config('lens-for-laravel.mcp.allow_writes', false)) {
                throw new McpException('WRITES_DISABLED', 'writes_disabled');
            }
            $manager->write($issues, $path, ['level_filter' => $run['request']['level'], 'wcag_version' => $run['request']['wcag'], 'urls_scanned' => $run['urls_scanned']]);

            return ['status' => 'saved', 'issue_count' => $issues->count()];
        }
        if (! is_file($path) || filesize($path) > 16777216) {
            throw new McpException('BASELINE_UNAVAILABLE', 'baseline_unavailable');
        }
        $saved = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($saved['wcag_version'] ?? $run['request']['wcag']) !== $run['request']['wcag'] || ($saved['level_filter'] ?? $run['request']['level']) !== $run['request']['level']) {
            throw new McpException('INCOMPARABLE_RUNS', 'incomparable');
        }
        $result = $manager->compare($issues, $path);

        return ['new' => $result['new']->all(), 'existing' => $result['existing']->all(), 'no_longer_detected' => $result['fixed']->all(), 'baseline_count' => $result['baseline_count']];
    }
}
