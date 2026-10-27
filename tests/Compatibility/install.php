<?php

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Artisan;
use LensForLaravel\LensForLaravel\Mcp\ClientConfigurationInstaller;
use LensForLaravel\LensForLaravel\Services\AiFixAvailability;
use LensForLaravel\LensForLaravel\Services\McpAvailability;
use LensForLaravel\LensForLaravel\Support\InstallationState;

$app = require __DIR__.'/bootstrap.php';
$state = app(InstallationState::class);

if (in_array('--claim', $argv, true)) {
    echo $state->markInstalled() ? 'first' : 'repeat';
    exit;
}

$paths = [$state->path(), base_path('.mcp.json'), base_path('.codex/config.toml'), app(McpAvailability::class)->markerPath()];
$originals = [];
foreach ($paths as $path) {
    $originals[$path] = is_file($path) ? file_get_contents($path) : null;
}
$applicationFiles = [];
foreach (['composer.json', 'composer.lock', 'package.json', '.env', 'config/lens-for-laravel.php'] as $path) {
    $applicationFiles[$path] = is_file(base_path($path)) ? file_get_contents(base_path($path)) : null;
}

try {
    @unlink($state->path());
    config(['lens-for-laravel.locale' => 'en']);
    lensCheck(Artisan::call('list', ['--raw' => true]) === 0, 'Artisan list failed');
    lensCheck(! is_file($state->path()) && ! str_contains(Artisan::output(), 'Would you like'), 'Welcome leaked into normal commands');

    // Race real PHP processes over the persistent first-install marker.
    $children = [];
    for ($i = 0; $i < 8; $i++) {
        $process = proc_open([PHP_BINARY, '-d', 'error_reporting='.(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED), __FILE__, '--claim'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        lensCheck(is_resource($process), 'Cannot start installation race worker');
        fclose($pipes[0]);
        $children[] = [$process, $pipes];
    }
    $results = [];
    foreach ($children as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $results[] = [proc_close($process), $output, $errors];
    }
    $first = 0;
    foreach ($results as [$exitCode, $output, $errors]) {
        lensCheck($exitCode === 0 && $errors === '', 'Installation race worker failed: '.$errors);
        lensCheck(in_array($output, ['first', 'repeat'], true), 'Unexpected installation race output');
        $first += $output === 'first' ? 1 : 0;
    }
    lensCheck($first === 1, 'Concurrent installation showed more than one welcome');
    unlink($state->path());

    lensCheck(Artisan::call('lens:install', ['--no-interaction' => true, '--no-ansi' => true]) === 0, 'First installation failed');
    $output = Artisan::output();
    lensCheck(str_contains($output, 'Thanks for using Lens for Laravel! Would you like to show some love and leave a star on GitHub?'), 'Approved star text missing');
    lensCheck(! str_contains($output, "\e[") && ! str_contains($output, 'selected'), 'Unwanted ANSI or provider selection');
    lensCheck(str_contains($output, '/lens-for-laravel/dashboard'), 'Dashboard URL missing');
    if (app(AiFixAvailability::class)->available()) {
        lensCheck(str_contains($output, 'SDK installed'), 'Real AI SDK was not recognized');
    } else {
        lensCheck(str_contains($output, 'PHP 8.3+') && str_contains($output, 'Laravel 12+'), 'AI compatibility requirements missing');
    }

    lensCheck(Artisan::call('lens:install', ['--no-interaction' => true]) === 0, 'Repeat installation failed');
    $output = Artisan::output();
    lensCheck(str_contains($output, 'already been installed') && ! str_contains($output, 'GitHub') && ! str_contains($output, 'AI Fix'), 'Welcome repeated');

    if (InstalledVersions::isInstalled('laravel/mcp')) {
        foreach (['claude', 'codex'] as $client) {
            @unlink(base_path('.mcp.json'));
            @unlink(base_path('.codex/config.toml'));
            unlink($state->path());
            app(ClientConfigurationInstaller::class)->install($client, base_path(), true);
            lensCheck(Artisan::call('lens:install', ['--no-interaction' => true]) === 0, 'Configured MCP installation failed');
            $output = Artisan::output();
            lensCheck(str_contains($output, 'Client configured'), 'Real MCP client configuration was not recognized');
            lensCheck(! str_contains($output, 'Claude') && ! str_contains($output, 'Codex') && ! str_contains($output, 'Gemini'), 'Provider or client leaked into welcome');
        }
    }
    foreach ($applicationFiles as $path => $contents) {
        lensCheck((is_file(base_path($path)) ? file_get_contents(base_path($path)) : null) === $contents, 'Installation modified application file: '.$path);
    }
} finally {
    foreach ($originals as $path => $contents) {
        if ($contents === null) {
            @unlink($path);
        } else {
            file_put_contents($path, $contents);
        }
    }
}

echo json_encode(['php' => PHP_VERSION, 'laravel' => app()->version(), 'checks' => $GLOBALS['lens_checks'], 'concurrent_installers' => 8], JSON_THROW_ON_ERROR).PHP_EOL;
