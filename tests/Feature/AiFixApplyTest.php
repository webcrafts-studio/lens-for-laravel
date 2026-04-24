<?php

beforeEach(function () {
    $this->viewsPath = $this->app->resourcePath('views');
    $this->jsPath = $this->app->resourcePath('js');

    if (! is_dir($this->viewsPath)) {
        mkdir($this->viewsPath, 0755, true);
    }

    if (! is_dir($this->jsPath.'/Components')) {
        mkdir($this->jsPath.'/Components', 0755, true);
    }

    $this->bladeFile = $this->viewsPath.'/lens-fix-test.blade.php';
    $this->reactFile = $this->jsPath.'/Components/LensFixTest.jsx';
});

afterEach(function () {
    if (file_exists($this->bladeFile)) {
        unlink($this->bladeFile);
    }

    if (file_exists($this->reactFile)) {
        unlink($this->reactFile);
    }
});

test('POST /fix/apply requires all fields', function () {
    $this->postJson(route('lens-for-laravel.fix.apply'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fileName', 'originalCode', 'fixedCode']);
});

test('POST /fix/apply returns 403 when environment not allowed', function () {
    $this->app['config']->set('lens-for-laravel.enabled_environments', ['local']);

    $this->postJson(route('lens-for-laravel.fix.apply'), [
        'fileName' => 'test.blade.php',
        'originalCode' => '<img src="x.png">',
        'fixedCode' => '<img src="x.png" alt="Fixed">',
    ])->assertStatus(403);
});

test('POST /fix/apply applies fix and replaces content in blade file', function () {
    $original = '<img src="logo.png">';
    $fixed = '<img src="logo.png" alt="Company logo">';

    file_put_contents($this->bladeFile, "<div>\n{$original}\n</div>");

    $this->postJson(route('lens-for-laravel.fix.apply'), [
        'fileName' => basename($this->bladeFile),
        'originalCode' => $original,
        'fixedCode' => $fixed,
    ])->assertStatus(200)
        ->assertJson(['status' => 'success']);

    expect(file_get_contents($this->bladeFile))->toContain($fixed)
        ->not->toContain($original);
});

test('POST /fix/apply applies fix and replaces content in react file', function () {
    $original = '<img className="logo" src="/logo.png" />';
    $fixed = '<img className="logo" src="/logo.png" alt="Company logo" />';

    file_put_contents($this->reactFile, "export default function Logo() {\n    return {$original};\n}\n");

    $this->postJson(route('lens-for-laravel.fix.apply'), [
        'fileName' => 'js/Components/LensFixTest.jsx',
        'originalCode' => $original,
        'fixedCode' => $fixed,
    ])->assertStatus(200)
        ->assertJson(['status' => 'success']);

    expect(file_get_contents($this->reactFile))->toContain($fixed)
        ->not->toContain($original);
});

test('POST /fix/apply returns 422 when original code not found in file', function () {
    file_put_contents($this->bladeFile, '<div>Different content here</div>');

    $this->postJson(route('lens-for-laravel.fix.apply'), [
        'fileName' => basename($this->bladeFile),
        'originalCode' => '<img src="nonexistent.png">',
        'fixedCode' => '<img src="nonexistent.png" alt="Fixed">',
    ])->assertStatus(422)
        ->assertJson(['status' => 'error']);
});

test('POST /fix/apply blocks path traversal attempts', function () {
    $this->postJson(route('lens-for-laravel.fix.apply'), [
        'fileName' => '../../../etc/passwd',
        'originalCode' => 'root',
        'fixedCode' => 'hacked',
    ])->assertStatus(422)
        ->assertJson(['status' => 'error', 'message' => 'Invalid file path.']);
});

test('POST /fix/apply blocks access to unsupported files', function () {
    $this->postJson(route('lens-for-laravel.fix.apply'), [
        'fileName' => '/etc/hosts',
        'originalCode' => 'localhost',
        'fixedCode' => 'hacked',
    ])->assertStatus(422)
        ->assertJson(['status' => 'error', 'message' => 'Invalid file path.']);
});

test('POST /fix/apply blocks fixedCode containing RCE functions', function () {
    file_put_contents($this->bladeFile, '<div><img src="x.png"></div>');

    foreach (['shell_exec(', 'system(', 'exec(', 'passthru(', 'proc_open(', 'popen(', 'eval('] as $pattern) {
        $this->postJson(route('lens-for-laravel.fix.apply'), [
            'fileName' => basename($this->bladeFile),
            'originalCode' => '<img src="x.png">',
            'fixedCode' => "<img src=\"x.png\" alt=\"x\"> <?php {$pattern}'id'); ?>",
        ])->assertStatus(422)
            ->assertJson(['status' => 'error']);
    }
});

test('POST /fix/apply blocks fixedCode that introduces new PHP open tags', function () {
    file_put_contents($this->bladeFile, '<div><img src="x.png"></div>');

    $this->postJson(route('lens-for-laravel.fix.apply'), [
        'fileName' => basename($this->bladeFile),
        'originalCode' => '<img src="x.png">',
        'fixedCode' => '<img src="x.png" alt="x"> <?php echo "backdoor"; ?>',
    ])->assertStatus(422)
        ->assertJson(['status' => 'error']);
});

test('POST /fix/apply allows fixedCode that preserves existing PHP tags', function () {
    $original = '<?php echo $title; ?><img src="x.png">';
    $fixed = '<?php echo $title; ?><img src="x.png" alt="Logo">';

    file_put_contents($this->bladeFile, $original);

    $this->postJson(route('lens-for-laravel.fix.apply'), [
        'fileName' => basename($this->bladeFile),
        'originalCode' => $original,
        'fixedCode' => $fixed,
    ])->assertStatus(200)
        ->assertJson(['status' => 'success']);
});
