<?php

use LensForLaravel\LensForLaravel\DTOs\AiFixGeneration;
use LensForLaravel\LensForLaravel\Services\AiFixPromptRunner;

test('POST /fix/suggest passes the configured cloud provider to the runner', function (string $provider) {
    $viewsPath = $this->app->resourcePath('views');
    if (! is_dir($viewsPath)) {
        mkdir($viewsPath, 0755, true);
    }
    $file = $viewsPath.'/provider-passthrough-test.blade.php';
    file_put_contents($file, '<img src="logo.png">');

    $this->app['config']->set('lens-for-laravel.ai_provider', $provider);

    $runner = Mockery::mock(AiFixPromptRunner::class);
    $runner->shouldReceive('generate')
        ->once()
        ->withArgs(function (string $prompt, string $runnerProvider, ?string $model, ?int $timeout) use ($provider): bool {
            return $runnerProvider === $provider
                && $model === null
                && $timeout === null
                && str_contains($prompt, '<img src="logo.png">');
        })
        ->andReturn(new AiFixGeneration(
            replacement: '<img src="logo.png" alt="Company logo">',
            explanation: 'Adds alternative text.',
            provider: $provider,
            model: 'provider-default-model',
            finishReason: 'stop',
        ));
    app()->instance(AiFixPromptRunner::class, $runner);

    $this->postJson(route('lens-for-laravel.fix.suggest'), [
        'htmlSnippet' => '<img src="logo.png">',
        'description' => 'Images must have alternate text',
        'fileName' => 'provider-passthrough-test.blade.php',
        'lineNumber' => 1,
    ])->assertOk()
        ->assertJsonPath('fixedCode', '<img src="logo.png" alt="Company logo">');

    unlink($file);
})->with([
    'openrouter' => 'openrouter',
    'xai' => 'xai',
    'deepseek' => 'deepseek',
    'mistral' => 'mistral',
]);

test('POST /fix/suggest falls back to gemini for an unknown provider', function () {
    $viewsPath = $this->app->resourcePath('views');
    if (! is_dir($viewsPath)) {
        mkdir($viewsPath, 0755, true);
    }
    $file = $viewsPath.'/provider-fallback-test.blade.php';
    file_put_contents($file, '<img src="logo.png">');

    $this->app['config']->set('lens-for-laravel.ai_provider', 'bogus-provider');

    $runner = Mockery::mock(AiFixPromptRunner::class);
    $runner->shouldReceive('generate')
        ->once()
        ->withArgs(fn (string $prompt, string $runnerProvider): bool => $runnerProvider === 'gemini')
        ->andReturn(new AiFixGeneration(
            replacement: '<img src="logo.png" alt="Company logo">',
            explanation: 'Adds alternative text.',
            provider: 'gemini',
            model: 'provider-default-model',
            finishReason: 'stop',
        ));
    app()->instance(AiFixPromptRunner::class, $runner);

    $this->postJson(route('lens-for-laravel.fix.suggest'), [
        'htmlSnippet' => '<img src="logo.png">',
        'description' => 'Images must have alternate text',
        'fileName' => 'provider-fallback-test.blade.php',
        'lineNumber' => 1,
    ])->assertOk()
        ->assertJsonPath('fixedCode', '<img src="logo.png" alt="Company logo">');

    unlink($file);
});

test('POST /fix/suggest passes an explicit ai_model to the runner for cloud providers', function () {
    $viewsPath = $this->app->resourcePath('views');
    if (! is_dir($viewsPath)) {
        mkdir($viewsPath, 0755, true);
    }
    $file = $viewsPath.'/explicit-model-test.blade.php';
    file_put_contents($file, '<img src="logo.png">');

    $this->app['config']->set('lens-for-laravel.ai_provider', 'openai');
    $this->app['config']->set('lens-for-laravel.ai_model', 'gpt-5.6-luna');

    $runner = Mockery::mock(AiFixPromptRunner::class);
    $runner->shouldReceive('generate')
        ->once()
        ->withArgs(function (string $prompt, string $runnerProvider, ?string $model, ?int $timeout): bool {
            return $runnerProvider === 'openai'
                && $model === 'gpt-5.6-luna'
                && $timeout === null
                && str_contains($prompt, '<img src="logo.png">');
        })
        ->andReturn(new AiFixGeneration(
            replacement: '<img src="logo.png" alt="Company logo">',
            explanation: 'Adds alternative text.',
            provider: 'openai',
            model: 'gpt-5.6-luna',
            finishReason: 'stop',
        ));
    app()->instance(AiFixPromptRunner::class, $runner);

    $this->postJson(route('lens-for-laravel.fix.suggest'), [
        'htmlSnippet' => '<img src="logo.png">',
        'description' => 'Images must have alternate text',
        'fileName' => 'explicit-model-test.blade.php',
        'lineNumber' => 1,
    ])->assertOk()
        ->assertJsonPath('fixedCode', '<img src="logo.png" alt="Company logo">');

    unlink($file);
});

test('ai_model takes precedence over ai_ollama_model for ollama', function () {
    $viewsPath = $this->app->resourcePath('views');
    if (! is_dir($viewsPath)) {
        mkdir($viewsPath, 0755, true);
    }
    $file = $viewsPath.'/model-precedence-test.blade.php';
    file_put_contents($file, '<img src="logo.png">');

    $this->app['config']->set('lens-for-laravel.ai_provider', 'ollama');
    $this->app['config']->set('lens-for-laravel.ai_model', 'qwen3:8b');
    $this->app['config']->set('lens-for-laravel.ai_ollama_model', 'qwen2.5-coder:7b');

    $runner = Mockery::mock(AiFixPromptRunner::class);
    $runner->shouldReceive('generate')
        ->once()
        ->withArgs(function (string $prompt, string $runnerProvider, ?string $model, ?int $timeout): bool {
            return $runnerProvider === 'ollama'
                && $model === 'qwen3:8b'
                && $timeout === 120
                && str_contains($prompt, '<img src="logo.png">');
        })
        ->andReturn(new AiFixGeneration(
            replacement: '<img src="logo.png" alt="Company logo">',
            explanation: 'Adds alternative text.',
            provider: 'ollama',
            model: 'qwen3:8b',
            finishReason: 'stop',
        ));
    app()->instance(AiFixPromptRunner::class, $runner);

    $this->postJson(route('lens-for-laravel.fix.suggest'), [
        'htmlSnippet' => '<img src="logo.png">',
        'description' => 'Images must have alternate text',
        'fileName' => 'model-precedence-test.blade.php',
        'lineNumber' => 1,
    ])->assertOk()
        ->assertJsonPath('fixedCode', '<img src="logo.png" alt="Company logo">');

    unlink($file);
});
