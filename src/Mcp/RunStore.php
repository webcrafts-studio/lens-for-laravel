<?php

namespace LensForLaravel\LensForLaravel\Mcp;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use LensForLaravel\LensForLaravel\Exceptions\McpException;

class RunStore
{
    public const TERMINAL = ['completed', 'partial', 'failed', 'cancelled'];

    public function directory(): string
    {
        $path = config('lens-for-laravel.mcp.storage_path') ?: storage_path('app/lens-for-laravel/mcp');

        return rtrim($path, DIRECTORY_SEPARATOR).'/'.substr(hash('sha256', base_path()), 0, 16);
    }

    public function path(string $id, string $suffix = '.json'): string
    {
        if (! Str::isUuid($id)) {
            throw new McpException('RUN_NOT_FOUND', 'run_missing');
        }

        return $this->directory().'/'.$id.$suffix;
    }

    public function create(array $request): array
    {
        $this->ensureDirectory();

        return $this->locked('catalog', function () use ($request) {
            $this->prune();
            $runs = $this->all();
            foreach ($runs as $run) {
                if (($request['idempotency_key'] ?? null) !== null && $run['request']['idempotency_key'] === $request['idempotency_key']) {
                    if ($run['request'] !== $request) {
                        throw new McpException('IDEMPOTENCY_CONFLICT', 'invalid_arguments');
                    }

                    return [...$run, 'created_new' => false];
                }
                if (! in_array($run['status'], self::TERMINAL, true) && $run['deadline'] < time()) {
                    $this->update($run['run_id'], function ($expired) {
                        $expired['status'] = 'failed';
                        $expired['error'] = (new McpException('RUN_TIMEOUT', 'timeout'))->toArray();

                        return $expired;
                    });
                }
            }
            $active = array_filter($runs, fn ($run) => ! in_array($run['status'], self::TERMINAL, true) && $run['deadline'] >= time());
            if (count($active) >= max(1, (int) config('lens-for-laravel.mcp.max_concurrent_runs', 2))) {
                throw new McpException('RUN_LIMIT', 'run_limit', true);
            }
            if (count($runs) >= max(10, (int) config('lens-for-laravel.mcp.max_stored_runs', 200))) {
                throw new McpException('RESULT_LIMIT', 'result_limit');
            }
            $run = [
                'run_id' => (string) Str::uuid(), 'schema_version' => 1,
                'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(),
                'expires_at' => now()->addHours(max(1, (int) config('lens-for-laravel.mcp.retention_hours', 24)))->timestamp,
                'deadline' => time() + max(30, (int) config('lens-for-laravel.mcp.run_timeout', 900)),
                'status' => 'queued', 'request' => $request, 'issues' => [], 'changes' => [],
                'urls_scanned' => [], 'failed_urls' => [], 'progress' => ['completed' => 0, 'total' => count($request['urls'])],
            ];
            $this->write($run);

            return [...$run, 'created_new' => true];
        });
    }

    public function read(string $id): array
    {
        $path = $this->path($id);
        if (! is_file($path) || is_link($path)) {
            throw new McpException('RUN_NOT_FOUND', 'run_missing');
        }
        if (filesize($path) > max(1048576, (int) config('lens-for-laravel.mcp.max_run_bytes', 16777216)) * 2) {
            throw new McpException('RESULT_LIMIT', 'result_limit');
        }
        try {
            $run = json_decode(Crypt::decryptString(file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new McpException('STORE_FAILED', 'storage_failed');
        }
        if ($run['expires_at'] < time()) {
            throw new McpException('RUN_EXPIRED', 'run_missing');
        }

        return $run;
    }

    public function update(string $id, callable $change): array
    {
        return $this->locked($id, function () use ($id, $change) {
            $run = $change($this->read($id));
            $run['updated_at'] = now()->toIso8601String();
            $this->write($run);

            return $run;
        });
    }

    public function locked(string $name, callable $callback): mixed
    {
        $this->ensureDirectory();
        if ($name !== 'catalog' && ! Str::isUuid($name)) {
            throw new McpException('RUN_NOT_FOUND', 'run_missing');
        }
        $lock = fopen($this->directory().'/'.$name.'.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX)) {
            throw new McpException('STORE_FAILED', 'storage_failed');
        }
        try {
            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function all(): array
    {
        $runs = [];
        foreach (glob($this->directory().'/*.json') ?: [] as $path) {
            try {
                $run = $this->read(basename($path, '.json'));
                $runs[] = $run;
            } catch (McpException) {
                continue;
            }
        }
        usort($runs, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $runs;
    }

    public function prune(): int
    {
        $count = 0;
        foreach (glob($this->directory().'/*.json') ?: [] as $path) {
            $id = basename($path, '.json');
            if (! Str::isUuid($id)) {
                continue;
            }
            $expired = filemtime($path) < time() - max(1, (int) config('lens-for-laravel.mcp.retention_hours', 24)) * 3600;
            if (! $expired) {
                try {
                    $this->read($id);
                } catch (McpException $e) {
                    $expired = $e->errorCode === 'RUN_EXPIRED';
                }
            }
            if ($expired) {
                $this->locked($id, function () use ($id, $path) {
                    @unlink($path);
                    foreach (['.export.json', '.export.pdf', '.worker'] as $suffix) {
                        @unlink($this->path($id, $suffix));
                    }
                });
                $count++;
            }
        }

        return $count;
    }

    private function write(array $run): void
    {
        $json = json_encode($run, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        if (strlen($json) > max(1048576, (int) config('lens-for-laravel.mcp.max_run_bytes', 16777216))) {
            throw new McpException('RESULT_LIMIT', 'result_limit');
        }
        $path = $this->path($run['run_id']);
        $tmp = tempnam($this->directory(), '.run-');
        try {
            if (! $tmp || file_put_contents($tmp, Crypt::encryptString($json)) === false || ! chmod($tmp, 0600) || ! rename($tmp, $path)) {
                throw new McpException('STORE_FAILED', 'storage_failed');
            }
        } finally {
            if ($tmp && is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private function ensureDirectory(): void
    {
        $dir = $this->directory();
        if ((! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) || ! is_writable($dir)) {
            throw new McpException('STORE_FAILED', 'storage_failed');
        }
    }
}
