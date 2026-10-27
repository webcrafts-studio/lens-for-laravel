<?php

use LensForLaravel\LensForLaravel\Services\McpAvailability;

function fakeMcpAvailability(string $laravel, bool $sdk): McpAvailability
{
    return new class($laravel, $sdk) extends McpAvailability
    {
        public function __construct(private string $framework, private bool $installed) {}

        protected function laravelVersion(): string
        {
            return $this->framework;
        }

        protected function sdkInstalled(): bool
        {
            return $this->installed;
        }
    };
}

test('MCP availability requires Laravel 11 independently of optional AI', function () {
    config(['lens-for-laravel.mcp.enabled' => true, 'lens-for-laravel.ai_enabled' => false]);
    expect(fakeMcpAvailability('10.48.0', true)->status()['reason'])->toBe('unsupported_runtime')
        ->and(fakeMcpAvailability('11.45.3', true)->status()['available'])->toBeTrue()
        ->and(fakeMcpAvailability('12.41.1', true)->status()['available'])->toBeTrue()
        ->and(fakeMcpAvailability('13.0.0', true)->status()['available'])->toBeTrue();
});

test('MCP availability explains missing official SDK explicit disablement and environment', function () {
    config(['lens-for-laravel.mcp.enabled' => true]);
    expect(fakeMcpAvailability('12.41.1', false)->status()['reason'])->toBe('sdk_missing');
    config(['lens-for-laravel.mcp.enabled' => false]);
    expect(fakeMcpAvailability('12.41.1', true)->status()['reason'])->toBe('disabled');
    config(['lens-for-laravel.mcp.enabled' => true, 'lens-for-laravel.enabled_environments' => ['local']]);
    expect(fakeMcpAvailability('12.41.1', true)->status()['reason'])->toBe('environment');
});

test('MCP local installation marker enables integration unless explicitly disabled', function () {
    $marker = tempnam(sys_get_temp_dir(), 'lens-mcp-enabled-');
    $availability = new class($marker) extends McpAvailability
    {
        public function __construct(private string $path) {}

        public function markerPath(?string $root = null): string
        {
            return $this->path;
        }

        protected function sdkInstalled(): bool
        {
            return true;
        }

        protected function laravelVersion(): string
        {
            return '11.45.3';
        }
    };
    try {
        config(['lens-for-laravel.mcp.enabled' => null]);
        expect($availability->enabled())->toBeFalse();
        file_put_contents($marker, hash('sha256', base_path()));
        expect($availability->status()['available'])->toBeTrue();
        config(['lens-for-laravel.mcp.enabled' => false]);
        expect($availability->status()['reason'])->toBe('disabled');
    } finally {
        unlink($marker);
    }
});
