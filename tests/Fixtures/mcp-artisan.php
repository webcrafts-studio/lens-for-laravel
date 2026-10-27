<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Collection;
use Laravel\Mcp\Server\McpServiceProvider;
use LensForLaravel\LensForLaravel\DTOs\AuthenticatedScanContext;
use LensForLaravel\LensForLaravel\DTOs\Issue;
use LensForLaravel\LensForLaravel\LensForLaravelServiceProvider;
use LensForLaravel\LensForLaravel\Services\AxeScanner;
use LensForLaravel\LensForLaravel\Services\FileLocator;
use Orchestra\Testbench\Foundation\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

// An isolated consumer application for real stdio and worker-process tests.
$loader = require dirname(__DIR__, 2).'/vendor/autoload.php';
if ($sdk = getenv('LENS_MCP_TEST_SDK_PATH')) {
    $loader->addPsr4(implode(chr(92), ['Laravel', 'Mcp', 'Server', '']), $sdk.'/src/Server', true);
    $loader->addPsr4(implode(chr(92), ['Laravel', 'Mcp', '']), $sdk.'/src', true);
}

$root = getenv('LENS_MCP_TEST_ROOT');
$app = Application::create(null, null, [
    'extra' => ['providers' => [
        LensForLaravelServiceProvider::class,
        McpServiceProvider::class,
    ]],
]);
$app['config']->set([
    'app.key' => 'base64:'.base64_encode(str_repeat('m', 32)), 'app.url' => 'http://localhost',
    'lens-for-laravel.enabled_environments' => ['testing'], 'lens-for-laravel.mcp.enabled' => true,
    'lens-for-laravel.mcp.storage_path' => $root.'/runs', 'lens-for-laravel.ai_enabled' => false,
]);
$app->detectEnvironment(fn () => 'testing');
$app->setBasePath($root);
$app->instance(FileLocator::class, new class extends FileLocator
{
    public function locate(string $htmlSnippet, string $selector): ?array
    {
        return null;
    }
});
$app->instance(AxeScanner::class, new class extends AxeScanner
{
    public function scan(string $url, ?string $wcagVersion = null, ?AuthenticatedScanContext $auth = null): Collection
    {
        if (str_contains($url, 'slow')) {
            usleep(2000000);
        }

        return collect([new Issue('image-alt', 'serious', 'Images need alternatives', '', '<img>', 'img', ['wcag2a'], $url)]);
    }
});
$status = $app->make(Kernel::class)->handle(
    new ArgvInput,
    new ConsoleOutput,
);
exit($status);
