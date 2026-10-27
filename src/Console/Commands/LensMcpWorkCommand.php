<?php

namespace LensForLaravel\LensForLaravel\Console\Commands;

use Illuminate\Console\Command;
use LensForLaravel\LensForLaravel\Mcp\RunExecutor;
use LensForLaravel\LensForLaravel\Services\McpAvailability;

class LensMcpWorkCommand extends Command
{
    protected $signature = 'lens:mcp:work {run : Server-issued run UUID} {--enabled}';

    protected $description = 'Execute a queued Lens MCP scan in an isolated worker';

    protected $hidden = true;

    public function handle(McpAvailability $availability, RunExecutor $executor): int
    {
        if ($this->option('enabled')) {
            config(['lens-for-laravel.mcp.enabled' => true]);
        }
        try {
            $availability->ensureAvailable();
            if (function_exists('posix_setsid')) {
                @posix_setsid();
            }
            if (function_exists('pcntl_signal')) {
                $this->trap([SIGTERM, SIGINT], fn () => exit(self::FAILURE));
            }
            $run = $executor->execute($this->argument('run'));

            return $run['status'] === 'completed' ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable) {
            return self::FAILURE;
        }
    }
}
