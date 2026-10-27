<?php

namespace LensForLaravel\LensForLaravel\Services;

use Spatie\Browsershot\Browsershot;

class ReportExporter
{
    public function pdf(array $issues, string $url, string $wcag): string
    {
        $html = view('lens-for-laravel::report', ['issues' => $issues, 'url' => $url, 'wcagVersion' => $wcag, 'generatedAt' => now()])->render();

        return $this->browser($html)->noSandbox()->timeout((int) config('lens-for-laravel.mcp.browser_timeout', 120))->format('A4')->margins(0, 0, 0, 0)->pdf();
    }

    protected function browser(string $html): Browsershot
    {
        return Browsershot::html($html);
    }
}
