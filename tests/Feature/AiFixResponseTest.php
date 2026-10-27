<?php

use Illuminate\Support\Facades\File;
use LensForLaravel\LensForLaravel\DTOs\AiFixGeneration;
use LensForLaravel\LensForLaravel\Exceptions\AiFixResponseException;
use LensForLaravel\LensForLaravel\Services\AiFixPromptRunner;

beforeEach(function () {
    $this->responseFile = resource_path('views/typed-response.blade.php');
    File::ensureDirectoryExists(dirname($this->responseFile));
    file_put_contents($this->responseFile, '<img src="logo.png">');
    $this->responseRequest = ['htmlSnippet' => '<img src="logo.png">', 'description' => 'Images need alternatives', 'fileName' => 'typed-response.blade.php', 'lineNumber' => 1];
});

afterEach(function () {
    @unlink($this->responseFile);
});

test('invalid structured responses retry once independently of the interface language', function (string $locale) {
    app()->setLocale($locale);
    $runner = Mockery::mock(AiFixPromptRunner::class);
    $runner->shouldReceive('generate')->once()->andThrow(new AiFixResponseException('openai', 'sdk-default', 'stop', ['input_tokens' => 20]));
    $runner->shouldReceive('generate')->once()->andReturn(new AiFixGeneration('<img src="logo.png" alt="Logo">', 'Adds alt.', 'openai', 'sdk-default', 'stop'));
    app()->instance(AiFixPromptRunner::class, $runner);

    $this->postJson(route('lens-for-laravel.fix.suggest'), $this->responseRequest)->assertOk()
        ->assertJsonPath('fixedCode', '<img src="logo.png" alt="Logo">');
    expect(file_get_contents($this->responseFile))->toBe('<img src="logo.png">');
})->with(['en', 'pl', 'es', 'fr', 'de']);

test('repeated invalid structured responses stop after exactly one retry', function () {
    $runner = Mockery::mock(AiFixPromptRunner::class);
    $runner->shouldReceive('generate')->twice()->andThrow(new AiFixResponseException('openai', 'sdk-default', 'length', ['input_tokens' => 20]));
    app()->instance(AiFixPromptRunner::class, $runner);

    $this->postJson(route('lens-for-laravel.fix.suggest'), $this->responseRequest)->assertStatus(502)
        ->assertJsonPath('message', __('lens-for-laravel::messages.ai_fix.incomplete_response'));
    expect(file_get_contents($this->responseFile))->toBe('<img src="logo.png">');
});

test('filtered cancelled and failed generations are never retried', function (string $reason) {
    $runner = Mockery::mock(AiFixPromptRunner::class);
    $runner->shouldReceive('generate')->once()->andThrow(new AiFixResponseException('openai', 'sdk-default', $reason, [], false));
    app()->instance(AiFixPromptRunner::class, $runner);

    $this->postJson(route('lens-for-laravel.fix.suggest'), $this->responseRequest)->assertStatus(502)
        ->assertJsonPath('message', __('lens-for-laravel::messages.ai_fix.generation_failed'));
    expect(file_get_contents($this->responseFile))->toBe('<img src="logo.png">');
})->with(['content_filter', 'cancelled', 'error']);
