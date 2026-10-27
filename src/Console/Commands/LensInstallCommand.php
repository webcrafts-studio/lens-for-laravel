<?php

namespace LensForLaravel\LensForLaravel\Console\Commands;

use Illuminate\Console\Command;
use LensForLaravel\LensForLaravel\Mcp\ClientConfigurationInstaller;
use LensForLaravel\LensForLaravel\Services\AiFixAvailability;
use LensForLaravel\LensForLaravel\Services\McpAvailability;
use LensForLaravel\LensForLaravel\Support\InstallationState;
use RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;

class LensInstallCommand extends Command
{
    protected $signature = 'lens:install';

    protected $description = 'Show the first-install welcome and optional Lens integrations';

    public function handle(InstallationState $state, AiFixAvailability $ai, McpAvailability $mcp, ClientConfigurationInstaller $clients): int
    {
        $previousLocale = app()->getLocale();
        $locale = config('lens-for-laravel.locale', $previousLocale);
        $supported = config('lens-for-laravel.supported_locales', ['en' => 'English']);
        app()->setLocale(isset($supported[$locale]) ? $locale : config('lens-for-laravel.fallback_locale', 'en'));

        try {
            try {
                $firstInstall = $state->markInstalled();
            } catch (RuntimeException) {
                $this->components->error($this->message('failed'));

                return self::FAILURE;
            }

            $this->components->info(OutputFormatter::escape($this->message($firstInstall ? 'installed' : 'already_installed')));
            $this->line('  <options=bold>'.OutputFormatter::escape($this->message('dashboard')).'</>');
            $this->link(route('lens-for-laravel.dashboard'));

            if (! $firstInstall) {
                $this->newLine();

                return self::SUCCESS;
            }

            $this->newLine();
            $aiStatus = $ai->status();
            $aiAvailable = $aiStatus['available'];
            $this->integration('AI Fix', $aiAvailable ? 'sdk_installed' : 'optional', 'ai_description', $aiAvailable);
            if (! $aiAvailable) {
                $this->detail($this->message('ai_requirements'));
                if (in_array($aiStatus['reason'], ['disabled', 'sdk_unsupported'], true)) {
                    $this->detail($aiStatus['message']);
                }
            }
            $this->link('https://lens.webcrafts.pl/docs/ai-fix-engine');

            $this->newLine();
            $mcpStatus = $mcp->status();
            $sdkInstalled = ! in_array($mcpStatus['reason'], ['unsupported_runtime', 'sdk_missing'], true);
            $configured = $mcpStatus['available'] && $clients->configured(base_path());
            $this->integration('Lens MCP', $configured ? 'client_configured' : ($sdkInstalled ? 'sdk_installed' : 'optional'), 'mcp_description', $sdkInstalled);
            if (! $sdkInstalled) {
                $this->detail($this->message('mcp_requirements'));
            } elseif ($mcpStatus['reason'] === 'environment' || config('lens-for-laravel.mcp.enabled') === false) {
                $this->detail($mcpStatus['message']);
            } elseif (! $configured) {
                $this->detail($this->message('mcp_setup'));
            }
            $this->link('https://lens.webcrafts.pl/docs');

            $this->newLine();
            $this->line('  <fg=yellow>☆</> <fg=gray>'.OutputFormatter::escape($this->message('star')).'</>');
            $this->link('https://github.com/webcrafts-studio/lens-for-laravel');
            $this->newLine();

            return self::SUCCESS;
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    private function message(string $key): string
    {
        return __('lens-for-laravel::messages.install.'.$key);
    }

    private function integration(string $name, string $status, string $description, bool $installed): void
    {
        $color = $installed ? 'green' : 'gray';
        $this->line('  <options=bold>'.$name.'</>  <fg='.$color.'>'.OutputFormatter::escape($this->message($status)).'</> <fg=gray>· '.OutputFormatter::escape($this->message($description)).'</>');
    }

    private function detail(?string $text): void
    {
        $this->line('  <fg=gray>'.OutputFormatter::escape($text ?? '').'</>');
    }

    private function link(string $url): void
    {
        $this->line('  <fg=blue>'.OutputFormatter::escape($url).'</>');
    }
}
