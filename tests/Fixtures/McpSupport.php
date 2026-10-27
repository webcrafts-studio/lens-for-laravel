<?php

use LensForLaravel\LensForLaravel\DTOs\Issue;
use LensForLaravel\LensForLaravel\Mcp\RunService;
use LensForLaravel\LensForLaravel\Services\AxeScanner;

function lensMcpIssue(?string $file = null, int $line = 2): Issue
{
    return new Issue('button-name', 'serious', 'Button needs a name', '', '<button>Save</button>', 'button:nth-child(2)', ['wcag2a'], 'http://localhost/', $file, $file ? $line : null, $file ? 'blade' : null);
}

function lensMcpRun(array $issues = [], array $input = []): array
{
    app()->instance(AxeScanner::class, Mockery::mock(AxeScanner::class)->shouldReceive('scan')->andReturn(collect($issues))->getMock());

    return app(RunService::class)->start(['url' => '/', 'async' => false, 'save_history' => false, ...$input]);
}
