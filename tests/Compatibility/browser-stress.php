<?php

use LensForLaravel\LensForLaravel\Mcp\RunService;
use LensForLaravel\LensForLaravel\Mcp\RunStore;
use LensForLaravel\LensForLaravel\Services\AxeScanner;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\Process\Process;

$app = require __DIR__.'/bootstrap.php';
if (! getenv('LENS_MCP_BROWSER_MODULES') || ! getenv('LENS_MCP_CHROME_PATH')) {
    throw new RuntimeException('Real Chromium and local axe-core paths are required');
}
$source = resource_path('views/browser-stress.blade.php');
file_put_contents($source, '<!doctype html><html lang="en"><head><title>Stress</title><script src="/axe.js"></script></head><body><main><h1>Stress</h1><img id="logo" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=="><button id="open" onclick="document.querySelector(\'#dialog\').hidden=false">Open</button><div id="dialog" hidden><button id="empty"></button></div></main></body></html>');
copy($source, base_path('page.html'));
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (! $socket) {
    throw new RuntimeException($error);
}
$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, dirname(__DIR__).'/Fixtures/mcp-browser-router.php'], getcwd(), ['LENS_MCP_BROWSER_ROOT' => getcwd()], timeout: 120);
$server->disableOutput()->start();
$url = 'http://127.0.0.1:'.$port;
config(['app.url' => $url, 'lens-for-laravel.mcp.browser_timeout' => 15]);
app()->instance(AxeScanner::class, new class extends AxeScanner
{
    protected function browsershotForUrl(string $url): Browsershot
    {
        return Browsershot::url($url)->setNodeModulePath(getenv('LENS_MCP_BROWSER_MODULES'))->setChromePath(getenv('LENS_MCP_CHROME_PATH'));
    }
});
$start = microtime(true);
try {
    for ($i = 0; $i < 100; $i++) {
        if ($connection = @fsockopen('127.0.0.1', $port)) {
            fclose($connection);
            break;
        }
        usleep(10000);
    }
    $urls = array_map(fn ($i) => '/?page='.$i, range(1, 12));
    $run = app(RunService::class)->start(['mode' => 'multiple', 'urls' => [...$urls, '/redirect'], 'async' => false, 'save_history' => false, 'level' => 'aa', 'wcag' => '2.2']);
    $stored = app(RunStore::class)->read($run['run_id']);
    lensCheck($run['status'] === 'partial', 'Redirect failure was hidden in a large scan');
    lensCheck(count($stored['urls_scanned']) === 12 && count($stored['failed_urls']) === 1, 'Large scan lost pages');
    lensCheck(count(array_filter($stored['issues'], fn ($issue) => $issue['id'] === 'image-alt')) === 12, 'Large scan lost measured issues');
    lensCheck(count(array_unique(array_column($stored['issues'], 'issue_ref'))) === count($stored['issues']), 'Repeated rules share issue references');
    for ($i = 0; $i < 3; $i++) {
        $state = app(RunService::class)->start(['url' => '/?state='.$i, 'mode' => 'states', 'script' => "state: Initial\nstate: Dialog\nclick: #open", 'async' => false, 'save_history' => false]);
        $issues = app(RunStore::class)->read($state['run_id'])['issues'];
        lensCheck($state['status'] === 'completed', 'Repeated interactive scan failed');
        lensCheck(count(array_filter($issues, fn ($issue) => $issue['id'] === 'button-name' && $issue['stateLabel'] === 'Dialog')) === 1, 'Interactive states leaked across scans');
    }
    echo json_encode(['checks' => $GLOBALS['lens_checks'], 'pages' => 13, 'interactive_runs' => 3, 'seconds' => round(microtime(true) - $start, 3), 'peak_memory_bytes' => memory_get_peak_usage(true)], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $server->stop(0.1);
}
