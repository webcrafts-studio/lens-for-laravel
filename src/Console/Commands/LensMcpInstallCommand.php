<?php

namespace LensForLaravel\LensForLaravel\Console\Commands;

use Illuminate\Console\Command;
use LensForLaravel\LensForLaravel\Exceptions\McpException;
use LensForLaravel\LensForLaravel\Mcp\ClientConfigurationInstaller;
use LensForLaravel\LensForLaravel\Services\McpAvailability;

class LensMcpInstallCommand extends Command
{
    protected $signature = 'lens:mcp:install {--client= : codex or claude} {--force : Replace an existing Lens server entry}';

    protected $description = 'Configure a project-scoped Codex or Claude Code connection to Lens MCP';

    public function handle(ClientConfigurationInstaller $installer, McpAvailability $availability): int
    {
        $previous = config('lens-for-laravel.mcp.enabled');
        config(['lens-for-laravel.mcp.enabled' => true]);
        try {
            $availability->ensureAvailable();
            $client = $this->option('client') ?: $this->choice(__('lens-for-laravel::messages.mcp.choose_client'), ['codex', 'claude']);
            $path = $installer->install($client, base_path(), (bool) $this->option('force'));
            $this->info(__('lens-for-laravel::messages.mcp.installed', ['path' => $path]));

            return self::SUCCESS;
        } catch (McpException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            config(['lens-for-laravel.mcp.enabled' => $previous]);
        }
    }
}
