<?php

namespace LensForLaravel\LensForLaravel\Services;

use LensForLaravel\LensForLaravel\Exceptions\McpException;

class McpAvailability
{
    public function status(): array
    {
        $key = match (true) {
            version_compare($this->laravelVersion(), '11.0.0', '<') => 'unsupported_runtime',
            ! $this->sdkInstalled() => 'sdk_missing',
            ! $this->enabled() => 'disabled',
            ! in_array(app()->environment(), config('lens-for-laravel.enabled_environments', ['local']), true) => 'environment',
            default => null,
        };

        return ['available' => $key === null, 'reason' => $key, 'message' => $key ? __("lens-for-laravel::messages.mcp.{$key}") : null];
    }

    public function ensureAvailable(): void
    {
        $status = $this->status();
        if (! $status['available']) {
            throw new McpException('MCP_UNAVAILABLE', $status['reason']);
        }
    }

    public function enabled(): bool
    {
        $configured = config('lens-for-laravel.mcp.enabled');
        if ($configured !== null) {
            return (bool) $configured;
        }
        $marker = $this->markerPath();

        return is_file($marker) && ! is_link($marker) && trim((string) file_get_contents($marker)) === hash('sha256', base_path());
    }

    public function markerPath(?string $root = null): string
    {
        $root ??= base_path();
        $storage = $root === base_path() ? storage_path() : $root.'/storage';

        return $storage.'/app/lens-for-laravel/mcp-enabled-'.substr(hash('sha256', $root), 0, 16);
    }

    protected function laravelVersion(): string
    {
        return app()->version();
    }

    protected function sdkInstalled(): bool
    {
        return class_exists('Laravel\\Mcp\\Server') && class_exists('Laravel\\Mcp\\Facades\\Mcp');
    }
}
