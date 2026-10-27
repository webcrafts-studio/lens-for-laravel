<?php

namespace LensForLaravel\LensForLaravel\Services;

use LensForLaravel\LensForLaravel\Exceptions\McpException;

class SourceFileResolver
{
    public function resolve(string $name): array
    {
        if ($name === '' || str_contains($name, '..') || str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) {
            throw new McpException('INVALID_SOURCE', 'invalid_source');
        }

        if (str_ends_with($name, '.blade.php')) {
            $root = resource_path('views');
            $relative = $name;
            $type = 'blade';
        } elseif (str_starts_with($name, 'js/') && in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['js', 'jsx', 'ts', 'tsx', 'vue'], true)) {
            $root = resource_path('js');
            $relative = substr($name, 3);
            $type = str_ends_with($name, '.vue') ? 'vue' : 'react';
        } else {
            throw new McpException('INVALID_SOURCE', 'invalid_source');
        }

        $root = realpath($root);
        $path = $root ? realpath($root.DIRECTORY_SEPARATOR.$relative) : false;
        if (! $path || ! is_file($path) || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_readable($path)) {
            throw new McpException('INVALID_SOURCE', 'invalid_source');
        }

        return ['path' => $path, 'type' => $type, 'relative_path' => 'resources/'.($type === 'blade' ? 'views/'.$name : $name)];
    }

    public function invalidate(array $source): void
    {
        clearstatcache(true, $source['path']);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($source['path'], true);
        }
        if ($source['type'] === 'blade' && app()->bound('blade.compiler')) {
            $compiled = app('blade.compiler')->getCompiledPath($source['path']);
            if (is_file($compiled)) {
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($compiled, true);
                }
                @unlink($compiled);
            }
        }
    }
}
