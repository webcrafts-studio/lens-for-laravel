<?php

use LensForLaravel\LensForLaravel\DTOs\Issue;
use LensForLaravel\LensForLaravel\Services\BaselineManager;

test('baseline fingerprints ignore scheme and host differences', function () {
    $manager = new BaselineManager;

    $local = new Issue(
        id: 'button-name',
        impact: 'critical',
        description: 'Buttons must have discernible text',
        helpUrl: 'https://example.com',
        htmlSnippet: '<button></button>',
        selector: 'button.primary',
        tags: ['wcag2a'],
        url: 'https://app.test/dashboard?tab=main',
        fileName: 'components/button.blade.php',
        lineNumber: 12,
        sourceType: 'blade'
    );

    $ci = new Issue(
        id: 'button-name',
        impact: 'critical',
        description: 'Buttons must have discernible text',
        helpUrl: 'https://example.com',
        htmlSnippet: '<button></button>',
        selector: 'button.primary',
        tags: ['wcag2a'],
        url: 'http://127.0.0.1:8000/dashboard?tab=main',
        fileName: 'components/button.blade.php',
        lineNumber: 18,
        sourceType: 'blade'
    );

    expect($manager->fingerprint($local))->toBe($manager->fingerprint($ci));
});

test('baseline comparison separates new existing and fixed issues', function () {
    $manager = new BaselineManager;
    $path = tempnam(sys_get_temp_dir(), 'lens-baseline-');

    $existing = new Issue('image-alt', 'critical', 'desc', 'url', '<img>', 'img.logo', ['wcag2a'], 'https://example.com');
    $fixed = new Issue('label', 'serious', 'desc', 'url', '<input>', 'input[name=email]', ['wcag2a'], 'https://example.com');
    $new = new Issue('color-contrast', 'serious', 'desc', 'url', '<p>', 'p.intro', ['wcag2aa'], 'https://example.com');

    $manager->write(collect([$existing, $fixed]), $path);

    $comparison = $manager->compare(collect([$existing, $new]), $path);

    expect($comparison['existing'])->toHaveCount(1)
        ->and($comparison['new'])->toHaveCount(1)
        ->and($comparison['fixed'])->toHaveCount(1)
        ->and($comparison['new']->first()['rule_id'])->toBe('color-contrast');

    @unlink($path);
});

test('default baseline path resolves relative config paths from the application root', function () {
    config()->set('lens-for-laravel.baseline_path', 'storage/app/custom-lens-baseline.json');

    expect((new BaselineManager)->defaultPath())
        ->toBe(base_path('storage/app/custom-lens-baseline.json'));
});
