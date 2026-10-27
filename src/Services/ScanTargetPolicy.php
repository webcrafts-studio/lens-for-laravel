<?php

namespace LensForLaravel\LensForLaravel\Services;

use LensForLaravel\LensForLaravel\Exceptions\McpException;

class ScanTargetPolicy
{
    public function resolve(string $url): string
    {
        $base = rtrim((string) config('app.url'), '/');
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            $url = $base.$url;
        }
        $parts = parse_url($url);
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! $parts || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            throw new McpException('INVALID_TARGET', 'invalid_target');
        }
        $origins = array_merge([$base], (array) config('lens-for-laravel.mcp.allowed_origins', []));
        if (! in_array($this->origin($url), array_map($this->origin(...), $origins), true)) {
            throw new McpException('INVALID_TARGET', 'invalid_target');
        }

        return $url;
    }

    public function origin(string $url): string
    {
        $p = parse_url($url);

        return strtolower(($p['scheme'] ?? '').'://'.($p['host'] ?? '')).':'.($p['port'] ?? (($p['scheme'] ?? '') === 'https' ? 443 : 80));
    }
}
