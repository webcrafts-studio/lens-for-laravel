<?php

use LensForLaravel\LensForLaravel\DTOs\Issue;
use LensForLaravel\LensForLaravel\Services\AxeScanner;
use LensForLaravel\LensForLaravel\Services\FileLocator;

test('POST /scan/states requires url and script', function () {
    $this->postJson(route('lens-for-laravel.scan.states'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['url', 'script']);
});

test('POST /scan/states rejects malformed interaction scripts', function () {
    $this->postJson(route('lens-for-laravel.scan.states'), [
        'url' => 'http://localhost',
        'script' => 'click: button',
    ])
        ->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'add a state'));
});

test('POST /scan/states returns interactive state issues with source locations', function () {
    $issues = collect([
        new Issue(
            id: 'button-name',
            impact: 'critical',
            description: 'Buttons must have discernible text',
            helpUrl: 'https://example.com/button-name',
            htmlSnippet: '<button></button>',
            selector: 'button.close',
            tags: ['wcag2a'],
            url: 'http://localhost',
            stateLabel: 'Modal open'
        ),
    ]);

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scanInteractiveStates')
        ->once()
        ->with('http://localhost', Mockery::on(function (array $states) {
            return count($states) === 1
                && $states[0]['label'] === 'Modal open'
                && $states[0]['actions'][0]['type'] === 'click'
                && $states[0]['actions'][0]['selector'] === '[data-open-modal]';
        }))
        ->andReturn($issues);
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')
        ->once()
        ->with('<button></button>', 'button.close')
        ->andReturn(['file' => 'components/modal.blade.php', 'line' => 14, 'type' => 'blade']);
    app()->instance(FileLocator::class, $locatorMock);

    $this->postJson(route('lens-for-laravel.scan.states'), [
        'url' => 'http://localhost',
        'script' => "state: Modal open\nclick: [data-open-modal]",
    ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('states.0.label', 'Modal open')
        ->assertJsonPath('states.0.actionCount', 1)
        ->assertJsonPath('issues.0.id', 'button-name')
        ->assertJsonPath('issues.0.stateLabel', 'Modal open')
        ->assertJsonPath('issues.0.fileName', 'components/modal.blade.php')
        ->assertJsonPath('issues.0.lineNumber', 14);
});

test('POST /scan/states passes the selected WCAG version to the scanner', function () {
    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scanInteractiveStates')
        ->once()
        ->with('http://localhost', Mockery::type('array'), '2.1')
        ->andReturn(collect());
    app()->instance(AxeScanner::class, $scannerMock);

    app()->instance(FileLocator::class, Mockery::mock(FileLocator::class));

    $this->postJson(route('lens-for-laravel.scan.states'), [
        'url' => 'http://localhost',
        'script' => 'state: Initial',
        'wcagVersion' => '2.1',
    ])->assertOk();
});

test('POST /scan/states is blocked in non-allowed environment', function () {
    $this->app['config']->set('lens-for-laravel.enabled_environments', ['production']);

    $this->postJson(route('lens-for-laravel.scan.states'), [
        'url' => 'http://localhost',
        'script' => 'state: Initial',
    ])->assertStatus(403);
});

test('POST /scan/states rejects external domains', function () {
    $this->postJson(route('lens-for-laravel.scan.states'), [
        'url' => 'https://external.test',
        'script' => 'state: Initial',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['url']);
});
