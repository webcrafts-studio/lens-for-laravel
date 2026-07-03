<?php

namespace LensForLaravel\LensForLaravel\Services;

use Illuminate\Support\Collection;
use LensForLaravel\LensForLaravel\DTOs\Issue;
use LensForLaravel\LensForLaravel\Support\UrlNormalizer;
use RuntimeException;

class BaselineManager
{
    public function defaultPath(): string
    {
        $path = (string) config(
            'lens-for-laravel.baseline_path',
            storage_path('app/lens-for-laravel/baseline.json')
        );

        return $this->isAbsolutePath($path) ? $path : base_path($path);
    }

    public function resolvePath(?string $path = null): string
    {
        if ($path === null || trim($path) === '') {
            return $this->defaultPath();
        }

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @param  Collection<int, Issue>  $issues
     */
    public function write(Collection $issues, string $path, array $metadata = []): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException(__('lens-for-laravel::messages.errors.baseline_directory', ['path' => $directory]));
        }

        $payload = [
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            ...$metadata,
            'issue_count' => $issues->count(),
            'issues' => $issues
                ->map(fn (Issue $issue) => $this->issueRecord($issue))
                ->values()
                ->all(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false || file_put_contents($path, $json.PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException(__('lens-for-laravel::messages.errors.baseline_write', ['path' => $path]));
        }
    }

    /**
     * @param  Collection<int, Issue>  $currentIssues
     * @return array{
     *     current: Collection<int, array<string, mixed>>,
     *     new: Collection<int, array<string, mixed>>,
     *     existing: Collection<int, array<string, mixed>>,
     *     fixed: Collection<int, array<string, mixed>>,
     *     baseline_count: int
     * }
     */
    public function compare(Collection $currentIssues, string $path): array
    {
        $baseline = $this->read($path);
        $baselineIssues = collect($baseline['issues'] ?? [])
            ->filter(fn ($issue) => is_array($issue) && isset($issue['fingerprint']))
            ->values();

        $current = $currentIssues
            ->map(fn (Issue $issue) => $this->issueRecord($issue))
            ->values();

        $baselineFingerprints = $baselineIssues->pluck('fingerprint')->flip();
        $currentFingerprints = $current->pluck('fingerprint')->flip();

        return [
            'current' => $current,
            'new' => $current
                ->reject(fn (array $issue) => $baselineFingerprints->has($issue['fingerprint']))
                ->values(),
            'existing' => $current
                ->filter(fn (array $issue) => $baselineFingerprints->has($issue['fingerprint']))
                ->values(),
            'fixed' => $baselineIssues
                ->reject(fn (array $issue) => $currentFingerprints->has($issue['fingerprint']))
                ->values(),
            'baseline_count' => $baselineIssues->count(),
        ];
    }

    public function fingerprint(Issue $issue): string
    {
        $json = json_encode($this->fingerprintParts($issue), JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException(__('lens-for-laravel::messages.errors.baseline_fingerprint'));
        }

        return sha1($json);
    }

    protected function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException(__('lens-for-laravel::messages.errors.baseline_missing', ['path' => $path]));
        }

        $contents = file_get_contents($path);
        $data = $contents === false ? null : json_decode($contents, true);

        if (! is_array($data)) {
            throw new RuntimeException(__('lens-for-laravel::messages.errors.baseline_invalid', ['path' => $path]));
        }

        return $data;
    }

    protected function issueRecord(Issue $issue): array
    {
        return [
            'fingerprint' => $this->fingerprint($issue),
            'rule_id' => $issue->id,
            'impact' => $issue->impact,
            'url' => UrlNormalizer::pathAndQuery($issue->url),
            'state_label' => $issue->stateLabel,
            'selector' => trim($issue->selector),
            'file_name' => $issue->fileName,
            'line_number' => $issue->lineNumber,
            'source_type' => $issue->sourceType,
        ];
    }

    protected function fingerprintParts(Issue $issue): array
    {
        return [
            'rule_id' => $issue->id,
            'url' => UrlNormalizer::pathAndQuery($issue->url),
            'state_label' => $issue->stateLabel,
            'selector' => trim($issue->selector),
            'file_name' => $issue->fileName,
            'source_type' => $issue->sourceType,
        ];
    }

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }
}
