<?php

use Illuminate\Support\Facades\Log;
use LensForLaravel\LensForLaravel\DTOs\AiFixGeneration;
use LensForLaravel\LensForLaravel\Services\AiFixPromptRunner;

test('POST /fix/suggest requires all fields', function () {
    $this->postJson(route('lens-for-laravel.fix.suggest'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['htmlSnippet', 'description', 'fileName', 'lineNumber']);
});

test('POST /fix/suggest allows progressive fix all queues within the local rate limit', function () {
    $route = app('router')->getRoutes()->getByName('lens-for-laravel.fix.suggest');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('throttle:60,1');
});

test('POST /fix/suggest rejects htmlSnippet longer than 2000 characters', function () {
    $this->postJson(route('lens-for-laravel.fix.suggest'), [
        'htmlSnippet' => str_repeat('a', 2001),
        'description' => 'Test',
        'fileName' => 'test.blade.php',
        'lineNumber' => 1,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['htmlSnippet']);
});

test('POST /fix/suggest rejects lineNumber less than 1', function () {
    $this->postJson(route('lens-for-laravel.fix.suggest'), [
        'htmlSnippet' => '<img src="x.png">',
        'description' => 'Missing alt',
        'fileName' => 'test.blade.php',
        'lineNumber' => 0,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['lineNumber']);
});

test('POST /fix/suggest returns 403 when environment not allowed', function () {
    $this->app['config']->set('lens-for-laravel.enabled_environments', ['local']);

    $this->postJson(route('lens-for-laravel.fix.suggest'), [
        'htmlSnippet' => '<img src="x.png">',
        'description' => 'Missing alt',
        'fileName' => 'test.blade.php',
        'lineNumber' => 1,
    ])->assertStatus(403);
});

test('POST /fix/suggest returns 503 when AI Fix is disabled', function () {
    $this->app['config']->set('lens-for-laravel.ai_enabled', false);

    $this->postJson(route('lens-for-laravel.fix.suggest'), [
        'htmlSnippet' => '<img src="x.png">',
        'description' => 'Missing alt',
        'fileName' => 'test.blade.php',
        'lineNumber' => 1,
    ])->assertStatus(503)
        ->assertJson([
            'status' => 'error',
            'message' => 'AI Fix is disabled by configuration. Core accessibility scanning remains available.',
        ]);
});

test('POST /fix/suggest blocks path traversal in fileName', function () {
    // AiFixer::suggestFix throws RuntimeException before calling AI
    // when the path traversal is detected; the route catches it and
    // returns a generic error message (no internal details exposed).
    $this->postJson(route('lens-for-laravel.fix.suggest'), [
        'htmlSnippet' => '<img src="x.png">',
        'description' => 'Missing alt',
        'fileName' => '../../../etc/passwd',
        'lineNumber' => 1,
    ])->assertStatus(500)
        ->assertJson(['status' => 'error']);
});

test('POST /fix/suggest returns only a minimal semantic replacement', function () {
    // Create a real blade file so path validation passes
    $viewsPath = $this->app->resourcePath('views');
    if (! is_dir($viewsPath)) {
        mkdir($viewsPath, 0755, true);
    }
    $file = $viewsPath.'/suggest-test.blade.php';
    file_put_contents($file, <<<'BLADE'
<main>
    <p>Private unrelated content</p>
    <img src="logo.png">
    <p>More unrelated content</p>
</main>
BLADE);

    $runner = Mockery::mock(AiFixPromptRunner::class);
    $runner->shouldReceive('generate')
        ->once()
        ->withArgs(function (string $prompt, string $provider): bool {
            return $provider === 'gemini'
                && str_contains($prompt, '<img src="logo.png">')
                && ! str_contains($prompt, 'Private unrelated content')
                && ! str_contains($prompt, 'More unrelated content');
        })
        ->andReturn(new AiFixGeneration(
            replacement: '<img src="logo.png" alt="Company logo">',
            explanation: 'Adds alternative text.',
            provider: 'gemini',
            model: 'provider-default-model',
            finishReason: 'stop',
            usage: ['prompt_tokens' => 100, 'completion_tokens' => 30],
        ));
    app()->instance(AiFixPromptRunner::class, $runner);

    Log::spy();

    $response = $this->postJson(route('lens-for-laravel.fix.suggest'), [
        'htmlSnippet' => '<img src="logo.png">',
        'description' => 'Images must have alternate text',
        'fileName' => 'suggest-test.blade.php',
        'lineNumber' => 3,
    ])->assertOk()
        ->assertJson([
            'status' => 'success',
            'originalCode' => '<img src="logo.png">',
            'fixedCode' => '<img src="logo.png" alt="Company logo">',
            'startLine' => 3,
        ]);

    Log::shouldHaveReceived('log')->once()->withArgs(function (string $level, string $message, array $context): bool {
        return $level === 'info'
            && $message === 'Lens AI fix generation attempt'
            && $context['provider'] === 'gemini'
            && $context['model'] === 'provider-default-model'
            && $context['finish_reason'] === 'stop'
            && $context['usage']['prompt_tokens'] === 100;
    });

    unlink($file);
});

test('POST /fix/suggest retries a structured decoding failure exactly once', function () {
    $viewsPath = $this->app->resourcePath('views');
    if (! is_dir($viewsPath)) {
        mkdir($viewsPath, 0755, true);
    }
    $file = $viewsPath.'/retry-test.blade.php';
    file_put_contents($file, '<button>Save</button>');

    $attempt = 0;
    $runner = Mockery::mock(AiFixPromptRunner::class);
    $runner->shouldReceive('generate')
        ->twice()
        ->andReturnUsing(function () use (&$attempt): AiFixGeneration {
            $attempt++;
            if ($attempt === 1) {
                throw new RuntimeException('Structured object could not be decoded. Received: {"replacement":"<button>');
            }

            return new AiFixGeneration(
                replacement: '<button type="button">Save</button>',
                explanation: 'Sets an explicit button type.',
                provider: 'gemini',
                model: 'provider-default-model',
                finishReason: 'stop',
            );
        });
    app()->instance(AiFixPromptRunner::class, $runner);

    $this->postJson(route('lens-for-laravel.fix.suggest'), [
        'htmlSnippet' => '<button>Save</button>',
        'description' => 'Buttons must have discernible behavior',
        'fileName' => 'retry-test.blade.php',
        'lineNumber' => 1,
    ])->assertOk()
        ->assertJsonPath('fixedCode', '<button type="button">Save</button>');

    expect($attempt)->toBe(2);

    unlink($file);
});

test('POST /fix/suggest retries a length finish reason exactly once', function () {
    $viewsPath = $this->app->resourcePath('views');
    if (! is_dir($viewsPath)) {
        mkdir($viewsPath, 0755, true);
    }
    $file = $viewsPath.'/length-test.blade.php';
    file_put_contents($file, '<button>Save</button>');

    $runner = Mockery::mock(AiFixPromptRunner::class);
    $runner->shouldReceive('generate')
        ->once()
        ->andReturn(new AiFixGeneration('<button>Save', 'Incomplete.', 'gemini', 'provider-default-model', 'length'));
    $runner->shouldReceive('generate')
        ->once()
        ->andReturn(new AiFixGeneration('<button type="button">Save</button>', 'Complete.', 'gemini', 'provider-default-model', 'stop'));
    app()->instance(AiFixPromptRunner::class, $runner);

    $this->postJson(route('lens-for-laravel.fix.suggest'), [
        'htmlSnippet' => '<button>Save</button>',
        'description' => 'Button issue',
        'fileName' => 'length-test.blade.php',
        'lineNumber' => 1,
    ])->assertOk()
        ->assertJsonPath('fixedCode', '<button type="button">Save</button>');

    unlink($file);
});

test('POST /fix/suggest hides a truncated structured response after retry exhaustion', function () {
    $viewsPath = $this->app->resourcePath('views');
    if (! is_dir($viewsPath)) {
        mkdir($viewsPath, 0755, true);
    }
    $file = $viewsPath.'/truncated-test.blade.php';
    file_put_contents($file, '<img src="logo.png">');

    $runner = Mockery::mock(AiFixPromptRunner::class);
    $runner->shouldReceive('generate')
        ->twice()
        ->andThrow(new RuntimeException('Gemini hit token limit with invalid JSON: SECRET_RAW_PROVIDER_RESPONSE'));
    app()->instance(AiFixPromptRunner::class, $runner);

    $response = $this->postJson(route('lens-for-laravel.fix.suggest'), [
        'htmlSnippet' => '<img src="logo.png">',
        'description' => 'Images must have alternate text',
        'fileName' => 'truncated-test.blade.php',
        'lineNumber' => 1,
    ])->assertStatus(502)
        ->assertJson(['status' => 'error']);

    expect($response->json('message'))
        ->toBe('AI Fix could not generate a complete, valid suggestion after one retry. No file was changed. Please try again or fix this issue manually.')
        ->not->toContain('SECRET_RAW_PROVIDER_RESPONSE');

    unlink($file);
});

test('POST /fix/suggest does not retry authentication failures or expose provider details', function () {
    $viewsPath = $this->app->resourcePath('views');
    if (! is_dir($viewsPath)) {
        mkdir($viewsPath, 0755, true);
    }
    $file = $viewsPath.'/provider-failure-test.blade.php';
    file_put_contents($file, '<img src="logo.png">');

    $runner = Mockery::mock(AiFixPromptRunner::class);
    $runner->shouldReceive('generate')
        ->once()
        ->andThrow(new RuntimeException('Invalid API key: sk-secret-fragment'));
    app()->instance(AiFixPromptRunner::class, $runner);

    $response = $this->postJson(route('lens-for-laravel.fix.suggest'), [
        'htmlSnippet' => '<img src="logo.png">',
        'description' => 'Images must have alternate text',
        'fileName' => 'provider-failure-test.blade.php',
        'lineNumber' => 1,
    ])->assertStatus(502)
        ->assertJson(['status' => 'error']);

    expect($response->json('message'))
        ->toBe('AI Fix could not contact or use the configured AI provider. No file was changed. Check the provider credentials and try again.')
        ->not->toContain('sk-secret-fragment');

    unlink($file);
});
