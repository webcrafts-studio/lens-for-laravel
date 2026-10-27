<?php

namespace LensForLaravel\LensForLaravel\Console\Commands;

use Illuminate\Console\Command;
use LensForLaravel\LensForLaravel\Exceptions\McpException;
use LensForLaravel\LensForLaravel\Mcp\RunExecutor;
use LensForLaravel\LensForLaravel\Mcp\RunProcessManager;
use LensForLaravel\LensForLaravel\Mcp\RunService;
use LensForLaravel\LensForLaravel\Mcp\RunStore;
use LensForLaravel\LensForLaravel\Mcp\ToolActions;
use LensForLaravel\LensForLaravel\Services\McpAvailability;

class LensMcpRunsCommand extends Command
{
    protected $signature = 'lens:mcp:runs {action=list : list, status, cancel, work, prune, or doctor} {run? : Run UUID}';

    protected $description = 'Inspect, resume, cancel, or prune long Lens MCP scans';

    public function handle(McpAvailability $availability, RunStore $store, RunService $runs, RunProcessManager $processes): int
    {
        try {
            if ($this->argument('action') === 'doctor') {
                $this->line(json_encode(app(ToolActions::class)->status(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

                return $availability->status()['available'] ? self::SUCCESS : self::FAILURE;
            }
            $availability->ensureAvailable();
            $id = (string) $this->argument('run');
            $result = match ($this->argument('action')) {
                'list' => ['runs' => array_map($runs->summary(...), array_slice($store->all(), 0, 50))],
                'status' => $runs->get($id),
                'cancel' => $runs->summary($processes->cancel($id)),
                'work' => $runs->summary(app(RunExecutor::class)->execute($id)),
                'prune' => ['removed' => $store->prune()],
                default => throw new \InvalidArgumentException,
            };
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e instanceof McpException ? $e->getMessage() : __('lens-for-laravel::messages.mcp.invalid_arguments'));

            return self::FAILURE;
        }
    }
}
