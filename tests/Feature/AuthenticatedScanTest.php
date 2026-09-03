<?php

use Illuminate\Support\Facades\Http;
use LensForLaravel\LensForLaravel\DTOs\AuthenticatedScanContext;
use LensForLaravel\LensForLaravel\Services\AuthenticatedScanResolver;
use LensForLaravel\LensForLaravel\Services\AxeScanner;
use LensForLaravel\LensForLaravel\Services\FileLocator;
use LensForLaravel\LensForLaravel\Services\HttpsClientConfiguration;
use LensForLaravel\LensForLaravel\Services\SiteCrawler;
use Spatie\Browsershot\Browsershot;

class FakeBrowsershotForAuthCookiesTest extends Browsershot
{
    public ?array $cookies = null;

    public function noSandbox(): static
    {
        return $this;
    }

    public function waitUntilNetworkIdle(bool $strict = true): static
    {
        return $this;
    }

    public function setDelay(int $delayInMilliseconds): static
    {
        return $this;
    }

    public function setExtraHttpHeaders(array $extraHTTPHeaders): static
    {
        return $this;
    }

    public function useCookies(array $cookies, ?string $domain = null): static
    {
        $this->cookies = $cookies;

        return $this;
    }

    public function evaluate(string $pageFunction): string
    {
        return '[]';
    }
}

class FakeBrowsershotForPreviewAuthTest extends Browsershot
{
    public ?array $cookies = null;

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

    public function useCookies(array $cookies, ?string $domain = null): static
    {
        $this->cookies = $cookies;

        return $this;
    }

    public function screenshot(): string
    {
        return 'preview-png';
    }
}

function mockAuthResolverWithContext(AuthenticatedScanContext $context): void
{
    $resolverMock = Mockery::mock(AuthenticatedScanResolver::class);
    $resolverMock->shouldReceive('runAsUser')
        ->once()
        ->with(Mockery::type('int'), Mockery::type('callable'))
        ->andReturnUsing(fn ($userId, callable $callback) => $callback($context));
    app()->instance(AuthenticatedScanResolver::class, $resolverMock);
}

// ─── Route validation ─────────────────────────────────────────────────────────

test('POST /scan rejects authenticated scans when disabled', function () {
    $this->postJson(route('lens-for-laravel.scan'), [
        'url' => 'http://localhost',
        'asUserId' => 7,
    ])->assertStatus(422)
        ->assertJsonPath('status', 'error');
});

test('POST /scan validates the user id', function () {
    $this->postJson(route('lens-for-laravel.scan'), [
        'url' => 'http://localhost',
        'asUserId' => 0,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['asUserId']);

    $this->postJson(route('lens-for-laravel.scan'), [
        'url' => 'http://localhost',
        'asUserId' => 'not-an-id',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['asUserId']);
});

test('POST /scan maps resolver failures to a safe 422 response', function () {
    $resolverMock = Mockery::mock(AuthenticatedScanResolver::class);
    $resolverMock->shouldReceive('runAsUser')
        ->andThrow(new InvalidArgumentException('auth unavailable'));
    app()->instance(AuthenticatedScanResolver::class, $resolverMock);

    $this->postJson(route('lens-for-laravel.scan'), [
        'url' => 'http://localhost',
        'asUserId' => 7,
    ])->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'auth unavailable');
});

// ─── Context forwarding ───────────────────────────────────────────────────────

test('POST /scan forwards the authenticated context to the scanner', function () {
    mockAuthResolverWithContext(new AuthenticatedScanContext(['lens_session' => 'secret-id']));

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')
        ->once()
        ->with('http://localhost', null, Mockery::on(
            fn ($auth) => $auth instanceof AuthenticatedScanContext && $auth->cookies === ['lens_session' => 'secret-id']
        ))
        ->andReturn(collect());
    app()->instance(AxeScanner::class, $scannerMock);
    app()->instance(FileLocator::class, Mockery::mock(FileLocator::class));

    $this->postJson(route('lens-for-laravel.scan'), [
        'url' => 'http://localhost',
        'asUserId' => 9,
    ])->assertOk()
        ->assertJsonPath('status', 'success');
});

test('POST /scan/states forwards the authenticated context to the scanner', function () {
    mockAuthResolverWithContext(new AuthenticatedScanContext(['lens_session' => 'secret-id']));

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scanInteractiveStates')
        ->once()
        ->with('http://localhost', Mockery::type('array'), null, Mockery::on(
            fn ($auth) => $auth instanceof AuthenticatedScanContext && $auth->cookies === ['lens_session' => 'secret-id']
        ))
        ->andReturn(collect());
    app()->instance(AxeScanner::class, $scannerMock);
    app()->instance(FileLocator::class, Mockery::mock(FileLocator::class));

    $this->postJson(route('lens-for-laravel.scan.states'), [
        'url' => 'http://localhost',
        'script' => 'state: Initial',
        'asUserId' => 9,
    ])->assertOk()
        ->assertJsonPath('status', 'success');
});

test('POST /crawl forwards the authenticated context to the crawler', function () {
    mockAuthResolverWithContext(new AuthenticatedScanContext(['lens_session' => 'secret-id']));

    $crawlerMock = Mockery::mock(SiteCrawler::class);
    $crawlerMock->shouldReceive('crawl')
        ->once()
        ->with('http://localhost', 5, Mockery::on(
            fn ($auth) => $auth instanceof AuthenticatedScanContext && $auth->cookies === ['lens_session' => 'secret-id']
        ))
        ->andReturn(['http://localhost']);
    app()->instance(SiteCrawler::class, $crawlerMock);

    $this->postJson(route('lens-for-laravel.crawl'), [
        'url' => 'http://localhost',
        'asUserId' => 9,
    ])->assertOk()
        ->assertJsonPath('status', 'success');
});

test('POST /preview applies authenticated cookies', function () {
    $fakeBrowser = new FakeBrowsershotForPreviewAuthTest;
    $configuration = Mockery::mock(HttpsClientConfiguration::class);
    $configuration->shouldReceive('configureBrowser')
        ->once()
        ->with(Mockery::type(Browsershot::class))
        ->andReturn($fakeBrowser);
    app()->instance(HttpsClientConfiguration::class, $configuration);

    mockAuthResolverWithContext(new AuthenticatedScanContext(['lens_session' => 'abc']));

    $this->postJson(route('lens-for-laravel.preview'), [
        'url' => 'http://localhost',
        'selector' => 'img.logo',
        'asUserId' => 4,
    ])->assertOk();

    expect($fakeBrowser->cookies)->toBe(['lens_session' => 'abc']);
});

// ─── Scanner and crawler cookie handling ──────────────────────────────────────

test('axe scanner attaches authenticated cookies to the browser', function () {
    $fakeBrowsershot = new FakeBrowsershotForAuthCookiesTest;
    $scanner = new class($fakeBrowsershot) extends AxeScanner
    {
        public function __construct(private readonly Browsershot $fakeBrowsershot) {}

        protected function browsershotForUrl(string $url): Browsershot
        {
            return $this->fakeBrowsershot;
        }
    };

    $scanner->scan('https://example.com', '2.0', new AuthenticatedScanContext(['lens_session' => 'abc']));

    expect($fakeBrowsershot->cookies)->toBe(['lens_session' => 'abc']);
});

test('axe scanner sends no cookies without an authenticated context', function () {
    $fakeBrowsershot = new FakeBrowsershotForAuthCookiesTest;
    $scanner = new class($fakeBrowsershot) extends AxeScanner
    {
        public function __construct(private readonly Browsershot $fakeBrowsershot) {}

        protected function browsershotForUrl(string $url): Browsershot
        {
            return $this->fakeBrowsershot;
        }
    };

    $scanner->scan('https://example.com');

    expect($fakeBrowsershot->cookies)->toBeNull();
});

test('crawler forwards authenticated cookies to internal requests', function () {
    Http::fake([
        '*' => Http::response(
            '<html><body><a href="http://localhost/about">About</a></body></html>',
            200,
            ['Content-Type' => 'text/html']
        ),
    ]);

    $urls = (new SiteCrawler)->crawl(
        'http://localhost', 5, new AuthenticatedScanContext(['lens_session' => 'abc'])
    );

    expect($urls)->toContain('http://localhost/about');

    Http::assertSent(fn ($request) => str_contains((string) ($request->header('Cookie')[0] ?? ''), 'lens_session=abc'));
});

test('crawler sends no cookies without an authenticated context', function () {
    Http::fake([
        '*' => Http::response(
            '<html><body><a href="http://localhost/about">About</a></body></html>',
            200,
            ['Content-Type' => 'text/html']
        ),
    ]);

    (new SiteCrawler)->crawl('http://localhost', 5);

    Http::assertSent(fn ($request) => ($request->header('Cookie')[0] ?? null) === null);
});

// ─── CLI ──────────────────────────────────────────────────────────────────────

test('lens:audit rejects authenticated scans when disabled', function () {
    $this->artisan('lens:audit', [
        'url' => 'https://example.com',
        '--as-user' => '7',
    ])->assertExitCode(1);
});

test('lens:audit rejects a malformed --as-user option', function () {
    $this->artisan('lens:audit', [
        'url' => 'https://example.com',
        '--as-user' => 'abc',
    ])->assertExitCode(1)
        ->expectsOutputToContain('positive numeric');
});

test('lens:audit passes the authenticated context to the scanner', function () {
    $context = new AuthenticatedScanContext(['lens_session' => 'abc']);

    $resolverMock = Mockery::mock(AuthenticatedScanResolver::class);
    $resolverMock->shouldReceive('runAsUser')
        ->once()
        ->with(7, Mockery::type('callable'))
        ->andReturnUsing(fn ($userId, callable $callback) => $callback($context));
    app()->instance(AuthenticatedScanResolver::class, $resolverMock);

    $scannerMock = Mockery::mock(AxeScanner::class);
    $scannerMock->shouldReceive('scan')
        ->once()
        ->with('https://example.com', Mockery::any(), Mockery::on(fn ($auth) => $auth === $context))
        ->andReturn(collect());
    app()->instance(AxeScanner::class, $scannerMock);

    $locatorMock = Mockery::mock(FileLocator::class);
    $locatorMock->shouldReceive('locate')->andReturn(null);
    app()->instance(FileLocator::class, $locatorMock);

    $this->artisan('lens:audit', [
        'url' => 'https://example.com',
        '--as-user' => '7',
    ])->assertExitCode(0)
        ->expectsOutputToContain('As user');
});

// ─── Dashboard ────────────────────────────────────────────────────────────────

test('dashboard hides the authenticated scan controls when disabled', function () {
    $this->get(route('lens-for-laravel.dashboard'))
        ->assertOk()
        ->assertDontSee('Scan as user ID');
});

test('dashboard shows the authenticated scan controls when enabled', function () {
    config()->set('lens-for-laravel.auth_enabled', true);

    $this->get(route('lens-for-laravel.dashboard'))
        ->assertOk()
        ->assertSee('Authenticated scan')
        ->assertSee('Scan as user ID')
        ->assertSee('authUserId', false)
        ->assertSee('authPayload', false);
});
