<?php

use LensForLaravel\LensForLaravel\Services\HttpsClientConfiguration;
use Spatie\Browsershot\Browsershot;

class FakeBrowsershotForPreviewHttpsTest extends Browsershot
{
    public function noSandbox(): static
    {
        return $this;
    }

    public function waitUntilNetworkIdle(bool $strict = true): static
    {
        return $this;
    }

    public function windowSize(int $width, int $height): static
    {
        return $this;
    }

    public function setOption($key, $value): static
    {
        return $this;
    }

    public function screenshot(): string
    {
        return 'preview-png';
    }
}

test('POST /preview requires url', function () {
    $this->postJson(route('lens-for-laravel.preview'), ['selector' => 'img'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['url']);
});

test('POST /preview requires selector', function () {
    $this->postJson(route('lens-for-laravel.preview'), ['url' => 'http://localhost'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['selector']);
});

test('POST /preview rejects selector longer than 500 characters', function () {
    $this->postJson(route('lens-for-laravel.preview'), [
        'url' => 'http://localhost',
        'selector' => str_repeat('a', 501),
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['selector']);
});

test('POST /preview returns 403 when environment not allowed', function () {
    $this->app['config']->set('lens-for-laravel.enabled_environments', ['local']);

    $this->postJson(route('lens-for-laravel.preview'), [
        'url' => 'http://localhost',
        'selector' => 'img',
    ])->assertStatus(403);
});

test('POST /preview returns error json when browsershot fails', function () {
    $configuration = Mockery::mock(HttpsClientConfiguration::class);
    $configuration->shouldReceive('configureBrowser')
        ->once()
        ->andThrow(new RuntimeException('Browsershot failed'));
    app()->instance(HttpsClientConfiguration::class, $configuration);

    $this->postJson(route('lens-for-laravel.preview'), [
        'url' => 'http://localhost',
        'selector' => 'img.logo',
    ])->assertStatus(500)
        ->assertJson(['status' => 'error']);
});

test('POST /preview uses the shared HTTPS browser configuration', function () {
    $fakeBrowser = new FakeBrowsershotForPreviewHttpsTest;
    $configuration = Mockery::mock(HttpsClientConfiguration::class);
    $configuration->shouldReceive('configureBrowser')
        ->once()
        ->with(Mockery::type(Browsershot::class))
        ->andReturn($fakeBrowser);
    app()->instance(HttpsClientConfiguration::class, $configuration);

    $this->postJson(route('lens-for-laravel.preview'), [
        'url' => 'http://localhost',
        'selector' => 'img.logo',
    ])->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertContent('preview-png');
});
