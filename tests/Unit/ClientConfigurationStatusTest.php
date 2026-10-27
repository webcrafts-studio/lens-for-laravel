<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LensForLaravel\LensForLaravel\Mcp\ClientConfigurationInstaller;

beforeEach(function () {
    $this->clientRoot = sys_get_temp_dir().'/lens-client-status-'.Str::uuid();
    File::ensureDirectoryExists($this->clientRoot);
});

afterEach(function () {
    File::deleteDirectory($this->clientRoot);
});

test('client status detects a Lens connection written by the installer', function (string $client) {
    $installer = app(ClientConfigurationInstaller::class);
    expect($installer->configured($this->clientRoot))->toBeFalse();
    $path = $installer->install($client, $this->clientRoot);
    $before = file_get_contents($path);

    expect($installer->configured($this->clientRoot))->toBeTrue()
        ->and(file_get_contents($path))->toBe($before);

    unlink($path);
    expect($installer->configured($this->clientRoot))->toBeFalse();
})->with(['claude', 'codex']);

test('client status rejects malformed unrelated or foreign project entries', function () {
    $installer = app(ClientConfigurationInstaller::class);
    foreach (['not json', '"string"', '{"mcpServers":[]}', '{"mcpServers":{"lens":{"command":"other"}}}'] as $contents) {
        file_put_contents($this->clientRoot.'/.mcp.json', $contents);
        expect($installer->configured($this->clientRoot))->toBeFalse();
    }
    $path = $installer->install('claude', $this->clientRoot, true);
    file_put_contents($path, str_replace($this->clientRoot.'/artisan', '/another/project/artisan', file_get_contents($path)));
    expect($installer->configured($this->clientRoot))->toBeFalse();
});

test('client status rejects disabled duplicate or changed Codex connections', function () {
    $installer = app(ClientConfigurationInstaller::class);
    $path = $installer->install('codex', $this->clientRoot);
    $original = file_get_contents($path);
    foreach ([$original."enabled = false\n", $original."  enabled = false # disabled\n", $original.$original, str_replace('lens:mcp', 'another:server', $original)] as $contents) {
        file_put_contents($path, $contents);
        expect($installer->configured($this->clientRoot))->toBeFalse();
    }
    file_put_contents($path, str_replace("\n", "\r\n", $original));
    expect($installer->configured($this->clientRoot))->toBeTrue();
});

test('client status ignores linked or oversized configurations without modifying them', function () {
    $installer = app(ClientConfigurationInstaller::class);
    $path = $installer->install('claude', $this->clientRoot);
    rename($path, $path.'.original');
    symlink($path.'.original', $path);
    expect($installer->configured($this->clientRoot))->toBeFalse();
    unlink($path);
    file_put_contents($path, str_repeat(' ', 1048577));
    expect($installer->configured($this->clientRoot))->toBeFalse()
        ->and(filesize($path))->toBe(1048577);
});
