<?php

use LensForLaravel\LensForLaravel\Support\Wcag;

test('supported WCAG versions return cumulative axe-core tags', function () {
    expect(Wcag::tags('2.0'))
        ->toBe(['wcag2a', 'wcag2aa', 'wcag2aaa', 'best-practice'])
        ->and(Wcag::tags('2.1'))->toContain('wcag2a', 'wcag21a', 'wcag21aa')
        ->and(Wcag::tags('2.2'))->toContain('wcag2a', 'wcag21aa', 'wcag22aa');
});

test('WCAG level classification recognizes newer standard tags', function () {
    expect(Wcag::level(['wcag21a']))->toBe('a')
        ->and(Wcag::level(['wcag21aa']))->toBe('aa')
        ->and(Wcag::level(['wcag22aa']))->toBe('aa')
        ->and(Wcag::level(['wcag2aaa']))->toBe('aaa')
        ->and(Wcag::level(['best-practice']))->toBe('other');
});

test('invalid configured WCAG versions fall back to 2.0', function () {
    config()->set('lens-for-laravel.wcag_version', '3.0');

    expect(Wcag::configuredVersion())->toBe('2.0');
});
