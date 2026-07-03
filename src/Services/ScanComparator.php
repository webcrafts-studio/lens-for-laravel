<?php

namespace LensForLaravel\LensForLaravel\Services;

use Illuminate\Support\Collection;
use LensForLaravel\LensForLaravel\Models\Scan;
use LensForLaravel\LensForLaravel\Models\ScanIssue;
use LensForLaravel\LensForLaravel\Support\UrlNormalizer;

class ScanComparator
{
    /**
     * @return array{
     *     new: Collection<int, ScanIssue>,
     *     fixed: Collection<int, ScanIssue>,
     *     remaining: Collection<int, ScanIssue>
     * }
     */
    public function compare(Scan $base, Scan $compare): array
    {
        $baseKeys = $base->issues
            ->map(fn (ScanIssue $issue) => $this->issueKey($issue, $base->url))
            ->flip();
        $compareKeys = $compare->issues
            ->map(fn (ScanIssue $issue) => $this->issueKey($issue, $compare->url))
            ->flip();

        return [
            'new' => $compare->issues
                ->reject(fn (ScanIssue $issue) => $baseKeys->has($this->issueKey($issue, $compare->url)))
                ->values(),
            'fixed' => $base->issues
                ->reject(fn (ScanIssue $issue) => $compareKeys->has($this->issueKey($issue, $base->url)))
                ->values(),
            'remaining' => $base->issues
                ->filter(fn (ScanIssue $issue) => $compareKeys->has($this->issueKey($issue, $base->url)))
                ->values(),
        ];
    }

    public function issueKey(ScanIssue $issue, ?string $fallbackUrl = null): string
    {
        return json_encode([
            'rule_id' => $issue->rule_id,
            'url' => UrlNormalizer::pathAndQuery($issue->url ?: $fallbackUrl),
            'state_label' => $issue->state_label,
            'selector' => trim((string) $issue->selector),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
