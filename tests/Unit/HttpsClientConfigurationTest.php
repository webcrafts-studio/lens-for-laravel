<?php

use Illuminate\Support\Facades\Http;
use LensForLaravel\LensForLaravel\Services\HttpsClientConfiguration;
use Spatie\Browsershot\Browsershot;

class FakeBrowsershotForHttpsConfigurationTest extends Browsershot
{
    public bool $ignoreHttpsErrorsCalled = false;

    public function ignoreHttpsErrors(): static
    {
        $this->ignoreHttpsErrorsCalled = true;

        return $this;
    }
}

test('shared HTTPS configuration disables verification for HTTP and Chromium clients', function () {
    config()->set('lens-for-laravel.ignore_https_errors', true);

    $configuration = new HttpsClientConfiguration;
    $http = $configuration->configureHttp(Http::timeout(5));
    $browser = new FakeBrowsershotForHttpsConfigurationTest;
    $configuration->configureBrowser($browser);

    expect($http->getOptions())->toHaveKey('verify', false)
        ->and($browser->ignoreHttpsErrorsCalled)->toBeTrue();
});

test('shared HTTPS configuration verifies certificates by default', function () {
    config()->set('lens-for-laravel.ignore_https_errors', false);

    $configuration = new HttpsClientConfiguration;
    $http = $configuration->configureHttp(Http::timeout(5));
    $browser = new FakeBrowsershotForHttpsConfigurationTest;
    $configuration->configureBrowser($browser);

    expect($http->getOptions())->not->toHaveKey('verify')
        ->and($browser->ignoreHttpsErrorsCalled)->toBeFalse();
});
