<?php

use Illuminate\Support\Collection;
use LensForLaravel\LensForLaravel\DTOs\AuthenticatedScanContext;
use LensForLaravel\LensForLaravel\DTOs\Issue;
use LensForLaravel\LensForLaravel\Exceptions\McpException;
use LensForLaravel\LensForLaravel\Mcp\RunExecutor;
use LensForLaravel\LensForLaravel\Mcp\RunProcessManager;
use LensForLaravel\LensForLaravel\Mcp\RunService;
use LensForLaravel\LensForLaravel\Mcp\RunStore;
use LensForLaravel\LensForLaravel\Services\AxeScanner;
use LensForLaravel\LensForLaravel\Services\FileLocator;
use LensForLaravel\LensForLaravel\Services\SourceFixApplier;
use Symfony\Component\Process\Process;

$app = require __DIR__.'/bootstrap.php';
config(['lens-for-laravel.mcp.max_concurrent_runs' => 2, 'lens-for-laravel.mcp.max_stored_runs' => 200]);
$store = app(RunStore::class);
$action = $argv[1] ?? 'parent';
$id = $argv[2] ?? '';
if ($action !== 'parent') {
    try {
        $result = match ($action) {
            'create' => $store->create(app(RunService::class)->normalize(['url' => '/', 'save_history' => false, 'idempotency_key' => $id])),
            'update' => (function () use ($store, $id) {
                for ($i = 0; $i < 30; $i++) {
                    $store->update($id, function ($run) {
                        $run['stress_counter'] = ($run['stress_counter'] ?? 0) + 1;

                        return $run;
                    });
                }

                return ['updated' => 30];
            })(),
            'read' => (function () use ($store, $id) {
                for ($i = 0; $i < 1000; $i++) {
                    $store->read($id);
                }

                return ['read' => 1000];
            })(),
            'apply' => ['hash' => app(SourceFixApplier::class)->apply('stress.blade.php', '<img>', '<img alt="worker-'.$argv[4].'">', $argv[3])],
            'hold' => $store->locked('catalog', function () {
                file_put_contents(base_path('lock-ready'), 'ready');
                sleep(45);

                return [];
            }),
            'execute' => (function () use ($id) {
                app()->instance(FileLocator::class, new class extends FileLocator
                {
                    public function locate(string $htmlSnippet, string $selector): ?array
                    {
                        return null;
                    }
                });
                app()->instance(AxeScanner::class, new class extends AxeScanner
                {
                    public function scan(string $url, ?string $wcagVersion = null, ?AuthenticatedScanContext $auth = null): Collection
                    {
                        usleep(300000);
                        if (str_contains($url, '/error')) {
                            throw new RuntimeException('SECRET_SCANNER_DETAIL');
                        }

                        return collect([new Issue('image-alt', 'serious', 'Image needs alt', '', '<img>', 'img', ['wcag2a'], $url)]);
                    }
                });

                return app(RunExecutor::class)->execute($id);
            })(),
        };
        echo json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR).PHP_EOL;
    } catch (McpException $e) {
        echo json_encode(['ok' => false, 'code' => $e->errorCode], JSON_THROW_ON_ERROR).PHP_EOL;
    }
    exit;
}

function lensStressProcesses(array $arguments): array
{
    $processes = [];
    foreach ($arguments as $args) {
        $process = new Process([PHP_BINARY, __FILE__, ...$args], getcwd(), timeout: 40);
        $process->start();
        $processes[] = $process;
    }
    $results = [];
    try {
        foreach ($processes as $process) {
            $process->wait();
            lensCheck($process->isSuccessful(), 'Stress child failed: '.$process->getErrorOutput().$process->getOutput());
            $results[] = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
        }
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(0.1);
            }
        }
    }

    return $results;
}

$started = microtime(true);
$key = 'stress-'.bin2hex(random_bytes(12));
$results = lensStressProcesses(array_fill(0, 24, ['create', $key]));
$ids = array_unique(array_column(array_column($results, 'result'), 'run_id'));
lensCheck(count($ids) === 1 && count(array_filter($results, fn ($r) => $r['ok'])) === 24, 'Concurrent idempotency created duplicate runs');
$id = reset($ids);
$results = lensStressProcesses([...array_fill(0, 16, ['update', $id]), ...array_fill(0, 4, ['read', $id])]);
lensCheck($store->read($id)['stress_counter'] === 480, 'Concurrent updates lost data');
app(RunProcessManager::class)->cancel($id);

$results = lensStressProcesses(array_map(fn ($i) => ['create', $key.'-'.$i], range(1, 20)));
lensCheck(count(array_filter($results, fn ($r) => $r['ok'])) === 2, 'Concurrent admission exceeded active run limit');
lensCheck(count(array_filter($results, fn ($r) => ($r['code'] ?? '') === 'RUN_LIMIT')) === 18, 'Admission failed for an unexpected reason');
foreach ($store->all() as $run) {
    app(RunProcessManager::class)->cancel($run['run_id']);
}

$file = resource_path('views/stress.blade.php');
file_put_contents($file, '<img>');
$hash = hash_file('sha256', $file);
$results = lensStressProcesses(array_map(fn ($i) => ['apply', '-', $hash, (string) $i], range(1, 16)));
lensCheck(count(array_filter($results, fn ($r) => $r['ok'])) === 1, 'Concurrent source writes did not select one winner');
lensCheck(count(array_filter($results, fn ($r) => ($r['code'] ?? '') === 'SOURCE_CHANGED')) === 15, 'Stale source changes were accepted');
lensCheck(preg_match('/^<img alt="worker-\d+">$/', file_get_contents($file)) === 1, 'Source was corrupted by concurrent writers');

$run = $store->create(app(RunService::class)->normalize(['url' => '/', 'save_history' => false]));
$results = lensStressProcesses(array_fill(0, 8, ['execute', $run['run_id']]));
lensCheck(count(array_filter($results, fn ($r) => $r['ok'])) === 1, 'Worker lease allowed duplicate execution');
lensCheck(count($store->read($run['run_id'])['urls_scanned']) === 1, 'Duplicate worker recorded duplicate pages');

$run = $store->create(app(RunService::class)->normalize(['mode' => 'multiple', 'urls' => ['/', '/error', '/last'], 'save_history' => false]));
$result = lensStressProcesses([['execute', $run['run_id']]])[0]['result'];
lensCheck($result['status'] === 'partial' && count($result['urls_scanned']) === 2 && count($result['failed_urls']) === 1, 'Partial scan reported complete coverage');
lensCheck(! str_contains(json_encode($result), 'SECRET_SCANNER_DETAIL'), 'Scanner details leaked');

$run = $store->create(app(RunService::class)->normalize(['url' => '/', 'save_history' => false]));
$worker = new Process([PHP_BINARY, __FILE__, 'execute', $run['run_id']], getcwd(), timeout: 10);
try {
    $worker->start();
    $deadline = microtime(true) + 5;
    while ($store->read($run['run_id'])['status'] === 'queued' && microtime(true) < $deadline) {
        usleep(5000);
    }
    app(RunProcessManager::class)->cancel($run['run_id']);
    $worker->wait();
    lensCheck($store->read($run['run_id'])['status'] === 'cancelled', 'Worker overwrote cancellation');
} finally {
    $worker->stop(0.1);
}

$run = $store->create(app(RunService::class)->normalize(['url' => '/', 'save_history' => false]));
$store->update($run['run_id'], function ($run) {
    $run['deadline'] = time() - 1;

    return $run;
});
$expired = lensStressProcesses([['execute', $run['run_id']]])[0]['result'];
lensCheck($expired['status'] === 'failed' && $expired['error']['code'] === 'RUN_TIMEOUT', 'Expired worker did not fail safely');

$holder = new Process([PHP_BINARY, __FILE__, 'hold'], getcwd(), timeout: 10);
try {
    @unlink(base_path('lock-ready'));
    $holder->start();
    $deadline = microtime(true) + 5;
    while (! is_file(base_path('lock-ready')) && microtime(true) < $deadline) {
        usleep(5000);
    }
    lensCheck(is_file(base_path('lock-ready')), 'Lock holder did not start');
    $holder->signal(9);
    $holder->wait();
    $recovered = lensStressProcesses([['create', $key.'-after-crash']])[0];
    lensCheck($recovered['ok'], 'A killed process left the catalog locked');
    app(RunProcessManager::class)->cancel($recovered['result']['run_id']);
} finally {
    $holder->stop(0.1);
}

$before = $store->read($id);
try {
    config(['lens-for-laravel.mcp.max_run_bytes' => 1048576]);
    $store->update($id, function ($run) {
        $run['oversized'] = str_repeat('x', 1100000);

        return $run;
    });
    throw new RuntimeException('Oversized run was accepted');
} catch (McpException $e) {
    lensCheck($e->errorCode === 'RESULT_LIMIT' && $store->read($id) === $before, 'Failed write corrupted stored results');
}
$path = $store->path($id);
$encrypted = file_get_contents($path);
try {
    file_put_contents($path, 'tampered');
    try {
        $store->read($id);
        throw new RuntimeException('Tampered run was accepted');
    } catch (McpException $e) {
        lensCheck($e->errorCode === 'STORE_FAILED', 'Tampering returned an unsafe error');
    }
} finally {
    file_put_contents($path, $encrypted);
}
lensCheck($store->read($id) === $before, 'Store failed to recover');
echo json_encode(['checks' => $GLOBALS['lens_checks'], 'concurrent_children' => 24, 'locked_updates' => 480, 'concurrent_reads' => 4000, 'source_writers' => 16,
    'seconds' => round(microtime(true) - $started, 3), 'peak_memory_bytes' => memory_get_peak_usage(true)], JSON_THROW_ON_ERROR).PHP_EOL;
