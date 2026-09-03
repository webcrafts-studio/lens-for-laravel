<?php

use LensForLaravel\LensForLaravel\DTOs\Issue;
use LensForLaravel\LensForLaravel\Services\AxeScanner;
use LensForLaravel\LensForLaravel\Services\FileLocator;
use LensForLaravel\LensForLaravel\Services\SiteCrawler;

// ── Multiple URL mode - exit 0 with no violations ─────────────────────────────

test('lens:audit accepts multiple url arguments and exits 0 with no violations', function () {
    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')->twice()->andReturn(collect());
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    $this->artisan('lens:audit', [
        'url' => ['https://example.com', 'https://example.com/about'],
    ])->assertExitCode(0)
        ->expectsOutputToContain('No violations found');
});

// ── Multiple URL mode - aggregates issues from all URLs ───────────────────────

test('lens:audit aggregates issues from multiple urls', function () {
    $issue = new Issue('image-alt', 'critical', 'desc', 'url', '<img>', 'img', ['wcag2a'], 'https://example.com');

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')->twice()->andReturn(collect([$issue]));
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    // 2 issues total (1 per URL), threshold=1 → exit 1 (2 > 1)
    $this->artisan('lens:audit', [
        'url' => ['https://example.com', 'https://example.com/about'],
        '--threshold' => '1',
    ])->assertExitCode(1);
});

// ── Multiple URL mode - quality gate ─────────────────────────────────────────

test('lens:audit exits 1 when combined violations exceed threshold', function () {
    $issues = collect([
        new Issue('image-alt', 'critical', 'desc', 'url', '<img>', 'img', ['wcag2a'], 'https://example.com'),
        new Issue('color-contrast', 'serious', 'desc', 'url', '<p>', 'p', ['wcag2aa'], 'https://example.com'),
        new Issue('label', 'critical', 'desc', 'url', '<input>', 'input', ['wcag2a'], 'https://example.com'),
    ]);

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')->twice()->andReturn($issues);
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    // 6 issues total (3 per URL × 2 URLs), threshold=5 → exit 1 (6 > 5)
    $this->artisan('lens:audit', [
        'url' => ['https://example.com', 'https://example.com/about'],
        '--threshold' => '5',
    ])->assertExitCode(1);
});

// ── Multiple URL mode - does not invoke SiteCrawler ───────────────────────────

test('lens:audit with multiple urls uses multi-url scan not crawl', function () {
    $crawlerMock = Mockery::mock(SiteCrawler::class);
    $crawlerMock->shouldNotReceive('crawl');
    app()->instance(SiteCrawler::class, $crawlerMock);

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')->twice()->andReturn(collect());
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    $this->artisan('lens:audit', [
        'url' => ['https://example.com', 'https://example.com/contact'],
        '--crawl' => true,
    ])->assertExitCode(0);
});
