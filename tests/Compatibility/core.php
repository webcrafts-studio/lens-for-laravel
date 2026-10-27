<?php

use Composer\InstalledVersions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Contracts\Transport;
use LensForLaravel\LensForLaravel\DTOs\AuthenticatedScanContext;
use LensForLaravel\LensForLaravel\DTOs\Issue;
use LensForLaravel\LensForLaravel\Mcp\LensServer;
use LensForLaravel\LensForLaravel\Mcp\RunService;
use LensForLaravel\LensForLaravel\Mcp\RunStore;
use LensForLaravel\LensForLaravel\Services\AiFixAvailability;
use LensForLaravel\LensForLaravel\Services\AxeScanner;
use LensForLaravel\LensForLaravel\Services\FileLocator;
use LensForLaravel\LensForLaravel\Services\McpAvailability;

$app = require __DIR__.'/bootstrap.php';
$commands = app(Kernel::class)->all();
lensCheck(isset($commands['lens:audit'], $commands['lens:mcp'], $commands['lens:mcp:scan']), 'Lens commands missing');
lensCheck(Route::has('lens-for-laravel.dashboard') && Route::has('lens-for-laravel.fix.suggest'), 'Lens routes missing');
lensCheck(Artisan::call('migrate', ['--force' => true]) === 0, 'Consumer migrations failed');

app()->instance(AxeScanner::class, new class extends AxeScanner
{
    public function scan(string $url, ?string $wcagVersion = null, ?AuthenticatedScanContext $auth = null): Collection
    {
        return collect([new Issue('image-alt', 'serious', 'Image needs alt', '', '<img>', 'img', ['wcag2a'], $url)]);
    }
});
app()->instance(FileLocator::class, new class extends FileLocator
{
    public function locate(string $htmlSnippet, string $selector): ?array
    {
        return null;
    }
});
$run = app(RunService::class)->start(['url' => '/', 'async' => false, 'save_history' => true]);
lensCheck($run['status'] === 'completed' && $run['issue_count'] === 1, 'Core scan/history failed');
lensCheck(count(app(RunStore::class)->read($run['run_id'])['issues']) === 1, 'Stored scan lost issues');
$verified = app(RunService::class)->verify($run['run_id'], false);
lensCheck($verified['status'] === 'completed' && $verified['comparison']['complete'], 'Verification failed');
if (! InstalledVersions::isInstalled('laravel/ai')) {
    lensCheck(! app(AiFixAvailability::class)->available(), 'Missing AI SDK was treated as available');
}
if (InstalledVersions::isInstalled('laravel/mcp')) {
    lensCheck(app(McpAvailability::class)->status()['available'], 'MCP unavailable on a supported framework');
    $transport = new class implements Transport
    {
        public array $messages = [];

        public function onReceive(Closure $handler): void {}

        public function run(): void {}

        public function send(string $message, ?string $sessionId = null): void
        {
            $this->messages[] = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
        }

        public function sessionId(): string
        {
            return 'compatibility';
        }

        public function stream(Closure $stream): void
        {
            $stream();
        }
    };
    $server = new LensServer($transport);
    $server->start();
    foreach ([['tools/list', []], ['tools/call', ['name' => 'lens_status', 'arguments' => (object) []]],
        ['tools/call', ['name' => 'lens_scan', 'arguments' => ['url' => '/', 'async' => false, 'save_history' => false]]]] as $index => [$method, $params]) {
        $server->handle(json_encode(['jsonrpc' => '2.0', 'id' => $index + 1, 'method' => $method, 'params' => $params]));
    }
    lensCheck(count($transport->messages[0]['result']['tools'] ?? []) === 13, 'MCP tool catalog incompatible');
    lensCheck(($transport->messages[1]['result']['isError'] ?? true) === false, 'MCP status incompatible');
    lensCheck(($transport->messages[2]['result']['structuredContent']['status'] ?? '') === 'completed', 'MCP scan incompatible');
}

$http = app(Illuminate\Contracts\Http\Kernel::class);
$response = $http->handle(Request::create('/lens-for-laravel/dashboard'));
lensCheck($response->getStatusCode() === 200, 'Dashboard render failed: '.substr($response->getContent(), 0, 500));
lensCheck(str_contains($response->getContent(), 'Lens'), 'Dashboard response missing');
echo json_encode(['php' => PHP_VERSION, 'laravel' => app()->version(), 'mcp' => InstalledVersions::isInstalled('laravel/mcp') ? InstalledVersions::getPrettyVersion('laravel/mcp') : null,
    'checks' => $GLOBALS['lens_checks']], JSON_THROW_ON_ERROR).PHP_EOL;
