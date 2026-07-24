<?php

use Illuminate\Support\Facades\Route;
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
    $this->livewireFile = $this->viewsPath.'/livewire/lens-locator-navigation.blade.php';
    $this->reactFile = $this->jsPath.'/Components/LensLocatorTest.jsx';
    $this->vueFile = $this->jsPath.'/Components/LensLocatorTest.vue';

    Route::get('/lens-locator-home', fn () => 'Home')->name('lens-locator.home');
});

afterEach(function () {
    if (file_exists($this->bladeFile)) {
        unlink($this->bladeFile);
    }

    if (file_exists($this->reactFile)) {
        unlink($this->reactFile);
    }

    if (file_exists($this->vueFile)) {
        unlink($this->vueFile);
    }

    if (file_exists($this->livewireFile)) {
        unlink($this->livewireFile);
    }
});

test('locates element by id attribute', function () {
    file_put_contents($this->bladeFile, '<img id="main-logo" src="logo.png" alt="Logo">');

    $result = (new FileLocator)->locate('<img id="main-logo" src="logo.png">', '#main-logo');

    expect($result)->not->toBeNull()
        ->and($result['file'])->toEndWith('lens-locator-test.blade.php')
        ->and($result['line'])->toBe(1)
        ->and($result['type'])->toBe('blade');
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

test('uses nth-child selector position to locate the correct repeated blade element', function () {
    file_put_contents(
        $this->bladeFile,
        "<div class=\"grid\">\n".
        "    <div class=\"col-span-1\"><img src=\"{{ asset('img/clients/igum.png') }}\" class=\"hero-logo\" alt=\"Igum company logo\"></div>\n".
        "    <div class=\"col-span-1\"><img src=\"{{ asset('img/clients/igum.png') }}\" class=\"hero-logo\"></div>\n".
        "    <div class=\"col-span-1\"><img src=\"{{ asset('img/clients/k2.png') }}\" class=\"hero-logo\"></div>\n".
        '</div>'
    );

    $result = (new FileLocator)->locate(
        '<img src="https://example.test/img/clients/igum.png" class="hero-logo">',
        '.col-span-1:nth-child(2) > .hero-logo'
    );

    expect($result)->not->toBeNull()
        ->and($result['file'])->toEndWith('lens-locator-test.blade.php')
        ->and($result['line'])->toBe(3)
        ->and($result['type'])->toBe('blade');
});

test('prefers a rendered source filename over a shared blade class', function () {
    file_put_contents(
        $this->bladeFile,
        "<div>\n".
        "    <img src=\"{{ asset('img/clients/first.png') }}\" class=\"hero-logo\">\n".
        "    <img src=\"{{ asset('img/clients/second.png') }}\" class=\"hero-logo\">\n".
        '</div>'
    );

    $result = (new FileLocator)->locate(
        '<img src="https://example.test/img/clients/second.png" class="hero-logo">',
        '.client-grid > .hero-logo'
    );

    expect($result)->not->toBeNull()
        ->and($result['file'])->toEndWith('lens-locator-test.blade.php')
        ->and($result['line'])->toBe(3)
        ->and($result['type'])->toBe('blade');
});

test('locates dynamic blade links by route helper nested content and ancestor selector context', function () {
    file_put_contents(
        $this->bladeFile,
        "<div class=\"desktop-shell lg:px-4\">\n".
        "    <a href=\"{{ route('lens-locator.home') }}\">\n".
        "        <img src=\"{{ asset('img/logo-color.png') }}\" class=\"w-full max-w-46\" alt=\"\">\n".
        "    </a>\n".
        "</div>\n".
        "<div class=\"mobile-shell menu-container\">\n".
        "    <a href=\"{{ route('lens-locator.home') }}\">\n".
        "        <img src=\"{{ asset('img/logo-color.png') }}\" class=\"w-full max-w-46\" alt=\"\">\n".
        "    </a>\n".
        '</div>'
    );

    $html = '<a href="http://localhost/lens-locator-home">'.
        '<img src="http://localhost/img/logo-color.png" class="w-full max-w-46" alt="">'.
        '</a>';

    $desktop = (new FileLocator)->locate(
        $html,
        '.lg\:px-4 > a[href$="lens-locator-home"]'
    );
    $mobile = (new FileLocator)->locate(
        $html,
        '.menu-container > a[href$="lens-locator-home"]'
    );

    expect($desktop)->not->toBeNull()
        ->and($desktop['file'])->toEndWith('lens-locator-test.blade.php')
        ->and($desktop['line'])->toBe(2)
        ->and($desktop['type'])->toBe('blade')
        ->and($mobile)->not->toBeNull()
        ->and($mobile['file'])->toEndWith('lens-locator-test.blade.php')
        ->and($mobile['line'])->toBe(7)
        ->and($mobile['type'])->toBe('blade');
});

test('locates livewire blade links with dynamic routes and nested markup', function () {
    if (! is_dir(dirname($this->livewireFile))) {
        mkdir(dirname($this->livewireFile), 0755, true);
    }

    file_put_contents(
        $this->livewireFile,
        "<nav class=\"livewire-navigation\">\n".
        "    <a href=\"{{ route('lens-locator.home') }}\">\n".
        "        <img src=\"{{ asset('img/logo-color.png') }}\" class=\"brand-logo\" alt=\"\">\n".
        "    </a>\n".
        '</nav>'
    );

    $result = (new FileLocator)->locate(
        '<a href="http://localhost/lens-locator-home"><img src="http://localhost/img/logo-color.png" class="brand-logo" alt=""></a>',
        '.livewire-navigation > a[href$="lens-locator-home"]'
    );

    expect($result)->not->toBeNull()
        ->and($result['file'])->toBe('livewire/lens-locator-navigation.blade.php')
        ->and($result['line'])->toBe(2)
        ->and($result['type'])->toBe('blade');
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
        ->and($result['line'])->toBe(2)
        ->and($result['type'])->toBe('react');
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

test('locates nested react links with dynamic props and ancestor selector context', function () {
    file_put_contents(
        $this->bladeFile,
        "<a href=\"{{ route('lens-locator.home') }}\">Blade navigation</a>"
    );
    file_put_contents(
        $this->reactFile,
        "export function Navigation({ homeUrl }) {\n".
        "    return <>\n".
        "        <nav className=\"desktop-shell\">\n".
        "            <a href={homeUrl}>\n".
        "                <img src=\"/img/logo-color.png\" className=\"brand-logo\" alt=\"\" />\n".
        "            </a>\n".
        "        </nav>\n".
        "        <nav className=\"mobile-shell\">\n".
        "            <a href={homeUrl}>\n".
        "                <img src=\"/img/logo-color.png\" className=\"brand-logo\" alt=\"\" />\n".
        "            </a>\n".
        "        </nav>\n".
        "    </>;\n".
        "}\n"
    );

    $result = (new FileLocator)->locate(
        '<a href="http://localhost/lens-locator-home"><img src="http://localhost/img/logo-color.png" class="brand-logo" alt=""></a>',
        '.mobile-shell > a[href$="lens-locator-home"]'
    );

    expect($result)->not->toBeNull()
        ->and($result['file'])->toBe('js/Components/LensLocatorTest.jsx')
        ->and($result['line'])->toBe(9)
        ->and($result['type'])->toBe('react');
});

test('locates vue element by class from selector', function () {
    file_put_contents(
        $this->vueFile,
        "<template>\n".
        "    <img class=\"main-logo\" src=\"/logo.png\">\n".
        "</template>\n"
    );

    $result = (new FileLocator)->locate('<img class="main-logo" src="/logo.png">', '.main-logo');

    expect($result)->not->toBeNull()
        ->and($result['file'])->toBe('js/Components/LensLocatorTest.vue')
        ->and($result['line'])->toBe(2)
        ->and($result['type'])->toBe('vue');
});

test('uses nth-child selector position to locate the correct repeated frontend element', function () {
    file_put_contents(
        $this->vueFile,
        "<template>\n".
        "    <img class=\"client-logo\" :src=\"clients[0].logo\">\n".
        "    <img class=\"client-logo\" :src=\"clients[1].logo\">\n".
        "</template>\n"
    );

    $result = (new FileLocator)->locate(
        '<img class="client-logo" src="/clients/second.png">',
        '.clients:nth-child(2) > .client-logo'
    );

    expect($result)->not->toBeNull()
        ->and($result['file'])->toBe('js/Components/LensLocatorTest.vue')
        ->and($result['line'])->toBe(3)
        ->and($result['type'])->toBe('vue');
});

test('locates vue element by dynamic bound attribute', function () {
    file_put_contents(
        $this->vueFile,
        "<template>\n".
        "    <a :href=\"'/pricing'\">Pricing</a>\n".
        "</template>\n"
    );

    $result = (new FileLocator)->locate('<a href="/pricing">Pricing</a>', 'a');

    expect($result)->not->toBeNull()
        ->and($result['file'])->toBe('js/Components/LensLocatorTest.vue')
        ->and($result['line'])->toBe(2);
});

test('prefers vue attribute match over weak blade tag fallback', function () {
    file_put_contents($this->bladeFile, '<a href="/fallback">Fallback</a>');
    file_put_contents(
        $this->vueFile,
        "<template>\n".
        "    <a href=\"/pricing\">Pricing</a>\n".
        "</template>\n"
    );

    $result = (new FileLocator)->locate('<a href="/pricing">Pricing</a>', 'a');

    expect($result)->not->toBeNull()
        ->and($result['file'])->toBe('js/Components/LensLocatorTest.vue')
        ->and($result['line'])->toBe(2);
});

test('locates nested vue links with dynamic bindings and ancestor selector context', function () {
    file_put_contents(
        $this->bladeFile,
        "<a href=\"{{ route('lens-locator.home') }}\">Blade navigation</a>"
    );
    file_put_contents(
        $this->vueFile,
        "<template>\n".
        "    <nav class=\"desktop-shell\">\n".
        "        <a :href=\"homeUrl\">\n".
        "            <img src=\"/img/logo-color.png\" class=\"brand-logo\" alt=\"\">\n".
        "        </a>\n".
        "    </nav>\n".
        "    <nav class=\"mobile-shell\">\n".
        "        <a :href=\"homeUrl\">\n".
        "            <img src=\"/img/logo-color.png\" class=\"brand-logo\" alt=\"\">\n".
        "        </a>\n".
        "    </nav>\n".
        "</template>\n"
    );

    $result = (new FileLocator)->locate(
        '<a href="http://localhost/lens-locator-home"><img src="http://localhost/img/logo-color.png" class="brand-logo" alt=""></a>',
        '.mobile-shell > a[href$="lens-locator-home"]'
    );

    expect($result)->not->toBeNull()
        ->and($result['file'])->toBe('js/Components/LensLocatorTest.vue')
        ->and($result['line'])->toBe(8)
        ->and($result['type'])->toBe('vue');
});

test('locates inertia react pages under resources js pages', function () {
    $page = $this->jsPath.'/Pages/LensInertiaPage.tsx';
    if (! is_dir(dirname($page))) {
        mkdir(dirname($page), 0755, true);
    }

    file_put_contents(
        $page,
        "export default function PricingPage() {\n".
        "    return <button className={styles.primaryButton}>Buy</button>;\n".
        "}\n"
    );

    $result = (new FileLocator)->locate('<button class="primary-button">Buy</button>', '.primary-button');

    expect($result)->not->toBeNull()
        ->and($result['file'])->toBe('js/Pages/LensInertiaPage.tsx')
        ->and($result['line'])->toBe(2)
        ->and($result['type'])->toBe('react');

    unlink($page);
});

test('locates vue class object entries by selector part', function () {
    file_put_contents(
        $this->vueFile,
        "<template>\n".
        "    <button :class=\"{ active: isActive }\">Toggle</button>\n".
        "</template>\n"
    );

    $result = (new FileLocator)->locate('<button class="active">Toggle</button>', '.active');

    expect($result)->not->toBeNull()
        ->and($result['file'])->toBe('js/Components/LensLocatorTest.vue')
        ->and($result['line'])->toBe(2);
});

test('locates multiline vue anchor by escaped tailwind selector class', function () {
    file_put_contents(
        $this->vueFile,
        "<template>\n".
        "    <a\n".
        "        :href=\"social.url\"\n".
        "        target=\"_blank\"\n".
        "        class=\"group border border-slate-300 p-2 duration-200 hover:bg-slate-900\"\n".
        "    >\n".
        "        <GithubIcon />\n".
        "    </a>\n".
        "</template>\n"
    );

    $result = (new FileLocator)->locate(
        '<a class="group border border-slate-300 p-2 duration-200 hover:bg-slate-900" href="https://github.com/jakub-lipinski" target="_blank">',
        '.group.p-2.hover\:bg-slate-900:nth-child(1)'
    );

    expect($result)->not->toBeNull()
        ->and($result['file'])->toBe('js/Components/LensLocatorTest.vue')
        ->and($result['line'])->toBe(2)
        ->and($result['type'])->toBe('vue');
});

test('prefers target node classes over ancestor selector classes across vue files', function () {
    file_put_contents(
        $this->vueFile,
        "<template>\n".
        "    <nav class=\"sticky top-0 mx-auto grid max-w-360 grid-cols-12 bg-white\">\n".
        "        <span class=\"font-sans text-lg font-medium uppercase\">Portfolio</span>\n".
        "    </nav>\n".
        "</template>\n"
    );

    $home = $this->jsPath.'/Pages/Home.vue';
    if (! is_dir(dirname($home))) {
        mkdir(dirname($home), 0755, true);
    }

    file_put_contents(
        $home,
        "<template>\n".
        "    <div class=\"h-full border-b border-slate-300 p-8 tracking-wider md:p-12 lg:flex lg:flex-col\">\n".
        "        <span class=\"mb-2 inline-block font-mono text-slate-400 uppercase lg:mt-auto\">Core stack</span>\n".
        "    </div>\n".
        "</template>\n"
    );

    $result = (new FileLocator)->locate(
        '<span class="mb-2 inline-block font-mono text-slate-400 uppercase lg:mt-auto">Core stack</span>',
        '.h-full.lg\:flex.lg\:flex-col:nth-child(2) > .mb-2.lg\:mt-auto.inline-block'
    );

    expect($result)->not->toBeNull()
        ->and($result['file'])->toBe('js/Pages/Home.vue')
        ->and($result['line'])->toBe(3)
        ->and($result['type'])->toBe('vue');

    unlink($home);
});
