<?php

use LensForLaravel\LensForLaravel\Services\FileLocator;

beforeEach(function () {
    $this->viewsPath = $this->app->resourcePath('views');
    $this->jsPath = $this->app->resourcePath('js');

    if (! is_dir($this->viewsPath)) {
        mkdir($this->viewsPath, 0755, true);
    }

    if (! is_dir($this->jsPath.'/Components')) {
        mkdir($this->jsPath.'/Components', 0755, true);
    }

    $this->bladeFile = $this->viewsPath.'/lens-locator-test.blade.php';
    $this->reactFile = $this->jsPath.'/Components/LensLocatorTest.jsx';
});

afterEach(function () {
    if (file_exists($this->bladeFile)) {
        unlink($this->bladeFile);
    }

    if (file_exists($this->reactFile)) {
        unlink($this->reactFile);
    }
});

test('locates element by id attribute', function () {
    file_put_contents($this->bladeFile, '<img id="main-logo" src="logo.png" alt="Logo">');

    $result = (new FileLocator)->locate('<img id="main-logo" src="logo.png">', '#main-logo');

    expect($result)->not->toBeNull()
        ->and($result['file'])->toEndWith('lens-locator-test.blade.php')
        ->and($result['line'])->toBe(1);
});

test('locates element by name attribute', function () {
    file_put_contents($this->bladeFile, '<input name="email" type="email">');

    $result = (new FileLocator)->locate('<input name="email" type="email">', 'input[name="email"]');

    expect($result)->not->toBeNull()
        ->and($result['file'])->toEndWith('lens-locator-test.blade.php');
});

test('locates element by css class from selector', function () {
    file_put_contents($this->bladeFile, '<button class="submit-btn primary">Submit</button>');

    $result = (new FileLocator)->locate('<button class="submit-btn">Submit</button>', '.submit-btn');

    expect($result)->not->toBeNull();
});

test('returns null when no match found in any file', function () {
    file_put_contents($this->bladeFile, '<p>No matching element here</p>');

    $result = (new FileLocator)->locate('<img id="ghost-element" src="x.png">', '#ghost-element');

    expect($result)->toBeNull();
});

test('returns null when html snippet is empty', function () {
    $result = (new FileLocator)->locate('', 'div');

    expect($result)->toBeNull();
});

test('returns correct line number in a multi-line file', function () {
    file_put_contents(
        $this->bladeFile,
        "<div>\n".
        "    <p>First</p>\n".
        "    <img id=\"hero-img\" src=\"hero.jpg\" alt=\"Hero\">\n".
        "    <p>Last</p>\n".
        '</div>'
    );

    $result = (new FileLocator)->locate('<img id="hero-img" src="hero.jpg">', '#hero-img');

    expect($result)->not->toBeNull()
        ->and($result['line'])->toBe(3);
});

test('locates react element by jsx className from selector', function () {
    file_put_contents(
        $this->reactFile,
        "export default function Header() {\n".
        "    return <img className=\"main-logo\" src=\"/logo.png\" />;\n".
        "}\n"
    );

    $result = (new FileLocator)->locate('<img class="main-logo" src="/logo.png">', '.main-logo');

    expect($result)->not->toBeNull()
        ->and($result['file'])->toBe('js/Components/LensLocatorTest.jsx')
        ->and($result['line'])->toBe(2);
});

test('locates react element by jsx expression attribute', function () {
    file_put_contents(
        $this->reactFile,
        "export function Hero() {\n".
        "    return <a id={'pricing-link'} href={'/pricing'}>Pricing</a>;\n".
        "}\n"
    );

    $result = (new FileLocator)->locate('<a id="pricing-link" href="/pricing">Pricing</a>', '#pricing-link');

    expect($result)->not->toBeNull()
        ->and($result['file'])->toBe('js/Components/LensLocatorTest.jsx')
        ->and($result['line'])->toBe(2);
});

test('prefers react attribute match over weak blade tag fallback', function () {
    file_put_contents($this->bladeFile, '<a href="/fallback">Fallback</a>');
    file_put_contents(
        $this->reactFile,
        "export function Nav() {\n".
        "    return <a href={'/pricing'}>Pricing</a>;\n".
        "}\n"
    );

    $result = (new FileLocator)->locate('<a href="/pricing">Pricing</a>', 'a');

    expect($result)->not->toBeNull()
        ->and($result['file'])->toBe('js/Components/LensLocatorTest.jsx')
        ->and($result['line'])->toBe(2);
});
