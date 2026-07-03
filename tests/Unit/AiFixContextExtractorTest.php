<?php

use LensForLaravel\LensForLaravel\Services\AiFixContextExtractor;

test('extracts the exact semantic element instead of an arbitrary line window', function () {
    $source = <<<'BLADE'
<main>
    <p>Unrelated before</p>
    <button class="primary">
        <span>Save</span>
    </button>
    <p>Unrelated after</p>
</main>
BLADE;

    $context = (new AiFixContextExtractor)->extract($source, 3, '<button class="primary"><span>Save</span></button>');

    expect($context->code)->toBe(<<<'BLADE'
<button class="primary">
        <span>Save</span>
    </button>
BLADE)
        ->and($context->startLine)->toBe(3)
        ->and($context->scope)->toBe('element or component')
        ->and($context->code)->not->toContain('Unrelated');
});

test('balances nested elements with the same tag name', function () {
    $source = <<<'BLADE'
<div class="card">
    <div class="body">Content</div>
</div>
<div>Outside</div>
BLADE;

    $context = (new AiFixContextExtractor)->extract($source, 1, '<div class="card">');

    expect($context->code)->toBe(<<<'BLADE'
<div class="card">
    <div class="body">Content</div>
</div>
BLADE)
        ->and($context->code)->not->toContain('Outside');
});

test('extracts only a multiline void element', function () {
    $source = <<<'BLADE'
<img
    src="logo.png"
    class="logo"
>
<p>Unrelated</p>
BLADE;

    $context = (new AiFixContextExtractor)->extract($source, 1, '<img src="logo.png">');

    expect($context->code)->toBe(<<<'BLADE'
<img
    src="logo.png"
    class="logo"
>
BLADE)
        ->and($context->scope)->toBe('element');
});

test('uses only the opening tag when a component is too large to send safely', function () {
    $source = '<section class="large">'.str_repeat('<p>Long content</p>', 400).'</section>';

    $context = (new AiFixContextExtractor)->extract($source, 1, '<section class="large">');

    expect($context->code)->toBe('<section class="large">')
        ->and($context->scope)->toBe('opening element')
        ->and(strlen($context->code))->toBeLessThanOrEqual(6000);
});

test('rejects stale source line numbers', function () {
    (new AiFixContextExtractor)->extract('<button>Save</button>', 2, '<button>Save</button>');
})->throws(RuntimeException::class, 'The located source line does not exist');
