<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Mcp\Server;
use LensForLaravel\LensForLaravel\Mcp\RunStore;
use LensForLaravel\LensForLaravel\Mcp\SourceContext;
use LensForLaravel\LensForLaravel\Services\FileLocator;

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

test('MCP records native edits and shares pending status with the dashboard', function () {
    $result = lensMcpRun([lensMcpIssue(basename($this->mcpFile))]);
    $ref = app(RunStore::class)->read($result['run_id'])['issues'][0]['issue_ref'];
    file_put_contents($this->mcpFile, '<button aria-label="Save">Save</button>');
    app(SourceContext::class)->record($result['run_id'], $ref);
    $this->getJson(route('lens-for-laravel.mcp.changes', ['since' => 0]))->assertOk()->assertJsonPath('changes.0.status', 'pending_verification');
    expect(app(RunStore::class)->read($result['run_id'])['issues'])->toHaveCount(1);
});

test('MCP dashboard renders synchronization only when integration is available', function () {
    $this->get(route('lens-for-laravel.dashboard'))->assertOk()->assertSee('this.mcpPollTimer = window.setInterval', false);
    config(['lens-for-laravel.mcp.enabled' => false]);
    $this->get(route('lens-for-laravel.dashboard'))->assertOk()->assertDontSee('this.mcpPollTimer = window.setInterval', false);
    $this->getJson(route('lens-for-laravel.mcp.changes', ['since' => 0]))->assertForbidden();
});
