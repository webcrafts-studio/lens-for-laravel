<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LensForLaravel\LensForLaravel\Mcp\RunService;
use LensForLaravel\LensForLaravel\Mcp\RunStore;
use LensForLaravel\LensForLaravel\Mcp\SourceContext;
use LensForLaravel\LensForLaravel\Mcp\ToolActions;
use LensForLaravel\LensForLaravel\Services\AxeScanner;
use LensForLaravel\LensForLaravel\Services\ReportExporter;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\Process\Process;

beforeEach(function () {
    if (! getenv('LENS_MCP_BROWSER_MODULES') || ! getenv('LENS_MCP_CHROME_PATH')) {
        $this->markTestSkipped('Set LENS_MCP_BROWSER_MODULES and LENS_MCP_CHROME_PATH for real Chromium integration tests.');
    }
    $this->browserDirectory = sys_get_temp_dir().'/lens-mcp-browser-'.Str::uuid();
    mkdir($this->browserDirectory, 0700, true);
    $this->browserFile = resource_path('views/browser-'.Str::uuid().'.blade.php');
    File::ensureDirectoryExists(dirname($this->browserFile));
    $this->browserHtml = '<!doctype html><html lang="en"><head><title>Lens test</title><script src="/axe.js"></script></head><body><main><h1>Test</h1><img id="logo" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=="><button id="open" onclick="document.querySelector(\'#dialog\').hidden=false">Open</button><div id="dialog" hidden><button id="empty"></button></div></main></body></html>';
    file_put_contents($this->browserFile, $this->browserHtml);
    file_put_contents($this->browserDirectory.'/page.html', $this->browserHtml);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (! $socket) {
        throw new RuntimeException($error);
    }
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $this->browserServer = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, dirname(__DIR__).'/Fixtures/mcp-browser-router.php'], null, ['LENS_MCP_BROWSER_ROOT' => $this->browserDirectory]);
    $this->browserServer->disableOutput()->start();
    $this->browserUrl = 'http://127.0.0.1:'.$port;
    $ready = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $connection = @fsockopen('127.0.0.1', $port);
        if ($connection) {
            fclose($connection);
            $ready = true;
            break;
        }
        usleep(10000);
    }
    if (! $ready) {
        throw new RuntimeException('Browser fixture did not start');
    }
    config(['app.url' => $this->browserUrl, 'lens-for-laravel.mcp.enabled' => true, 'lens-for-laravel.mcp.allow_writes' => true,
        'lens-for-laravel.mcp.storage_path' => $this->browserDirectory.'/runs', 'lens-for-laravel.mcp.browser_timeout' => 20]);
    app()->instance(ReportExporter::class, new class extends ReportExporter
    {
        protected function browser(string $html): Browsershot
        {
            return Browsershot::html($html)->setNodeModulePath(getenv('LENS_MCP_BROWSER_MODULES'))->setChromePath(getenv('LENS_MCP_CHROME_PATH'));
        }
    });
    app()->instance(AxeScanner::class, new class extends AxeScanner
    {
        protected function browsershotForUrl(string $url): Browsershot
        {
            return Browsershot::url($url)->setNodeModulePath(getenv('LENS_MCP_BROWSER_MODULES'))->setChromePath(getenv('LENS_MCP_CHROME_PATH'));
        }
    });
});

afterEach(function () {
    if (isset($this->browserServer)) {
        $this->browserServer->stop();
    }
    if (isset($this->browserFile)) {
        @unlink($this->browserFile);
    }
    if (isset($this->browserDirectory)) {
        File::deleteDirectory($this->browserDirectory);
    }
});

test('real MCP browser scan maps source applies a patch and verifies the changed page', function () {
    $run = app(RunService::class)->start(['url' => '/', 'level' => 'aa', 'async' => false, 'save_history' => false]);
    expect($run['status'])->toBe('completed');
    $issue = collect(app(RunStore::class)->read($run['run_id'])['issues'])->firstWhere('id', 'image-alt');
    expect($issue)->not->toBeNull()->and($issue['fileName'])->toBe(basename($this->browserFile));
    $patch = app(SourceContext::class)->prepare($run['run_id'], $issue['issue_ref'], str_replace('<img ', '<img alt="Test logo" ', $issue['htmlSnippet']));
    app(SourceContext::class)->apply($run['run_id'], $patch['patch_id']);
    copy($this->browserFile, $this->browserDirectory.'/page.html');
    $verified = app(RunService::class)->verify($run['run_id'], false);
    expect($verified['status'])->toBe('completed')->and($verified['comparison']['complete'])->toBeTrue()
        ->and(array_column($verified['comparison']['no_longer_detected'], 'rule_id'))->toContain('image-alt');
    $export = app(ToolActions::class)->export($verified['run_id'], 'pdf');
    expect(substr(file_get_contents($export['path']), 0, 5))->toBe('%PDF-');
});

test('real MCP browser replays interactive states for both measurement and preview', function () {
    $run = app(RunService::class)->start(['url' => '/', 'mode' => 'states', 'script' => "state: Initial\nstate: Dialog\nclick: #open", 'async' => false, 'save_history' => false]);
    expect($run['status'])->toBe('completed');
    $issue = collect(app(RunStore::class)->read($run['run_id'])['issues'])->firstWhere('id', 'button-name');
    expect($issue['stateLabel'])->toBe('Dialog');
    $preview = app(ToolActions::class)->preview($run['run_id'], $issue['issue_ref']);
    expect(substr($preview['_image'], 1, 3))->toBe('PNG');
});

test('real MCP browser rejects redirected pages instead of verifying the login page', function () {
    $run = app(RunService::class)->start(['url' => '/redirect', 'async' => false, 'save_history' => false]);
    expect($run['status'])->toBe('failed')->and($run['failed_urls'])->toBe([$this->browserUrl.'/redirect']);
});
