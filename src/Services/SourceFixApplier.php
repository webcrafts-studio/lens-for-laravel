<?php

namespace LensForLaravel\LensForLaravel\Services;

use LensForLaravel\LensForLaravel\Exceptions\McpException;

class SourceFixApplier
{
    public function __construct(protected SourceFileResolver $sources) {}

    public function apply(string $name, string $original, string $replacement, ?string $expectedHash = null, ?int $offset = null): string
    {
        if ($original === '' || trim($replacement) === '' || strlen($replacement) > 12000) {
            throw new McpException('INVALID_PATCH', 'invalid_patch');
        }
        foreach (['shell_exec', 'system', 'exec', 'passthru', 'proc_open', 'popen', 'eval'] as $function) {
            if (preg_match('/\b'.preg_quote($function, '/').'\s*\(/i', $replacement)) {
                throw new McpException('INVALID_PATCH', 'invalid_patch');
            }
        }
        foreach (['<?php', '<?='] as $tag) {
            if (str_contains($replacement, $tag) && ! str_contains($original, $tag)) {
                throw new McpException('INVALID_PATCH', 'invalid_patch');
            }
        }

        $source = $this->sources->resolve($name);
        $lockDir = storage_path('framework/cache/lens-locks');
        if (! is_dir($lockDir) && ! mkdir($lockDir, 0700, true) && ! is_dir($lockDir)) {
            throw new McpException('WRITE_FAILED', 'storage_failed');
        }
        $lock = fopen($lockDir.'/'.hash('sha256', $source['path']).'.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX)) {
            throw new McpException('WRITE_FAILED', 'storage_failed');
        }
        $temporary = null;
        try {
            $fresh = $this->sources->resolve($name);
            if ($fresh['path'] !== $source['path']) {
                throw new McpException('SOURCE_CHANGED', 'source_changed');
            }
            $content = file_get_contents($source['path']);
            if ($content === false || ($expectedHash !== null && ! hash_equals($expectedHash, hash('sha256', $content)))) {
                throw new McpException('SOURCE_CHANGED', 'source_changed');
            }
            $offset ??= strpos($content, $original);
            if ($offset === false || substr($content, $offset, strlen($original)) !== $original) {
                throw new McpException('SOURCE_CHANGED', 'source_changed');
            }
            $updated = substr_replace($content, $replacement, $offset, strlen($original));
            $temporary = tempnam(dirname($source['path']), '.lens-');
            if (! $temporary || file_put_contents($temporary, $updated) !== strlen($updated)) {
                throw new McpException('WRITE_FAILED', 'storage_failed');
            }
            chmod($temporary, fileperms($source['path']) & 0777);
            if (hash_file('sha256', $source['path']) !== hash('sha256', $content) || ! rename($temporary, $source['path'])) {
                throw new McpException('SOURCE_CHANGED', 'source_changed');
            }
            $this->sources->invalidate($source);

            return hash('sha256', $updated);
        } finally {
            if ($temporary && is_file($temporary)) {
                @unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
