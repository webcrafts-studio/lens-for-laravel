<?php

use LensForLaravel\LensForLaravel\Services\AiFixAvailability;

function aiFixAvailability(int $phpVersionId, string $laravelVersion, bool $sdkInstalled): AiFixAvailability
{
    return new class($phpVersionId, $laravelVersion, $sdkInstalled) extends AiFixAvailability
    {
        public function __construct(
            private readonly int $fakePhpVersionId,
            private readonly string $fakeLaravelVersion,
            private readonly bool $fakeSdkInstalled,
        ) {}

        protected function phpVersionId(): int
        {
            return $this->fakePhpVersionId;
        }

        protected function laravelVersion(): string
        {
            return $this->fakeLaravelVersion;
        }

        protected function sdkInstalled(): bool
        {
            return $this->fakeSdkInstalled;
        }
    };
}

test('AI Fix is available on supported runtimes when the optional SDK is installed', function () {
    $availability = aiFixAvailability(80300, '12.0.0', true);

    expect($availability->available())->toBeTrue()
        ->and($availability->message())->toBeNull();
});

test('AI Fix is unavailable on PHP versions older than 8.3', function () {
    $availability = aiFixAvailability(80200, '12.0.0', false);

    expect($availability->available())->toBeFalse()
        ->and($availability->message())->toContain('PHP 8.3');
});

test('AI Fix is unavailable on Laravel versions older than 12', function () {
    $availability = aiFixAvailability(80300, '11.0.0', false);

    expect($availability->available())->toBeFalse()
        ->and($availability->message())->toContain('Laravel 12');
});

test('AI Fix is unavailable when the optional SDK is not installed', function () {
    $availability = aiFixAvailability(80300, '12.0.0', false);

    expect($availability->available())->toBeFalse()
        ->and($availability->message())->toContain('laravel/ai');
});

test('AI Fix can be disabled explicitly while core scanning remains available', function () {
    config()->set('lens-for-laravel.ai_enabled', false);

    $availability = aiFixAvailability(80300, '12.0.0', true);

    expect($availability->available())->toBeFalse()
        ->and($availability->message())->toContain('disabled by configuration');
});
