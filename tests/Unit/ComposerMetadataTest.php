<?php

use Composer\Semver\Semver;
use LensForLaravel\LensForLaravel\Services\AiSdkCompatibility;

test('Composer metadata describes the package rather than a skeleton application', function () {
    $composer = json_decode(
        file_get_contents(__DIR__.'/../../composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($composer['name'])->toBe('webcrafts-studio/lens-for-laravel')
        ->and($composer['type'])->toBe('library')
        ->and($composer['description'])->toContain('local-first WCAG accessibility auditor')
        ->and($composer['description'])->not->toContain('skeleton')
        ->and($composer['homepage'])->toBe('https://lens.webcrafts.pl')
        ->and($composer['suggest'])->toHaveKey('laravel/ai')
        ->and($composer)->not->toHaveKey('version');
});

test('optional SDK constraints preserve compatible releases without requiring either SDK', function () {
    $composer = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($composer['require'])->not->toHaveKey('laravel/ai')->not->toHaveKey('laravel/mcp')
        ->and($composer['require-dev'])->not->toHaveKey('laravel/ai');

    foreach (['0.3.1' => false, '0.3.2' => true, '0.4.5' => true, '0.11.3' => true, '0.12.0' => false, '1.0.0' => true, '1.3.0' => true, '2.0.0' => false] as $version => $supported) {
        expect(Semver::satisfies($version, $composer['conflict']['laravel/ai']))->toBe(! $supported)
            ->and(Semver::satisfies($version, AiSdkCompatibility::CONSTRAINT))->toBe($supported);
    }
    foreach (['0.5.2' => false, '0.5.3' => true, '0.6.0' => true, '0.7.2' => true, '0.8.2' => true, '0.9.7' => true, '0.10.0' => false, '1.1.2' => true, '2.0.0' => false] as $version => $supported) {
        expect(Semver::satisfies($version, $composer['conflict']['laravel/mcp']))->toBe(! $supported);
    }
});
