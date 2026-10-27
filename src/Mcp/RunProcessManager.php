<?php

namespace LensForLaravel\LensForLaravel\Mcp;

use LensForLaravel\LensForLaravel\Exceptions\McpException;
use Symfony\Component\Process\Process;

class RunProcessManager
{
    /** @var array<string, Process> */
    protected array $processes = [];

    public function __construct(protected RunStore $store) {}

    public function start(string $id): void
    {
        if (! is_file(base_path('artisan'))) {
            throw new McpException('WORKER_UNAVAILABLE', 'worker_unavailable');
        }
        $process = $this->makeProcess($id);
        $process->disableOutput();
        try {
            $process->start();
            $this->processes[$id] = $process;
        } catch (\Throwable) {
            throw new McpException('WORKER_UNAVAILABLE', 'worker_unavailable');
        }
    }

    protected function makeProcess(string $id): Process
    {
        return new Process([PHP_BINARY, base_path('artisan'), 'lens:mcp:work', $id, '--no-ansi', '--enabled'], base_path(), timeout: max(30, (int) config('lens-for-laravel.mcp.run_timeout', 900)));
    }

    public function tick(): void
    {
        foreach ($this->processes as $id => $process) {
            try {
                if ($this->store->read($id)['status'] === 'cancelled') {
                    $this->stop($process);
                    unset($this->processes[$id]);

                    continue;
                }
                $process->checkTimeout();
                if ($process->isRunning()) {
                    continue;
                }
                $this->finishOrphan($id, 'WORKER_FAILED', 'worker_failed');
            } catch (\Throwable) {
                $this->stop($process);
                $this->finishOrphan($id, 'RUN_TIMEOUT', 'timeout');
            }
            unset($this->processes[$id]);
        }
    }

    public function cancel(string $id): array
    {
        $run = $this->store->update($id, function ($run) {
            if (! in_array($run['status'], RunStore::TERMINAL, true)) {
                $run['status'] = 'cancelled';
            }

            return $run;
        });
        if (isset($this->processes[$id])) {
            $this->stop($this->processes[$id]);
            unset($this->processes[$id]);
        }

        return $run;
    }

    public function shutdown(): void
    {
        foreach (array_keys($this->processes) as $id) {
            try {
                $this->cancel($id);
            } catch (\Throwable) {
                $this->stop($this->processes[$id]);
            }
        }
        $this->processes = [];
    }

    protected function stop(Process $process): void
    {
        $pid = $process->getPid();
        // The worker creates its own process group on Unix. Only signal groups owned by this manager.
        $ownsGroup = $pid && function_exists('posix_getpgid') && function_exists('posix_kill') && posix_getpgid($pid) === $pid;
        if ($ownsGroup) {
            @posix_kill(-$pid, 15);
        }
        $process->stop(0.5);
        if ($ownsGroup) {
            @posix_kill(-$pid, 9);
        }
    }

    private function finishOrphan(string $id, string $code, string $key): void
    {
        $this->store->update($id, function ($run) use ($code, $key) {
            if (! in_array($run['status'], RunStore::TERMINAL, true)) {
                $run['status'] = 'failed';
                $run['error'] = (new McpException($code, $key))->toArray();
            }

            return $run;
        });
    }
}
