<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use LensForLaravel\LensForLaravel\Mcp\ClientConfigurationInstaller;
use LensForLaravel\LensForLaravel\Services\AiFixAvailability;
use LensForLaravel\LensForLaravel\Services\McpAvailability;
use LensForLaravel\LensForLaravel\Support\InstallationState;
use Symfony\Component\Console\Output\BufferedOutput;

function lensInstallAi(bool $sdk, int $php = 80300, string $laravel = '12.0.0', bool $supported = true): AiFixAvailability
{
    return new class($sdk, $php, $laravel, $supported) extends AiFixAvailability
    {
        public function __construct(private bool $sdk, private int $php, private string $laravel, private bool $supported) {}

        protected function sdkInstalled(): bool
        {
            return $this->sdk;
        }

        protected function sdkSupported(): bool
        {
            return $this->supported;
        }

        protected function phpVersionId(): int
        {
            return $this->php;
        }

        protected function laravelVersion(): string
        {
            return $this->laravel;
        }
    };
}

function lensInstallMcp(bool $sdk, string $laravel = '12.0.0'): McpAvailability
{
    return new class($sdk, $laravel) extends McpAvailability
    {
        public function __construct(private bool $sdk, private string $laravel) {}

        protected function sdkInstalled(): bool
        {
            return $this->sdk;
        }

        protected function laravelVersion(): string
        {
            return $this->laravel;
        }
    };
}

beforeEach(function () {
    $this->installDirectory = sys_get_temp_dir().'/lens-install-'.Str::uuid();
    $this->originalBasePath = base_path();
    $this->originalStoragePath = storage_path();
    File::ensureDirectoryExists($this->installDirectory.'/project');
    app()->setBasePath($this->installDirectory.'/project');
    app()->useStoragePath($this->installDirectory.'/custom-storage');
    app()->instance(AiFixAvailability::class, lensInstallAi(false));
    app()->instance(McpAvailability::class, lensInstallMcp(false));
    config(['lens-for-laravel.locale' => 'en', 'lens-for-laravel.mcp.enabled' => null]);
    URL::forceRootUrl('https://lens-demo.test');
    URL::forceScheme('https');
});

afterEach(function () {
    app()->setBasePath($this->originalBasePath);
    app()->useStoragePath($this->originalStoragePath);
    File::deleteDirectory($this->installDirectory);
});

test('core installation has a concise first-run welcome and passive star message without optional SDKs', function () {
    expect(Artisan::call('lens:install', ['--no-interaction' => true, '--no-ansi' => true]))->toBe(0);
    $output = Artisan::output();
    expect($output)->toContain('Lens for Laravel is installed.', 'https://lens-demo.test/lens-for-laravel/dashboard',
        'AI Fix', 'Lens MCP', 'Optional', 'PHP 8.3+', 'Laravel 12+', 'laravel/ai', 'Laravel 10.49+', 'laravel/mcp',
        'https://lens.webcrafts.pl/docs/ai-fix-engine', 'https://lens.webcrafts.pl/docs',
        'Thanks for using Lens for Laravel! Would you like to show some love and leave a star on GitHub?',
        'https://github.com/webcrafts-studio/lens-for-laravel')
        ->not->toContain('SDK installed', 'Client configured', "\e[", '[y/N]', 'Gemini', 'Claude', 'Codex');
    expect(file_get_contents(app(InstallationState::class)->path()))->toBe(hash('sha256', base_path()));
});

test('a repeated installation succeeds without repeating optional integrations or the star message', function () {
    Artisan::call('lens:install');
    expect(Artisan::call('lens:install', ['--no-interaction' => true]))->toBe(0);
    expect(Artisan::output())->toContain('already been installed', 'https://lens-demo.test/lens-for-laravel/dashboard')
        ->not->toContain('AI Fix', 'Lens MCP', 'GitHub', 'https://lens.webcrafts.pl/docs');
});

test('installed integrations use neutral statuses without exposing a model provider or client', function (string $client) {
    app()->instance(AiFixAvailability::class, lensInstallAi(true));
    app()->instance(McpAvailability::class, lensInstallMcp(true));
    config(['lens-for-laravel.ai_provider' => 'private-provider', 'lens-for-laravel.ai_model' => 'private-model']);
    app(ClientConfigurationInstaller::class)->install($client, base_path());

    expect(Artisan::call('lens:install'))->toBe(0);
    expect(Artisan::output())->toContain('SDK installed', 'Client configured')
        ->not->toContain('private-provider', 'private-model', 'Gemini', 'Claude', 'Codex', 'selected');
})->with(['claude', 'codex']);

test('an MCP SDK alone does not claim that a client is configured or enable MCP', function () {
    app()->instance(McpAvailability::class, lensInstallMcp(true));
    expect(Artisan::call('lens:install'))->toBe(0);
    expect(Artisan::output())->toContain('SDK installed', 'php artisan lens:mcp:install')
        ->not->toContain('Client configured');
    expect(app(McpAvailability::class)->enabled())->toBeFalse()
        ->and(is_file(base_path('.mcp.json')))->toBeFalse()
        ->and(is_file(base_path('.codex/config.toml')))->toBeFalse();
});

test('unsupported runtimes remain usable without trying to initialize optional integrations', function () {
    app()->instance(AiFixAvailability::class, lensInstallAi(false, 80200, '10.0.0'));
    app()->instance(McpAvailability::class, lensInstallMcp(false, '10.0.0'));
    expect(Artisan::call('lens:install'))->toBe(0);
    expect(Artisan::output())->toContain('Optional', 'PHP 8.3+', 'Laravel 10.49+')
        ->not->toContain('SDK installed', 'Client configured');
});

test('explicitly disabled integrations explain their state without claiming readiness', function () {
    app()->instance(AiFixAvailability::class, lensInstallAi(true));
    app()->instance(McpAvailability::class, lensInstallMcp(true));
    app(ClientConfigurationInstaller::class)->install('claude', base_path());
    config(['lens-for-laravel.ai_enabled' => false, 'lens-for-laravel.mcp.enabled' => false]);

    expect(Artisan::call('lens:install'))->toBe(0);
    expect(Artisan::output())->toContain('AI Fix is disabled', 'Lens MCP is disabled')->not->toContain('Client configured');
    expect(config('lens-for-laravel.ai_enabled'))->toBeFalse()
        ->and(config('lens-for-laravel.mcp.enabled'))->toBeFalse();
});

test('unsupported AI SDK dependencies and disallowed MCP environments are explained', function () {
    app()->instance(AiFixAvailability::class, lensInstallAi(true, supported: false));
    app()->instance(McpAvailability::class, lensInstallMcp(true));
    config(['lens-for-laravel.mcp.enabled' => true, 'lens-for-laravel.enabled_environments' => ['local']]);

    expect(Artisan::call('lens:install'))->toBe(0);
    expect(Artisan::output())->toContain('SDK or its dependencies are unsupported', 'not allowed in this environment')
        ->not->toContain('Client configured');
});

test('installation only writes its state and preserves existing application configuration', function () {
    File::ensureDirectoryExists(base_path('config'));
    $files = ['.env' => 'APP_KEY=private', 'composer.json' => '{"name":"example/application"}',
        'package.json' => '{"private":true}', 'config/lens-for-laravel.php' => '<?php return ["ai_enabled" => false];',
        '.mcp.json' => '{"mcpServers":{"other":{"command":"keep"}}}'];
    foreach ($files as $path => $content) {
        file_put_contents(base_path($path), $content);
    }
    $beforeTables = app('db')->connection()->getSchemaBuilder()->getTableListing();
    expect(Artisan::call('lens:install'))->toBe(0);
    foreach ($files as $path => $content) {
        expect(file_get_contents(base_path($path)))->toBe($content);
    }
    expect(app('db')->connection()->getSchemaBuilder()->getTableListing())->toBe($beforeTables)
        ->and(is_file(base_path('.codex/config.toml')))->toBeFalse();
});

test('normal application commands never show the welcome or create installation state', function () {
    Artisan::call('list', ['--raw' => true]);
    expect(Artisan::output())->not->toContain('Thanks for using', 'Would you like', 'SDK installed')
        ->and(is_file(app(InstallationState::class)->path()))->toBeFalse();
});

test('installation reports an unwritable state directory safely and can be retried', function () {
    File::ensureDirectoryExists(storage_path('app'));
    file_put_contents(storage_path('app/lens-for-laravel'), 'keep');
    expect(Artisan::call('lens:install'))->toBe(1);
    expect(Artisan::output())->toContain('Could not save the installation status')->not->toContain($this->installDirectory, 'Thanks for using');
    expect(file_get_contents(storage_path('app/lens-for-laravel')))->toBe('keep');
    unlink(storage_path('app/lens-for-laravel'));
    expect(Artisan::call('lens:install'))->toBe(0);
    expect(Artisan::output())->toContain('Thanks for using Lens for Laravel!');
});

test('the configured package locale is used and the application locale is restored', function (string $locale) {
    app()->setLocale('en');
    config(['lens-for-laravel.locale' => $locale]);
    $catalog = require __DIR__.'/../../resources/lang/'.$locale.'/messages.php';

    expect(Artisan::call('lens:install'))->toBe(0);
    expect(Artisan::output())->toContain($catalog['install']['installed'], $catalog['install']['star'])
        ->not->toContain('lens-for-laravel::messages.');
    expect(app()->getLocale())->toBe('en');
})->with(['en', 'pl', 'de', 'es', 'fr']);

test('the dashboard address includes an application subdirectory', function () {
    URL::forceRootUrl('https://example.test/my-project');

    expect(Artisan::call('lens:install'))->toBe(0);
    expect(Artisan::output())->toContain('https://example.test/my-project/lens-for-laravel/dashboard');
});

test('quiet installation produces no output and never requests input', function () {
    expect(Artisan::call('lens:install', ['--quiet' => true, '--no-interaction' => true]))->toBe(0);
    expect(Artisan::output())->toBe('');
});

test('ANSI-capable terminals receive styled output and plain output stays usable', function () {
    $buffer = new BufferedOutput(decorated: true);
    expect(Artisan::call('lens:install', ['--ansi' => true, '--no-interaction' => true], $buffer))->toBe(0);
    $output = $buffer->fetch();
    expect($output)->toContain("\e[", 'Thanks for using Lens for Laravel!', 'https://github.com/webcrafts-studio/lens-for-laravel');
});

test('an unsupported package locale uses the fallback and restores the application locale on failure', function () {
    app()->setLocale('pl');
    config(['lens-for-laravel.locale' => 'unknown', 'lens-for-laravel.fallback_locale' => 'en']);
    File::ensureDirectoryExists(storage_path('app'));
    file_put_contents(storage_path('app/lens-for-laravel'), 'keep');

    expect(Artisan::call('lens:install'))->toBe(1);
    expect(Artisan::output())->toContain('Could not save the installation status');
    expect(app()->getLocale())->toBe('pl');
});
