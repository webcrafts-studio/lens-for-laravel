<?php

namespace LensForLaravel\LensForLaravel\Mcp;

use LensForLaravel\LensForLaravel\Exceptions\McpException;
use LensForLaravel\LensForLaravel\Services\AuditRunner;

class RunExecutor
{
    public function __construct(protected RunStore $store, protected AuditRunner $runner) {}

    public function execute(string $id): array
    {
        $lease = fopen($this->store->path($id, '.worker'), 'c');
        if (! $lease || ! flock($lease, LOCK_EX | LOCK_NB)) {
            if ($lease) {
                fclose($lease);
            }
            throw new McpException('RUN_BUSY', 'run_limit', true);
        }
        $claimed = false;
        try {
            $run = $this->store->update($id, function ($run) {
                if ($run['status'] !== 'queued') {
                    throw new McpException('RUN_BUSY', 'run_limit');
                }
                $run['status'] = 'running';

                return $run;
            });
            $claimed = true;
            $this->runner->run($run['request'], function (?string $url, array $issues, int $total, bool $failed = false) use ($id) {
                $this->checkpoint($id);
                $this->store->update($id, function ($run) use ($url, $issues, $total, $failed) {
                    if ($run['status'] === 'cancelled') {
                        return $run;
                    }
                    if ($url !== null) {
                        $run[$failed ? 'failed_urls' : 'urls_scanned'][] = $url;
                        $run['issues'] = array_merge($run['issues'], $issues);
                    }
                    $run['progress'] = ['completed' => count($run['urls_scanned']) + count($run['failed_urls']), 'total' => $total];

                    return $run;
                });
            }, fn () => $this->checkpoint($id));
            $run = $this->store->update($id, function ($run) {
                if ($run['status'] === 'cancelled') {
                    return $run;
                }
                $run['status'] = $run['urls_scanned'] === [] ? 'failed' : ($run['failed_urls'] === [] ? 'completed' : 'partial');

                return $run;
            });
            if ($run['request']['save_history'] && in_array($run['status'], ['completed', 'partial'], true)) {
                app(HistoryRepository::class)->save($run);
            }
        } catch (\Throwable $e) {
            if (! $claimed) {
                throw $e;
            }
            logger()->warning('Lens MCP run failed', ['run_id' => $id, 'exception' => $e::class]);
            $this->store->update($id, function ($run) use ($e) {
                if ($run['status'] !== 'cancelled') {
                    $run['status'] = 'failed';
                    $run['error'] = $e instanceof McpException ? $e->toArray() : (new McpException('SCAN_FAILED', 'scan_failed'))->toArray();
                }

                return $run;
            });
        } finally {
            flock($lease, LOCK_UN);
            fclose($lease);
        }

        return $this->store->read($id);
    }

    public function checkpoint(string $id): void
    {
        $run = $this->store->read($id);
        if ($run['status'] === 'cancelled') {
            throw new McpException('RUN_CANCELLED', 'cancelled');
        }
        if ($run['deadline'] < time()) {
            throw new McpException('RUN_TIMEOUT', 'timeout');
        }
    }
}
