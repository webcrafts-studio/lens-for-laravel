<?php

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
