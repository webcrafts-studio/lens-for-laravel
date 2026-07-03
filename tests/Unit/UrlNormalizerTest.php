<?php

use LensForLaravel\LensForLaravel\Support\UrlNormalizer;

test('normalizes absolute URLs to path and query', function () {
    expect(UrlNormalizer::pathAndQuery('https://example.test/account?tab=billing#details'))
        ->toBe('/account?tab=billing')
        ->and(UrlNormalizer::pathAndQuery('http://127.0.0.1:8000/account?tab=billing'))
        ->toBe('/account?tab=billing');
});

test('keeps different paths and queries distinct', function () {
    expect(UrlNormalizer::pathAndQuery('https://example.test/account'))
        ->not->toBe(UrlNormalizer::pathAndQuery('https://example.test/checkout'))
        ->and(UrlNormalizer::pathAndQuery('https://example.test/account?tab=profile'))
        ->not->toBe(UrlNormalizer::pathAndQuery('https://example.test/account?tab=billing'));
});
