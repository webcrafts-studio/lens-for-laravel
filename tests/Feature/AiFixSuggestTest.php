<?php

test('POST /fix/suggest requires all fields', function () {
    $this->postJson(route('lens-for-laravel.fix.suggest'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['htmlSnippet', 'description', 'fileName', 'lineNumber']);
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

test('POST /fix/suggest returns 500 with generic message when AI call fails', function () {
    // Create a real blade file so path validation passes
    $viewsPath = $this->app->resourcePath('views');
    if (! is_dir($viewsPath)) {
        mkdir($viewsPath, 0755, true);
    }
    $file = $viewsPath.'/suggest-test.blade.php';
    file_put_contents($file, '<img src="logo.png">');

    // Without a real API key, the agent() call will throw.
    // The response must NOT expose raw SDK error messages (which may contain key fragments).
    $response = $this->postJson(route('lens-for-laravel.fix.suggest'), [
        'htmlSnippet' => '<img src="logo.png">',
        'description' => 'Images must have alternate text',
        'fileName' => 'suggest-test.blade.php',
        'lineNumber' => 1,
    ])->assertStatus(500)
        ->assertJson(['status' => 'error']);

    // Message must be generic — no raw SDK error details.
    expect($response->json('message'))->toBe('The AI provider returned an error. Check your API key configuration and try again.');

    unlink($file);
});
