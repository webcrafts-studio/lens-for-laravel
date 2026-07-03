<?php

use LensForLaravel\LensForLaravel\DTOs\Issue;
use LensForLaravel\LensForLaravel\Exceptions\ScannerException;
use LensForLaravel\LensForLaravel\Services\AxeScanner;
use LensForLaravel\LensForLaravel\Services\FileLocator;

test('POST /scan requires url', function () {
    $this->postJson(route('lens-for-laravel.scan'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['url']);
});

test('POST /scan rejects invalid url format', function () {
    $this->postJson(route('lens-for-laravel.scan'), ['url' => 'not-a-url'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['url']);
});

test('POST /scan returns 403 when environment not allowed', function () {
    $this->app['config']->set('lens-for-laravel.enabled_environments', ['local']);

    $this->postJson(route('lens-for-laravel.scan'), ['url' => 'http://localhost'])
        ->assertStatus(403);
});

test('POST /scan returns violations on success', function () {
    $mockIssue = new Issue(
        id: 'image-alt',
        impact: 'critical',
        description: 'Images must have alternate text',
        helpUrl: 'https://dequeuniversity.com/image-alt',
        htmlSnippet: '<img src="x.png">',
        selector: 'img',
        tags: ['wcag2a'],
        url: 'http://localhost',
    );

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')
        ->once()
        ->with('http://localhost')
        ->andReturn(collect([$mockIssue]));
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    $this->postJson(route('lens-for-laravel.scan'), ['url' => 'http://localhost'])
        ->assertStatus(200)
        ->assertJson(['status' => 'success'])
        ->assertJsonStructure(['status', 'issues']);
});

test('POST /scan passes the selected WCAG version to the scanner', function () {
    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')
        ->once()
        ->with('http://localhost', '2.2')
        ->andReturn(collect());
    app()->instance(AxeScanner::class, $scannerMock);

    app()->instance(FileLocator::class, Mockery::mock(FileLocator::class));

    $this->postJson(route('lens-for-laravel.scan'), [
        'url' => 'http://localhost',
        'wcagVersion' => '2.2',
    ])->assertOk();
});

test('POST /scan rejects unsupported WCAG versions', function () {
    $this->postJson(route('lens-for-laravel.scan'), [
        'url' => 'http://localhost',
        'wcagVersion' => '3.0',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['wcagVersion']);
});

test('POST /scan returns 500 when scanner throws exception', function () {
    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')
        ->andThrow(new ScannerException('Puppeteer not available'));
    app()->instance(AxeScanner::class, $scannerMock);

    $this->postJson(route('lens-for-laravel.scan'), ['url' => 'http://localhost'])
        ->assertStatus(500)
        ->assertJson(['status' => 'error']);
});

test('POST /scan enriches issues with blade file locations', function () {
    $mockIssue = new Issue(
        id: 'image-alt',
        impact: 'critical',
        description: 'desc',
        helpUrl: 'https://help.url',
        htmlSnippet: '<img id="logo" src="x.png">',
        selector: '#logo',
        tags: ['wcag2a'],
        url: 'http://localhost',
    );

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')->andReturn(collect([$mockIssue]));
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')
        ->andReturn(['file' => 'layouts/app.blade.php', 'line' => 15, 'type' => 'blade']);
    app()->instance(FileLocator::class, $locatorMock);

    $response = $this->postJson(route('lens-for-laravel.scan'), ['url' => 'http://localhost'])
        ->assertStatus(200);

    $issues = $response->json('issues');
    expect($issues[0]['fileName'])->toBe('layouts/app.blade.php')
        ->and($issues[0]['lineNumber'])->toBe(15)
        ->and($issues[0]['sourceType'])->toBe('blade');
});
