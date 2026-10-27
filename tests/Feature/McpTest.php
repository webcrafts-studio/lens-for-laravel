<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\McpServiceProvider;
use LensForLaravel\LensForLaravel\DTOs\Issue;
use LensForLaravel\LensForLaravel\Exceptions\McpException;
use LensForLaravel\LensForLaravel\Mcp\ClientConfigurationInstaller;
use LensForLaravel\LensForLaravel\Mcp\HistoryRepository;
use LensForLaravel\LensForLaravel\Mcp\LensServer;
use LensForLaravel\LensForLaravel\Mcp\LensTool;
use LensForLaravel\LensForLaravel\Mcp\RunExecutor;
use LensForLaravel\LensForLaravel\Mcp\RunProcessManager;
use LensForLaravel\LensForLaravel\Mcp\RunService;
use LensForLaravel\LensForLaravel\Mcp\RunStore;
use LensForLaravel\LensForLaravel\Mcp\SourceContext;
use LensForLaravel\LensForLaravel\Mcp\ToolActions;
use LensForLaravel\LensForLaravel\Mcp\ToolCatalog;
use LensForLaravel\LensForLaravel\Models\Scan;
use LensForLaravel\LensForLaravel\Services\AxeScanner;
use LensForLaravel\LensForLaravel\Services\FileLocator;
use LensForLaravel\LensForLaravel\Services\McpAvailability;
use LensForLaravel\LensForLaravel\Services\SiteCrawler;

require_once dirname(__DIR__).'/Fixtures/McpSupport.php';

beforeEach(function () {
    if (! class_exists(Server::class)) {
        $this->markTestSkipped('The optional laravel/mcp development dependency is not installed.');
    }
    $this->mcpDirectory = sys_get_temp_dir().'/lens-mcp-'.Str::uuid();
    mkdir($this->mcpDirectory, 0700, true);
    config(['lens-for-laravel.mcp.enabled' => true, 'lens-for-laravel.mcp.storage_path' => $this->mcpDirectory,
        'app.url' => 'http://localhost', 'lens-for-laravel.ai_enabled' => false]);
    $this->mock(FileLocator::class)->shouldReceive('locate')->andReturnNull();
    $this->mcpFile = resource_path('views/mcp-'.Str::uuid().'.blade.php');
    File::ensureDirectoryExists(dirname($this->mcpFile));
    file_put_contents($this->mcpFile, "<button>Save</button>\n<button>Save</button>\n");
});

afterEach(function () {
    if (isset($this->mcpDirectory)) {
        File::deleteDirectory($this->mcpDirectory);
    }
    if (isset($this->mcpFile)) {
        @unlink($this->mcpFile);
    }
});

test('MCP scan runs without AI SDK and keeps WCAG and level independent', function () {
    $result = lensMcpRun([lensMcpIssue(), new Issue('contrast', 'serious', 'Contrast', '', '', 'p', ['wcag2aa']), new Issue('best', 'minor', 'Best', '', '', 'div')], ['wcag' => '2.2', 'level' => 'aa']);
    expect($result['status'])->toBe('completed')->and($result['issue_count'])->toBe(2)->and($result['wcag'])->toBe('2.2');
    $issues = app(RunService::class)->issues($result['run_id']);
    expect($issues['issues'])->toHaveCount(2)->and($issues['issues'][0])->not->toHaveKey('htmlSnippet');
});

test('MCP gives repeated rules distinct issue references and paginates results', function () {
    $result = lensMcpRun([lensMcpIssue(), lensMcpIssue()]);
    $page = app(RunService::class)->issues($result['run_id'], ['per_page' => 1, 'page' => 2]);
    $run = app(RunStore::class)->read($result['run_id']);
    expect($page['total'])->toBe(2)->and($page['issues'])->toHaveCount(1)
        ->and($run['issues'][0]['issue_ref'])->not->toBe($run['issues'][1]['issue_ref']);
});

test('MCP stores sensitive scan manifests encrypted with restrictive permissions', function () {
    $request = app(RunService::class)->normalize(['mode' => 'states', 'script' => 'state: Secret State']);
    $run = app(RunStore::class)->create($request);
    $path = app(RunStore::class)->path($run['run_id']);
    expect(file_get_contents($path))->not->toContain('Secret State')->not->toContain('request')
        ->and(fileperms($path) & 0777)->toBe(0600)
        ->and(app(RunStore::class)->read($run['run_id'])['request']['script'])->toBe('state: Secret State');
});

test('MCP rejects path traversal and run references outside this project', function () {
    expect(fn () => app(RunStore::class)->read('../../.env'))->toThrow(McpException::class);
    $run = lensMcpRun();
    $original = config('lens-for-laravel.mcp.storage_path');
    config(['lens-for-laravel.mcp.storage_path' => $original.'/other-project']);
    expect(fn () => app(RunStore::class)->read($run['run_id']))->toThrow(McpException::class);
});

test('MCP bounds active runs and does not execute cancelled work', function () {
    config(['lens-for-laravel.mcp.max_concurrent_runs' => 1]);
    $request = app(RunService::class)->normalize([]);
    $run = app(RunStore::class)->create($request);
    expect(fn () => app(RunStore::class)->create($request))->toThrow(McpException::class);
    app(RunProcessManager::class)->cancel($run['run_id']);
    $this->mock(AxeScanner::class)->shouldNotReceive('scan');
    expect(fn () => app(RunExecutor::class)->execute($run['run_id']))->toThrow(McpException::class);
    expect(app(RunStore::class)->read($run['run_id'])['status'])->toBe('cancelled');
});

test('MCP surfaces partial coverage and never verifies unscanned pages', function () {
    $scanner = $this->mock(AxeScanner::class);
    $scanner->shouldReceive('scan')->with('http://localhost/', '2.0', null)->andReturn(collect([lensMcpIssue()]));
    $scanner->shouldReceive('scan')->with('http://localhost/broken', '2.0', null)->andThrow(new RuntimeException('private provider detail'));
    $result = app(RunService::class)->start(['mode' => 'multiple', 'urls' => ['/', '/broken'], 'async' => false, 'save_history' => false]);
    expect($result['status'])->toBe('partial')->and($result['failed_urls'])->toBe(['http://localhost/broken']);
    $comparison = app(RunService::class)->compare($result['run_id'], $result['run_id']);
    expect($comparison['complete'])->toBeFalse();
});

test('MCP supports bounded crawling and preserves all successful page identities', function () {
    $this->mock(SiteCrawler::class)->shouldReceive('crawl')->once()->with('http://localhost/', 5, null)->andReturn(['http://localhost/', 'http://localhost/about']);
    $result = lensMcpRun([], ['mode' => 'website']);
    expect($result['status'])->toBe('completed')->and($result['urls_scanned'])->toHaveCount(2);
});

test('MCP uses the shared state grammar and state labels', function () {
    $issue = lensMcpIssue();
    $issue->stateLabel = 'Modal';
    $this->mock(AxeScanner::class)->shouldReceive('scanInteractiveStates')->once()->with('http://localhost/', [['label' => 'Modal', 'actions' => [['type' => 'click', 'selector' => '#open']]]], '2.2', null)->andReturn(collect([$issue]));
    $result = app(RunService::class)->start(['mode' => 'states', 'script' => "state: Modal\nclick: #open", 'wcag' => '2.2', 'async' => false, 'save_history' => false]);
    expect(app(RunService::class)->issues($result['run_id'])['issues'][0]['stateLabel'])->toBe('Modal');
    expect(fn () => app(RunService::class)->normalize(['mode' => 'states', 'urls' => ['/'], 'script' => 'state: A']))->toThrow(McpException::class);
});

test('MCP verification replays settings and distinguishes new issues', function () {
    $base = lensMcpRun([lensMcpIssue()], ['wcag' => '2.2', 'level' => 'aa']);
    $this->mock(AxeScanner::class)->shouldReceive('scan')->with('http://localhost/', '2.2', null)->andReturn(collect());
    $verified = app(RunService::class)->verify($base['run_id'], false);
    expect($verified['comparison']['complete'])->toBeTrue()
        ->and($verified['comparison']['no_longer_detected'])->toHaveCount(1)
        ->and($verified['parent_run_id'])->toBe($base['run_id']);
});

test('MCP comparison marks missing page coverage as unverified', function () {
    $base = lensMcpRun([lensMcpIssue()]);
    $current = lensMcpRun();
    app(RunStore::class)->update($current['run_id'], function ($run) {
        $run['status'] = 'partial';
        $run['urls_scanned'] = ['http://localhost/other'];

        return $run;
    });
    $comparison = app(RunService::class)->compare($base['run_id'], $current['run_id']);
    expect($comparison['no_longer_detected'])->toBeEmpty()->and($comparison['unverified'])->toHaveCount(1)->and($comparison['complete'])->toBeFalse();
});

test('MCP rejects verification comparisons across WCAG versions', function () {
    $a = lensMcpRun();
    $b = lensMcpRun([], ['wcag' => '2.2']);
    expect(fn () => app(RunService::class)->compare($a['run_id'], $b['run_id']))->toThrow(McpException::class);
});

test('MCP reads exact source context for a repeated element and detects stale code', function () {
    $result = lensMcpRun([lensMcpIssue(basename($this->mcpFile))]);
    $ref = app(RunStore::class)->read($result['run_id'])['issues'][0]['issue_ref'];
    $context = app(SourceContext::class)->get($result['run_id'], $ref);
    expect($context['source']['offset'])->toBe(strlen("<button>Save</button>\n"))->and($context['mapping'])->toBe('heuristic');
    file_put_contents($this->mcpFile, '<button>Changed</button>');
    expect(fn () => app(SourceContext::class)->get($result['run_id'], $ref))->toThrow(McpException::class);
});

test('MCP prepared patches target the reviewed occurrence and are idempotent', function () {
    config(['lens-for-laravel.mcp.allow_writes' => true]);
    $result = lensMcpRun([lensMcpIssue(basename($this->mcpFile))]);
    $ref = app(RunStore::class)->read($result['run_id'])['issues'][0]['issue_ref'];
    $context = app(SourceContext::class);
    $patch = $context->prepare($result['run_id'], $ref, '<button aria-label="Save changes">Save</button>');
    expect(file_get_contents($this->mcpFile))->not->toContain('aria-label');
    $context->apply($result['run_id'], $patch['patch_id']);
    expect(file_get_contents($this->mcpFile))->toBe("<button>Save</button>\n<button aria-label=\"Save changes\">Save</button>\n");
    expect($context->apply($result['run_id'], $patch['patch_id'])['already_applied'])->toBeTrue();
    $issues = app(RunService::class)->issues($result['run_id']);
    expect($issues['total'])->toBe(1)->and($issues['issues'][0]['fix_status'])->toBe('pending_verification');
});

test('MCP refuses stale prepared patches without overwriting new edits', function () {
    config(['lens-for-laravel.mcp.allow_writes' => true]);
    $result = lensMcpRun([lensMcpIssue(basename($this->mcpFile))]);
    $ref = app(RunStore::class)->read($result['run_id'])['issues'][0]['issue_ref'];
    $patch = app(SourceContext::class)->prepare($result['run_id'], $ref, '<button>New</button>');
    file_put_contents($this->mcpFile, 'unrelated work');
    expect(fn () => app(SourceContext::class)->apply($result['run_id'], $patch['patch_id']))->toThrow(McpException::class)
        ->and(file_get_contents($this->mcpFile))->toBe('unrelated work');
});

test('MCP history persists server results once and includes existing dashboard scans', function () {
    $result = lensMcpRun([lensMcpIssue()], ['save_history' => true]);
    $run = app(RunStore::class)->read($result['run_id']);
    app(HistoryRepository::class)->save($run);
    expect(Scan::where('run_id', $result['run_id'])->count())->toBe(1);
    $history = app(HistoryRepository::class)->read(['action' => 'show', 'scan_id' => $run['history_id']]);
    expect($history['issues']['total'])->toBe(1)->and($history['scan']['total_issues'])->toBe(1);
});

test('MCP JSON export does not overwrite the encrypted run snapshot', function () {
    $result = lensMcpRun([lensMcpIssue()]);
    $export = app(ToolActions::class)->export($result['run_id'], 'json');
    expect(json_decode(file_get_contents($export['path']), true)['issues'])->toHaveCount(1)
        ->and(app(RunStore::class)->read($result['run_id'])['status'])->toBe('completed');
});

test('MCP tools reject unknown arguments and hide write tools by default', function () {
    $definitions = app(ToolCatalog::class)->definitions();
    expect((new LensTool('lens_apply_patch', $definitions['lens_apply_patch']))->eligibleForRegistration())->toBeFalse();
    $tool = new LensTool('lens_status', $definitions['lens_status']);
    expect($tool->handle(new Request(['secret' => '.env']))->getStructuredContent()['error']['code'])->toBe('INVALID_ARGUMENTS');
    config(['lens-for-laravel.mcp.enabled' => false]);
    expect($tool->handle(new Request)->getStructuredContent()['error']['code'])->toBe('MCP_UNAVAILABLE');
});

test('MCP installer merges Claude config without losing unrelated entries', function () {
    file_put_contents($this->mcpDirectory.'/.mcp.json', '{"mcpServers":{"other":{"command":"other"}},"custom":true}');
    app(ClientConfigurationInstaller::class)->install('claude', $this->mcpDirectory);
    $data = json_decode(file_get_contents($this->mcpDirectory.'/.mcp.json'), true);
    expect($data['mcpServers']['other']['command'])->toBe('other')->and($data['custom'])->toBeTrue()
        ->and($data['mcpServers']['lens']['args'])->toBe([$this->mcpDirectory.'/artisan', 'lens:mcp', '--enabled'])
        ->and(file_get_contents(app(McpAvailability::class)->markerPath($this->mcpDirectory)))->toBe(hash('sha256', $this->mcpDirectory));
    app(ClientConfigurationInstaller::class)->install('claude', $this->mcpDirectory);
});

test('MCP installer merges Codex config idempotently and preserves other tables', function () {
    mkdir($this->mcpDirectory.'/.codex');
    file_put_contents($this->mcpDirectory.'/.codex/config.toml', "model = \"custom\"\n[mcp_servers.other]\ncommand = \"other\"\n");
    $installer = app(ClientConfigurationInstaller::class);
    $path = $installer->install('codex', $this->mcpDirectory);
    $installer->install('codex', $this->mcpDirectory);
    expect(file_get_contents($path))->toContain('model = "custom"')->toContain('[mcp_servers.other]')
        ->and(substr_count(file_get_contents($path), '[mcp_servers.lens]'))->toBe(1);
});

test('MCP installer refuses to overwrite another Lens configuration or malformed JSON', function () {
    file_put_contents($this->mcpDirectory.'/.mcp.json', '{"mcpServers":{"lens":{"command":"custom"}}}');
    expect(fn () => app(ClientConfigurationInstaller::class)->install('claude', $this->mcpDirectory))->toThrow(McpException::class);
    file_put_contents($this->mcpDirectory.'/.mcp.json', 'not json');
    expect(fn () => app(ClientConfigurationInstaller::class)->install('claude', $this->mcpDirectory, true))->toThrow(McpException::class);
});

test('MCP official Laravel server handles discovery and tool calls over JSON RPC', function () {
    app()->register(McpServiceProvider::class);
    $transport = new class implements Transport
    {
        public array $messages = [];

        public function onReceive(Closure $handler): void {}

        public function send(string $message, ?string $sessionId = null): void
        {
            $this->messages[] = json_decode($message, true);
        }

        public function run(): void {}

        public function sessionId(): string
        {
            return 'lens-test';
        }

        public function stream(Closure $stream): void
        {
            $stream();
        }
    };
    $server = new LensServer($transport);
    $server->start();
    $server->handle(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25', 'clientInfo' => ['name' => 'Lens Test', 'version' => '1'], 'capabilities' => (object) []]]));
    $server->handle(json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']));
    $server->handle(json_encode(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'lens_status', 'arguments' => (object) []]]));
    expect($transport->messages[0]['result']['serverInfo']['name'])->toBe('Lens for Laravel')
        ->and(array_column($transport->messages[1]['result']['tools'], 'name'))->toContain('lens_scan', 'lens_cancel_run', 'lens_history')->not->toContain('lens_apply_patch')
        ->and($transport->messages[2]['result']['isError'])->toBeFalse()
        ->and($transport->messages[2]['result']['structuredContent']['mcp']['available'])->toBeTrue();
    $server->handle(json_encode(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'resources/read', 'params' => ['uri' => 'lens://capabilities']]));
    $run = lensMcpRun();
    $server->handle(json_encode(['jsonrpc' => '2.0', 'id' => 5, 'method' => 'resources/read', 'params' => ['uri' => 'lens://runs/'.$run['run_id'].'/summary']]));
    $server->handle(json_encode(['jsonrpc' => '2.0', 'id' => 6, 'method' => 'prompts/get', 'params' => ['name' => 'lens_accessibility_fix']]));
    expect(json_decode($transport->messages[3]['result']['contents'][0]['text'], true)['mcp']['available'])->toBeTrue()
        ->and(json_decode($transport->messages[4]['result']['contents'][0]['text'], true)['run_id'])->toBe($run['run_id'])
        ->and($transport->messages[5]['result']['messages'][0]['content']['text'])->toContain('lens_verify');
});

test('MCP rejects unsafe URLs and incompatible state options', function (array $input) {
    expect(fn () => app(RunService::class)->normalize($input))->toThrow(Exception::class);
})->with([
    [['url' => 'file:///etc/passwd']], [['url' => 'http://evil.test/']], [['url' => '//evil.test/']],
    [['url' => 'http://localhost:9999/']], [['url' => 'http://user:secret@localhost/']],
    [['mode' => 'states', 'script' => "state: X\neval: something"]],
    [['mode' => 'website', 'script' => 'state: X']], [['max_pages' => 6]],
]);

test('MCP prepared writes reject execution payloads and symlinks outside source roots', function () {
    config(['lens-for-laravel.mcp.allow_writes' => true]);
    $run = lensMcpRun([lensMcpIssue(basename($this->mcpFile))]);
    $ref = app(RunStore::class)->read($run['run_id'])['issues'][0]['issue_ref'];
    $patch = app(SourceContext::class)->prepare($run['run_id'], $ref, '<button>{{ system ("id") }}</button>');
    expect(fn () => app(SourceContext::class)->apply($run['run_id'], $patch['patch_id']))->toThrow(McpException::class);
    $outside = $this->mcpDirectory.'/outside.blade.php';
    file_put_contents($outside, '<button>Outside</button>');
    unlink($this->mcpFile);
    symlink($outside, $this->mcpFile);
    expect(fn () => app(SourceContext::class)->get($run['run_id'], $ref))->toThrow(McpException::class);
});

test('MCP times out orphaned runs and releases concurrency for new scans', function () {
    config(['lens-for-laravel.mcp.max_concurrent_runs' => 1]);
    $store = app(RunStore::class);
    $run = $store->create(app(RunService::class)->normalize([]));
    $store->update($run['run_id'], function ($run) {
        $run['deadline'] = time() - 1;

        return $run;
    });
    $next = lensMcpRun();
    expect($next['status'])->toBe('completed')->and($store->read($run['run_id'])['status'])->toBe('failed');
});

test('MCP scan retries use the same completed run and reject changed idempotent requests', function () {
    $a = lensMcpRun([], ['idempotency_key' => 'retry-1']);
    $b = app(RunService::class)->start(['url' => '/', 'async' => false, 'save_history' => false, 'idempotency_key' => 'retry-1']);
    expect($b['run_id'])->toBe($a['run_id']);
    expect(fn () => app(RunService::class)->start(['url' => '/other', 'save_history' => false, 'idempotency_key' => 'retry-1']))->toThrow(McpException::class);
});

test('MCP baseline writes are explicit and comparisons preserve the configured fingerprint contract', function () {
    config(['lens-for-laravel.baseline_path' => $this->mcpDirectory.'/baseline.json']);
    $run = lensMcpRun([lensMcpIssue()]);
    $actions = app(ToolActions::class);
    expect(fn () => $actions->call('lens_baseline', ['run_id' => $run['run_id'], 'action' => 'save']))->toThrow(McpException::class);
    config(['lens-for-laravel.mcp.allow_writes' => true]);
    $actions->call('lens_baseline', ['run_id' => $run['run_id'], 'action' => 'save']);
    expect($actions->call('lens_baseline', ['run_id' => $run['run_id']])['existing'])->toHaveCount(1);
});

test('MCP worker refuses to overwrite completed measurements on a repeated work command', function () {
    $run = lensMcpRun([lensMcpIssue()]);
    expect(fn () => app(RunExecutor::class)->execute($run['run_id']))->toThrow(McpException::class);
    expect(app(RunStore::class)->read($run['run_id'])['status'])->toBe('completed');
});

test('MCP console commands queue inspect and cancel tracked scans', function () {
    $this->artisan('lens:mcp:scan', ['url' => '/', '--queue' => true])->assertSuccessful();
    $run = app(RunStore::class)->all()[0];
    $this->artisan('lens:mcp:runs', ['action' => 'status', 'run' => $run['run_id']])->assertSuccessful();
    $this->artisan('lens:mcp:runs', ['action' => 'cancel', 'run' => $run['run_id']])->assertSuccessful();
    expect(app(RunStore::class)->read($run['run_id'])['status'])->toBe('cancelled');
    $this->artisan('lens:mcp:runs', ['action' => 'doctor'])->assertSuccessful();
});

test('MCP expired snapshots and their exports are pruned', function () {
    $run = lensMcpRun();
    $path = app(ToolActions::class)->export($run['run_id'], 'json')['path'];
    app(RunStore::class)->update($run['run_id'], function ($run) {
        $run['expires_at'] = time() - 1;

        return $run;
    });
    expect(app(RunStore::class)->prune())->toBe(1)->and(is_file($path))->toBeFalse();
});
