<?php

use LensForLaravel\LensForLaravel\Services\AxeScanner;
use Spatie\Browsershot\Browsershot;

class FakeBrowsershotForAxeScannerTest extends Browsershot
{
    public bool $ignoreHttpsErrorsCalled = false;

    public bool $delayWasSet = false;

    public string $evaluateResponse = '[]';

    public ?string $lastScript = null;

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
        $this->lastScript = $pageFunction;

        return $this->evaluateResponse;
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

test('interactive scanner maps state labels onto violations', function () {
    $fakeBrowsershot = new FakeBrowsershotForAxeScannerTest;
    $fakeBrowsershot->evaluateResponse = json_encode([
        'states' => [
            [
                'label' => 'Modal open',
                'violations' => [
                    [
                        'id' => 'button-name',
                        'impact' => 'critical',
                        'description' => 'Buttons must have discernible text',
                        'helpUrl' => 'https://example.com/rule',
                        'tags' => ['wcag2a'],
                        'nodes' => [
                            [
                                'html' => '<button></button>',
                                'target' => ['button.close'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $scanner = new class($fakeBrowsershot) extends AxeScanner
    {
        public function __construct(private readonly Browsershot $fakeBrowsershot) {}

        protected function browsershotForUrl(string $url): Browsershot
        {
            return $this->fakeBrowsershot;
        }
    };

    $issues = $scanner->scanInteractiveStates('https://example.com', [
        ['label' => 'Modal open', 'actions' => [['type' => 'click', 'selector' => '#open']]],
    ]);

    expect($issues)->toHaveCount(1)
        ->and($issues->first()->id)->toBe('button-name')
        ->and($issues->first()->selector)->toBe('button.close')
        ->and($issues->first()->stateLabel)->toBe('Modal open')
        ->and($fakeBrowsershot->lastScript)->toContain('const states =')
        ->and($fakeBrowsershot->lastScript)->toContain('clickWithoutNavigation')
        ->and($fakeBrowsershot->lastScript)->toContain("document.addEventListener('submit', preventDefault, true)");
});
