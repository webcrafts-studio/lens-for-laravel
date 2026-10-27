<?php

namespace LensForLaravel\LensForLaravel\Console\Commands;

use Illuminate\Console\Command;
use LensForLaravel\LensForLaravel\Exceptions\McpException;
use LensForLaravel\LensForLaravel\Mcp\LensServer;
use LensForLaravel\LensForLaravel\Mcp\ManagedStdioTransport;
use LensForLaravel\LensForLaravel\Mcp\RunProcessManager;
use LensForLaravel\LensForLaravel\Services\McpAvailability;

class LensMcpCommand extends Command
{
    protected $signature = 'lens:mcp {--enabled : Explicitly enable MCP for this client process}';

    protected $description = 'Serve Lens tools over local MCP stdio using the optional laravel/mcp package';

    public function handle(McpAvailability $availability, RunProcessManager $processes): int
    {
        if ($this->option('enabled')) {
            config(['lens-for-laravel.mcp.enabled' => true]);
        }
        try {
            $availability->ensureAvailable();
            ini_set('display_errors', 'stderr');
            $transport = new ManagedStdioTransport;
            $server = app(LensServer::class, ['transport' => $transport]);
            $server->start();
            if (function_exists('pcntl_signal')) {
                $this->trap([SIGTERM, SIGINT], function () use ($processes) {
                    $processes->shutdown();
                    exit(self::SUCCESS);
                });
            }
            $transport->run();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            fwrite(STDERR, ($e instanceof McpException ? $e->getMessage() : __('lens-for-laravel::messages.mcp.tool_failed')).PHP_EOL);

            return self::FAILURE;
        } finally {
            $processes->shutdown();
        }
    }
}
