<?php

use LensForLaravel\LensForLaravel\Services\AxeScanner;
use Spatie\Browsershot\Browsershot;

class FakeBrowsershotForAxeScannerTest extends Browsershot
{
    public bool $ignoreHttpsErrorsCalled = false;

    public bool $delayWasSet = false;

    public function noSandbox(): static
    {
        return $this;
    }

    public function waitUntilNetworkIdle(bool $strict = true): static
    {
        return $this;
    }

    public function setDelay(int $delayInMilliseconds): static
    {
        $this->delayWasSet = true;

        return $this;
    }

    public function ignoreHttpsErrors(): static
    {
        $this->ignoreHttpsErrorsCalled = true;

        return $this;
    }

    public function evaluate(string $pageFunction): string
    {
        return '[]';
    }
}

test('scanner ignores https errors when configured', function () {
    config()->set('lens-for-laravel.ignore_https_errors', true);

    $fakeBrowsershot = new FakeBrowsershotForAxeScannerTest;
    $scanner = new class($fakeBrowsershot) extends AxeScanner
    {
        public function __construct(private readonly Browsershot $fakeBrowsershot) {}

        protected function browsershotForUrl(string $url): Browsershot
        {
            return $this->fakeBrowsershot;
        }
    };

    $scanner->scan('https://example.com');

    expect($fakeBrowsershot->ignoreHttpsErrorsCalled)->toBeTrue();
});

test('scanner keeps https errors strict by default', function () {
    config()->set('lens-for-laravel.ignore_https_errors', false);

    $fakeBrowsershot = new FakeBrowsershotForAxeScannerTest;
    $scanner = new class($fakeBrowsershot) extends AxeScanner
    {
        public function __construct(private readonly Browsershot $fakeBrowsershot) {}

        protected function browsershotForUrl(string $url): Browsershot
        {
            return $this->fakeBrowsershot;
        }
    };

    $scanner->scan('https://example.com');

    expect($fakeBrowsershot->ignoreHttpsErrorsCalled)->toBeFalse();
});
