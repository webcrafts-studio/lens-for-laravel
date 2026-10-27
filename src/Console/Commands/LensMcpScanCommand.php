<?php

namespace LensForLaravel\LensForLaravel\Console\Commands;

use Illuminate\Console\Command;
use LensForLaravel\LensForLaravel\Exceptions\McpException;
use LensForLaravel\LensForLaravel\Mcp\RunService;
use LensForLaravel\LensForLaravel\Mcp\RunStore;
use LensForLaravel\LensForLaravel\Services\McpAvailability;

class LensMcpScanCommand extends Command
{
    protected $signature = 'lens:mcp:scan {url? : Target URL or application path}
        {--crawl : Discover and scan the website} {--urls=* : Multiple URLs}
        {--script= : Interactive-state script text, using the shared Lens grammar}
        {--wcag= : WCAG version} {--level=all : a, aa, or all} {--as-user= : Test user ID}
        {--queue : Persist a queued run for lens:mcp:runs work instead of executing now}';

    protected $description = 'Create a tracked Lens scan with JSON results and optional queued execution';

    public function handle(McpAvailability $availability, RunService $runs, RunStore $store): int
    {
        try {
            $availability->ensureAvailable();
            $urls = $this->option('urls');
            $script = $this->option('script');
            if (($script !== null && ($this->option('crawl') || $urls)) || ($urls && ($this->option('crawl') || $this->argument('url')))) {
                throw new McpException('INVALID_ARGUMENTS', 'invalid_arguments');
            }
            $input = ['mode' => $script !== null ? 'states' : ($urls ? 'multiple' : ($this->option('crawl') ? 'website' : 'single')),
                'level' => $this->option('level'), 'async' => false];
            if ($urls) {
                $input['urls'] = $urls;
            } else {
                $input['url'] = $this->argument('url') ?: '/';
            }
            if ($script !== null) {
                $input['script'] = $script;
            }
            if ($this->option('wcag') !== null) {
                $input['wcag'] = $this->option('wcag');
            }
            if ($this->option('as-user') !== null) {
                $input['as_user_id'] = $this->option('as-user');
            }
            $result = $this->option('queue') ? $runs->summary($store->create($runs->normalize($input))) : $runs->start($input);
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return in_array($result['status'], ['completed', 'queued'], true) ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error($e instanceof McpException ? $e->getMessage() : __('lens-for-laravel::messages.mcp.invalid_arguments'));

            return self::FAILURE;
        }
    }
}
