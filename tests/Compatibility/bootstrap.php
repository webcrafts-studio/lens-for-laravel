<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use LensForLaravel\LensForLaravel\LensForLaravelServiceProvider;

// Run from an isolated Composer consumer directory, never from the package root.
$consumerRoot = getcwd();
$marker = $consumerRoot.'/.lens-compatibility-consumer';
if (! is_file($consumerRoot.'/vendor/autoload.php') || realpath($consumerRoot) === realpath(dirname(__DIR__, 2))
    || ! is_file($marker) || trim(file_get_contents($marker)) !== hash('sha256', realpath($consumerRoot))) {
    throw new RuntimeException('An isolated Composer consumer directory is required.');
}
require $consumerRoot.'/vendor/autoload.php';

foreach (['app', 'bootstrap/cache', 'config', 'storage/framework/views', 'storage/framework/cache', 'storage/framework/sessions', 'storage/logs', 'resources/views', 'resources/js'] as $directory) {
    if (! is_dir($consumerRoot.'/'.$directory) && ! @mkdir($consumerRoot.'/'.$directory, 0700, true) && ! is_dir($consumerRoot.'/'.$directory)) {
        throw new RuntimeException('Cannot create fixture directory');
    }
}
$writeFixtureConfig = static function (string $name, string $content) use ($consumerRoot): void {
    $temporary = tempnam($consumerRoot.'/config', '.fixture-');
    file_put_contents($temporary, $content);
    rename($temporary, $consumerRoot.'/config/'.$name.'.php');
};
putenv('APP_ENV=local');
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'local';
$legacyProviders = [
    'Illuminate\\Auth\\AuthServiceProvider', 'Illuminate\\Broadcasting\\BroadcastServiceProvider', 'Illuminate\\Bus\\BusServiceProvider',
    'Illuminate\\Cache\\CacheServiceProvider', 'Illuminate\\Foundation\\Providers\\ConsoleSupportServiceProvider',
    'Illuminate\\Cookie\\CookieServiceProvider', 'Illuminate\\Database\\DatabaseServiceProvider', 'Illuminate\\Encryption\\EncryptionServiceProvider',
    'Illuminate\\Filesystem\\FilesystemServiceProvider', 'Illuminate\\Foundation\\Providers\\FoundationServiceProvider', 'Illuminate\\Hashing\\HashServiceProvider',
    'Illuminate\\Mail\\MailServiceProvider', 'Illuminate\\Notifications\\NotificationServiceProvider', 'Illuminate\\Pagination\\PaginationServiceProvider',
    'Illuminate\\Pipeline\\PipelineServiceProvider', 'Illuminate\\Queue\\QueueServiceProvider', 'Illuminate\\Redis\\RedisServiceProvider',
    'Illuminate\\Auth\\Passwords\\PasswordResetServiceProvider', 'Illuminate\\Session\\SessionServiceProvider', 'Illuminate\\Translation\\TranslationServiceProvider',
    'Illuminate\\Validation\\ValidationServiceProvider', 'Illuminate\\View\\ViewServiceProvider',
];
$writeFixtureConfig('app', '<?php return ["name" => "Lens compatibility", "env" => "local", "debug" => true, "url" => "http://localhost", "timezone" => "UTC", "locale" => "en", "fallback_locale" => "en", "cipher" => "AES-256-CBC", "key" => "base64:'.base64_encode(str_repeat('t', 32)).'", "providers" => method_exists(Illuminate\\Support\\ServiceProvider::class, "defaultProviders") ? Illuminate\\Support\\ServiceProvider::defaultProviders()->toArray() : '.var_export($legacyProviders, true).'];');
foreach ([
    'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array', 'serialize' => false]]],
    'view' => ['paths' => [$consumerRoot.'/resources/views'], 'compiled' => $consumerRoot.'/storage/framework/views'],
    'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 'lens_fixture_session', 'path' => '/', 'domain' => null,
        'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'lottery' => [2, 100], 'expire_on_close' => false],
    'database' => ['default' => 'sqlite', 'migrations' => 'migrations', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']],
        'redis' => ['client' => 'phpredis', 'default' => ['host' => '127.0.0.1', 'port' => 6379, 'database' => 0]]],
    'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]],
    'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']],
        'providers' => ['users' => ['driver' => 'eloquent', 'model' => User::class]]],
    'logging' => ['default' => 'single', 'channels' => ['single' => ['driver' => 'single', 'path' => $consumerRoot.'/storage/logs/laravel.log']]],
] as $name => $values) {
    $writeFixtureConfig($name, '<?php return '.var_export($values, true).';');
}

$app = new Application($consumerRoot);
$app->singleton(Kernel::class, Illuminate\Foundation\Console\Kernel::class);
$app->singleton(Illuminate\Contracts\Http\Kernel::class, function ($app) {
    return new class($app, $app['router']) extends Illuminate\Foundation\Http\Kernel
    {
        protected $middlewareAliases = ['throttle' => ThrottleRequests::class];

        protected $middlewareGroups = [
            'web' => [EncryptCookies::class, AddQueuedCookiesToResponse::class,
                StartSession::class, ShareErrorsFromSession::class,
                SubstituteBindings::class],
        ];
    };
});
$app->singleton(ExceptionHandler::class, Handler::class);
$app->make(Kernel::class)->bootstrap();
if (! $app->getProvider(LensForLaravelServiceProvider::class)) {
    $app->register(LensForLaravelServiceProvider::class);
}
$app['router']->getRoutes()->refreshNameLookups();
$app->detectEnvironment(fn () => 'testing');
config([
    'app.key' => 'base64:'.base64_encode(str_repeat('t', 32)), 'app.url' => 'http://localhost',
    'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'single',
    'logging.channels.single' => ['driver' => 'single', 'path' => $consumerRoot.'/storage/logs/laravel.log'],
    'database.default' => 'sqlite', 'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
    'view.paths' => [$consumerRoot.'/resources/views'], 'view.compiled' => $consumerRoot.'/storage/framework/views',
    'lens-for-laravel.enabled_environments' => ['testing'], 'lens-for-laravel.middleware' => [],
    'lens-for-laravel.mcp.enabled' => true, 'lens-for-laravel.mcp.storage_path' => $consumerRoot.'/runs',
]);
app('db')->purge('sqlite');

// Production Laravel converts warnings too: undefined SDK properties must fail these probes.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (! (error_reporting() & $severity) || in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n".$exception->getTraceAsString()."\n");
    exit(1);
});

function lensCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $GLOBALS['lens_checks'] = ($GLOBALS['lens_checks'] ?? 0) + 1;
}

return $app;
