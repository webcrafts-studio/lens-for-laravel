<?php

use LensForLaravel\LensForLaravel\DTOs\Issue;
use LensForLaravel\LensForLaravel\Exceptions\ScannerException;
use LensForLaravel\LensForLaravel\Services\AxeScanner;
use LensForLaravel\LensForLaravel\Services\BaselineManager;
use LensForLaravel\LensForLaravel\Services\FileLocator;
use LensForLaravel\LensForLaravel\Services\SiteCrawler;

// ── Command registration ───────────────────────────────────────────────────────

test('lens:audit command is registered', function () {
    $commands = collect(Artisan::all());
    expect($commands->has('lens:audit'))->toBeTrue();
});

// ── Exit code 0: no violations ────────────────────────────────────────────────

test('exits 0 and shows success when no violations found', function () {
    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')->andReturn(collect());
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    $this->artisan('lens:audit', ['url' => 'https://example.com'])
        ->assertExitCode(0)
        ->expectsOutputToContain('No violations found');
});

// ── Exit code 1: threshold exceeded ───────────────────────────────────────────

test('exits 1 when violations exceed threshold of 0', function () {
    $issues = collect([
        new Issue('image-alt', 'critical', 'desc', 'url', '<img>', 'img', ['wcag2a'], 'https://example.com'),
    ]);

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')->andReturn($issues);
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    $this->artisan('lens:audit', [
        'url' => 'https://example.com',
        '--threshold' => '0',
    ])->assertExitCode(1);
});

test('exits 0 when violations do not exceed threshold', function () {
    $issues = collect([
        new Issue('image-alt', 'critical', 'desc', 'url', '<img>', 'img', ['wcag2a'], 'https://example.com'),
        new Issue('color-contrast', 'serious', 'desc', 'url', '<p>', 'p', ['wcag2aa'], 'https://example.com'),
    ]);

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')->andReturn($issues);
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    $this->artisan('lens:audit', [
        'url' => 'https://example.com',
        '--threshold' => '5',
    ])->assertExitCode(0);
});

// ── Level filtering via flag ───────────────────────────────────────────────────

test('--a flag filters out non-wcag2a violations from output', function () {
    $issues = collect([
        new Issue('rule-a', 'critical', 'A-level issue', 'url', '<img>', 'img', ['wcag2a'], 'https://example.com'),
        new Issue('rule-aa', 'serious', 'AA-level issue', 'url', '<p>', 'p', ['wcag2aa'], 'https://example.com'),
    ]);

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')->andReturn($issues);
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    // With --a, only 1 issue is reported, threshold=0 → exit 1 (1 > 0)
    $this->artisan('lens:audit', [
        'url' => 'https://example.com',
        '--a' => true,
        '--threshold' => '0',
    ])->assertExitCode(1);
});

test('--aa flag includes wcag2a and wcag2aa violations', function () {
    $issues = collect([
        new Issue('rule-a', 'critical', 'desc', 'url', '<img>', 'img', ['wcag2a'], 'https://example.com'),
        new Issue('rule-aa', 'serious', 'desc', 'url', '<p>', 'p', ['wcag2aa'], 'https://example.com'),
        new Issue('rule-aaa', 'moderate', 'desc', 'url', '<div>', 'div', ['wcag2aaa'], 'https://example.com'),
    ]);

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')->andReturn($issues);
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    // --aa: 2 issues filtered, threshold=1 → exit 1 (2 > 1)
    $this->artisan('lens:audit', [
        'url' => 'https://example.com',
        '--aa' => true,
        '--threshold' => '1',
    ])->assertExitCode(1);
});

// ── Scanner failure ────────────────────────────────────────────────────────────

test('exits 1 when scanner throws exception', function () {
    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')
        ->andThrow(new ScannerException('Puppeteer not available'));
    app()->instance(AxeScanner::class, $scannerMock);

    $this->artisan('lens:audit', ['url' => 'https://example.com'])
        ->assertExitCode(1);
});

// ── Crawl mode ────────────────────────────────────────────────────────────────

test('--crawl flag uses SiteCrawler and aggregates violations', function () {
    $crawlerMock = Mockery::mock(SiteCrawler::class);
    $crawlerMock->shouldReceive('crawl')
        ->andReturn(['https://example.com', 'https://example.com/about']);
    app()->instance(SiteCrawler::class, $crawlerMock);

    $issues = collect([
        new Issue('image-alt', 'critical', 'desc', 'url', '<img>', 'img', ['wcag2a'], 'https://example.com'),
    ]);

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')->andReturn($issues);
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    $this->artisan('lens:audit', [
        'url' => 'https://example.com',
        '--crawl' => true,
        '--threshold' => '100',
    ])->assertExitCode(0);
});

// ── Baseline quality gate ────────────────────────────────────────────────────

test('--baseline writes the current filtered violations and exits 0', function () {
    $path = tempnam(sys_get_temp_dir(), 'lens-baseline-');

    $issues = collect([
        new Issue('image-alt', 'critical', 'desc', 'url', '<img>', 'img', ['wcag2a'], 'https://example.com'),
        new Issue('color-contrast', 'serious', 'desc', 'url', '<p>', 'p', ['wcag2aa'], 'https://example.com'),
    ]);

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')->andReturn($issues);
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    $this->artisan('lens:audit', [
        'url' => 'https://example.com',
        '--baseline' => true,
        '--baseline-file' => $path,
        '--threshold' => '0',
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Baseline saved with 2 issue(s)');

    $baseline = json_decode(file_get_contents($path), true);

    expect($baseline['issue_count'])->toBe(2)
        ->and($baseline['issues'])->toHaveCount(2);

    @unlink($path);
});

test('--baseline respects wcag level filters', function () {
    $path = tempnam(sys_get_temp_dir(), 'lens-baseline-');

    $issues = collect([
        new Issue('rule-a', 'critical', 'desc', 'url', '<img>', 'img', ['wcag2a'], 'https://example.com'),
        new Issue('rule-aa', 'serious', 'desc', 'url', '<p>', 'p', ['wcag2aa'], 'https://example.com'),
    ]);

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')->andReturn($issues);
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    $this->artisan('lens:audit', [
        'url' => 'https://example.com',
        '--baseline' => true,
        '--baseline-file' => $path,
        '--a' => true,
    ])->assertExitCode(0);

    $baseline = json_decode(file_get_contents($path), true);

    expect($baseline['issue_count'])->toBe(1)
        ->and($baseline['issues'][0]['rule_id'])->toBe('rule-a');

    @unlink($path);
});

test('--fail-on-new exits 0 when current violations already exist in baseline', function () {
    $path = tempnam(sys_get_temp_dir(), 'lens-baseline-');
    $issue = new Issue('image-alt', 'critical', 'desc', 'url', '<img>', 'img', ['wcag2a'], 'https://example.com');

    app(BaselineManager::class)->write(collect([$issue]), $path);

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')->andReturn(collect([$issue]));
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    $this->artisan('lens:audit', [
        'url' => 'https://example.com',
        '--fail-on-new' => true,
        '--baseline-file' => $path,
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Baseline gate passed');

    @unlink($path);
});

test('--fail-on-new exits 1 when a new violation is not in baseline', function () {
    $path = tempnam(sys_get_temp_dir(), 'lens-baseline-');

    $existing = new Issue('image-alt', 'critical', 'desc', 'url', '<img>', 'img', ['wcag2a'], 'https://example.com');
    $new = new Issue('color-contrast', 'serious', 'desc', 'url', '<p>', 'p', ['wcag2aa'], 'https://example.com');

    app(BaselineManager::class)->write(collect([$existing]), $path);

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')->andReturn(collect([$existing, $new]));
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    $this->artisan('lens:audit', [
        'url' => 'https://example.com',
        '--fail-on-new' => true,
        '--baseline-file' => $path,
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('Baseline gate failed: 1 new violation(s) found');

    @unlink($path);
});

test('--fail-on-new exits 1 when baseline file is missing', function () {
    $path = sys_get_temp_dir().'/lens-missing-baseline-'.bin2hex(random_bytes(6)).'.json';

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')->andReturn(collect());
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    $this->artisan('lens:audit', [
        'url' => 'https://example.com',
        '--fail-on-new' => true,
        '--baseline-file' => $path,
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('Baseline file not found');
});

test('baseline and fail-on-new options cannot be used together', function () {
    $this->artisan('lens:audit', [
        'url' => 'https://example.com',
        '--baseline' => true,
        '--fail-on-new' => true,
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('Use either --baseline or --fail-on-new');
});
