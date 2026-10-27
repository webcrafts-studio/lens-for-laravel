<?php

namespace LensForLaravel\LensForLaravel\Mcp;

use Illuminate\Support\Facades\Validator;
use LensForLaravel\LensForLaravel\Exceptions\McpException;
use LensForLaravel\LensForLaravel\Services\InteractionScriptParser;
use LensForLaravel\LensForLaravel\Services\ScanTargetPolicy;
use LensForLaravel\LensForLaravel\Support\UrlNormalizer;
use LensForLaravel\LensForLaravel\Support\Wcag;

class RunService
{
    public function __construct(protected RunStore $store, protected RunProcessManager $processes, protected RunExecutor $executor) {}

    public function start(array $input): array
    {
        $request = $this->normalize($input);

        return $this->launch($request, $input['async'] ?? true);
    }

    private function launch(array $request, bool $async): array
    {
        $this->processes->tick();
        if ($request['idempotency_key'] !== null) {
            foreach ($this->store->all() as $existing) {
                if ($existing['request']['idempotency_key'] === $request['idempotency_key']) {
                    if ($existing['request'] !== $request) {
                        throw new McpException('IDEMPOTENCY_CONFLICT', 'invalid_arguments');
                    }

                    return $this->summary($existing);
                }
            }
        }
        $run = $this->store->create($request);
        if (! $run['created_new']) {
            return $this->summary($run);
        }
        if ($async) {
            try {
                $this->processes->start($run['run_id']);
            } catch (McpException $e) {
                $this->store->update($run['run_id'], function ($run) use ($e) {
                    $run['status'] = 'failed';
                    $run['error'] = $e->toArray();

                    return $run;
                });
                throw $e;
            }
        } else {
            $run = $this->executor->execute($run['run_id']);
        }

        return $this->summary($run);
    }

    public function normalize(array $input): array
    {
        Validator::make($input, [
            'mode' => 'sometimes|in:single,multiple,website,states', 'url' => 'sometimes|string|max:2048',
            'urls' => 'sometimes|array|min:1|max:50', 'urls.*' => 'required|string|max:2048',
            'wcag' => 'sometimes|in:2.0,2.1,2.2', 'level' => 'sometimes|in:a,aa,all',
            'script' => 'sometimes|string|max:10000', 'as_user_id' => 'nullable|integer|min:1',
            'max_pages' => 'sometimes|integer|min:1|max:500', 'async' => 'sometimes|boolean',
            'save_history' => 'sometimes|boolean', 'idempotency_key' => 'nullable|string|max:100',
        ])->validate();
        $mode = $input['mode'] ?? 'single';
        if (($mode === 'multiple' && (empty($input['urls']) || isset($input['url']))) || ($mode !== 'multiple' && isset($input['urls'])) || ($mode !== 'states' && isset($input['script']))) {
            throw new McpException('INVALID_ARGUMENTS', 'invalid_arguments');
        }
        if ($mode === 'states') {
            app(InteractionScriptParser::class)->parse($input['script'] ?? '');
        }
        $targets = app(ScanTargetPolicy::class);
        $urls = array_values(array_unique(array_map($targets->resolve(...), $mode === 'multiple' ? $input['urls'] : [$input['url'] ?? '/'])));
        $max = max(1, min(500, (int) config('lens-for-laravel.crawl_max_pages', 50)));
        if (($input['max_pages'] ?? $max) > $max) {
            throw new McpException('RESULT_LIMIT', 'result_limit');
        }
        $settings = [];
        foreach (['ignore_https_errors', 'crawler_render_javascript', 'scan_wait_ms'] as $key) {
            $settings[$key] = config('lens-for-laravel.'.$key);
        }

        return [
            'mode' => $mode, 'urls' => $urls, 'wcag' => $input['wcag'] ?? Wcag::configuredVersion(),
            'level' => $input['level'] ?? 'all', 'script' => $input['script'] ?? null,
            'as_user_id' => isset($input['as_user_id']) ? (int) $input['as_user_id'] : null,
            'max_pages' => (int) ($input['max_pages'] ?? $max), 'settings' => $settings,
            'save_history' => (bool) ($input['save_history'] ?? true),
            'idempotency_key' => $input['idempotency_key'] ?? null, 'parent_run_id' => null,
        ];
    }

    public function get(string $id): array
    {
        $this->processes->tick();
        $run = $this->store->read($id);
        if (! in_array($run['status'], RunStore::TERMINAL, true) && $run['deadline'] < time()) {
            $this->processes->cancel($id);
            $run = $this->store->update($id, function ($run) {
                $run['status'] = 'failed';
                $run['error'] = (new McpException('RUN_TIMEOUT', 'timeout'))->toArray();

                return $run;
            });
        }

        return $this->summary($run);
    }

    public function verify(string $id, bool $async = true): array
    {
        $base = $this->store->read($id);
        if (! in_array($base['status'], ['completed', 'partial'], true)) {
            throw new McpException('RUN_NOT_READY', 'run_not_ready');
        }
        $request = $base['request'];
        // Re-check targets and auth capability at execution; never reuse captured cookies.
        foreach ($request['urls'] as $url) {
            app(ScanTargetPolicy::class)->resolve($url);
        }
        $request['parent_run_id'] = $id;
        $request['idempotency_key'] = null;
        $result = $this->launch($request, $async);
        if (in_array($result['status'], ['completed', 'partial'], true)) {
            $result['comparison'] = $this->compare($id, $result['run_id']);
        }

        return $result;
    }

    public function summary(array $run): array
    {
        return [
            'run_id' => $run['run_id'], 'status' => $run['status'], 'created_at' => $run['created_at'],
            'progress' => $run['progress'], 'mode' => $run['request']['mode'], 'wcag' => $run['request']['wcag'],
            'level' => $run['request']['level'], 'issue_count' => count($run['issues']),
            'urls_scanned' => $run['urls_scanned'], 'failed_urls' => $run['failed_urls'],
            'parent_run_id' => $run['request']['parent_run_id'], 'history_id' => $run['history_id'] ?? null,
            'error' => $run['error'] ?? null, 'history_warning' => $run['history_warning'] ?? null,
            'verification' => 'automated_checks_only',
        ];
    }

    public function issues(string $id, array $filters = []): array
    {
        $run = $this->store->read($id);
        $issues = collect($run['issues']);
        foreach (['rule_id' => 'id', 'impact' => 'impact', 'url' => 'url', 'state' => 'stateLabel', 'file' => 'fileName', 'level' => 'level'] as $input => $field) {
            if (isset($filters[$input])) {
                $issues = $issues->where($field, $filters[$input]);
            }
        }
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 25);
        $items = $issues->slice(($page - 1) * $perPage, $perPage)->map(function ($issue) use ($run) {
            unset($issue['htmlSnippet'], $issue['source_hash']);
            $issue['fix_status'] = isset($run['changes'][$issue['issue_ref']]) ? 'pending_verification' : null;

            return $issue;
        })->values()->all();

        return ['run_id' => $id, 'status' => $run['status'], 'total' => $issues->count(), 'page' => $page, 'per_page' => $perPage, 'issues' => $items];
    }

    public function issue(string $id, string $ref): array
    {
        $run = $this->store->read($id);
        foreach ($run['issues'] as $issue) {
            if ($issue['issue_ref'] === $ref) {
                return $issue;
            }
        }
        throw new McpException('ISSUE_NOT_FOUND', 'issue_missing');
    }

    public function compare(string $baseId, string $currentId, int $page = 1, int $perPage = 25): array
    {
        $base = $this->store->read($baseId);
        $current = $this->store->read($currentId);
        foreach ([$base, $current] as $run) {
            if (! in_array($run['status'], ['completed', 'partial'], true)) {
                throw new McpException('RUN_NOT_READY', 'run_not_ready');
            }
        }
        foreach (['mode', 'wcag', 'level', 'script', 'as_user_id', 'settings'] as $key) {
            if ($base['request'][$key] !== $current['request'][$key]) {
                throw new McpException('INCOMPARABLE_RUNS', 'incomparable');
            }
        }
        $key = static fn ($issue) => json_encode([$issue['id'], UrlNormalizer::pathAndQuery($issue['url']), $issue['stateLabel'], trim($issue['selector'])]);
        $old = collect($base['issues'])->keyBy($key);
        $new = collect($current['issues'])->keyBy($key);
        $covered = array_map(UrlNormalizer::pathAndQuery(...), $current['urls_scanned']);
        $disappeared = $old->diffKeys($new);
        $noLonger = $disappeared->filter(fn ($issue) => in_array(UrlNormalizer::pathAndQuery($issue['url']), $covered, true));
        $refs = static fn ($issues) => $issues->slice(($page - 1) * $perPage, $perPage)->map(fn ($issue) => ['issue_ref' => $issue['issue_ref'], 'rule_id' => $issue['id'], 'url' => $issue['url'], 'selector' => $issue['selector'], 'state' => $issue['stateLabel']])->values()->all();

        return [
            'base_run_id' => $baseId, 'current_run_id' => $currentId, 'page' => $page, 'per_page' => $perPage,
            'counts' => ['new' => $new->diffKeys($old)->count(), 'remaining' => $new->intersectByKeys($old)->count(), 'no_longer_detected' => $noLonger->count(), 'unverified' => $disappeared->diffKeys($noLonger)->count()],
            'new' => $refs($new->diffKeys($old)), 'remaining' => $refs($new->intersectByKeys($old)),
            'no_longer_detected' => $refs($noLonger), 'unverified' => $refs($disappeared->diffKeys($noLonger)),
            'complete' => $base['status'] === 'completed' && $current['status'] === 'completed' && $this->sameCoverage($base['urls_scanned'], $current['urls_scanned']),
            'verification' => 'automated_checks_only',
        ];
    }

    private function sameCoverage(array $a, array $b): bool
    {
        $a = array_map(UrlNormalizer::pathAndQuery(...), $a);
        $b = array_map(UrlNormalizer::pathAndQuery(...), $b);
        sort($a);
        sort($b);

        return $a === $b;
    }
}
