<?php

namespace LensForLaravel\LensForLaravel\Mcp;

use Illuminate\Support\Collection;
use LensForLaravel\LensForLaravel\Services\AuditRunner;
use LensForLaravel\LensForLaravel\Services\McpAvailability;

class DashboardBridge
{
    public function recordSourceChange(string $fileName, string $hash): void
    {
        if (! app(McpAvailability::class)->status()['available']) {
            return;
        }
        try {
            foreach (app(RunStore::class)->all() as $run) {
                $matching = array_filter($run['issues'], fn ($issue) => $issue['fileName'] === $fileName && $issue['source_hash'] !== $hash);
                if ($matching === []) {
                    continue;
                }
                app(RunStore::class)->update($run['run_id'], function ($run) use ($matching, $hash) {
                    foreach ($matching as $issue) {
                        $run['changes'][$issue['issue_ref']] = ['hash' => $hash, 'changed_at' => microtime(true), 'status' => 'pending_verification'];
                    }

                    return $run;
                });
            }
        } catch (\Throwable $e) {
            logger()->warning('Lens MCP change synchronization unavailable', ['exception' => $e::class]);
        }
    }

    public function capture(Collection $issues, string $url, ?string $wcag, ?int $user, ?string $script = null): ?string
    {
        if (! app(McpAvailability::class)->status()['available']) {
            return null;
        }
        try {
            $input = ['mode' => $script !== null ? 'states' : 'single', 'url' => $url, 'as_user_id' => $user, 'save_history' => false];
            if ($wcag !== null) {
                $input['wcag'] = $wcag;
            }
            if ($script !== null) {
                $input['script'] = $script;
            }
            $store = app(RunStore::class);
            $run = $store->create(app(RunService::class)->normalize($input));
            $records = $issues->map(fn ($issue) => app(AuditRunner::class)->record($issue))->all();
            $store->update($run['run_id'], function ($run) use ($url, $records) {
                $run['status'] = 'completed';
                $run['issues'] = $records;
                $run['urls_scanned'] = [$url];
                $run['progress'] = ['completed' => 1, 'total' => 1];

                return $run;
            });

            return $run['run_id'];
        } catch (\Throwable $e) {
            logger()->warning('Lens MCP dashboard snapshot unavailable', ['exception' => $e::class]);

            return null;
        }
    }

    public function changes(float $since): array
    {
        $changes = [];
        foreach (app(RunStore::class)->all() as $run) {
            foreach ($run['issues'] as $issue) {
                $change = $run['changes'][$issue['issue_ref']] ?? null;
                if ($change && $change['changed_at'] >= $since) {
                    $changes[] = ['id' => $issue['id'], 'url' => $issue['url'], 'selector' => $issue['selector'], 'stateLabel' => $issue['stateLabel'], 'fileName' => $issue['fileName'], ...$change];
                }
            }
        }

        return array_slice($changes, -500);
    }
}
