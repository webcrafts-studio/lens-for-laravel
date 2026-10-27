<?php

namespace LensForLaravel\LensForLaravel\Mcp;

use Illuminate\Support\Str;
use LensForLaravel\LensForLaravel\Exceptions\McpException;
use LensForLaravel\LensForLaravel\Services\AiFixContextExtractor;
use LensForLaravel\LensForLaravel\Services\SourceFileResolver;
use LensForLaravel\LensForLaravel\Services\SourceFixApplier;

class SourceContext
{
    public function __construct(protected RunService $runs, protected RunStore $store, protected SourceFileResolver $sources, protected AiFixContextExtractor $extractor) {}

    public function get(string $id, string $ref): array
    {
        $issue = $this->runs->issue($id, $ref);
        if (! $issue['fileName'] || ! $issue['lineNumber']) {
            return ['issue' => $issue, 'source' => null, 'mapping' => 'unavailable'];
        }
        $source = $this->sources->resolve($issue['fileName']);
        if (filesize($source['path']) > 1048576) {
            throw new McpException('RESULT_LIMIT', 'result_limit');
        }
        $content = file_get_contents($source['path']);
        $hash = hash('sha256', $content);
        if (! hash_equals($issue['source_hash'] ?? '', $hash)) {
            throw new McpException('SOURCE_CHANGED', 'source_changed');
        }
        $context = $this->extractor->extract($content, $issue['lineNumber'], $issue['htmlSnippet']);
        if (strlen($context->code) > 6000) {
            throw new McpException('RESULT_LIMIT', 'result_limit');
        }
        $lines = explode("\n", $content);
        $lineOffset = strlen(implode("\n", array_slice($lines, 0, $context->startLine - 1))) + ($context->startLine > 1 ? 1 : 0);
        $offset = strpos($content, $context->code, $lineOffset);
        if ($offset === false) {
            throw new McpException('SOURCE_CHANGED', 'source_changed');
        }

        return [
            'issue' => $issue, 'mapping' => 'heuristic',
            'source' => ['file' => $issue['fileName'], 'path' => $source['relative_path'], 'type' => $source['type'],
                'hash' => $hash, 'start_line' => $context->startLine, 'offset' => $offset, 'scope' => $context->scope, 'code' => $context->code],
        ];
    }

    public function record(string $id, string $ref): array
    {
        $issue = $this->runs->issue($id, $ref);
        $source = $this->sources->resolve($issue['fileName'] ?? '');
        $hash = hash_file('sha256', $source['path']);
        if ($hash === $issue['source_hash']) {
            throw new McpException('SOURCE_UNCHANGED', 'source_unchanged');
        }
        $this->sources->invalidate($source);
        $this->store->update($id, function ($run) use ($ref, $hash) {
            $run['changes'][$ref] = ['hash' => $hash, 'changed_at' => microtime(true), 'status' => 'pending_verification'];

            return $run;
        });

        return ['issue_ref' => $ref, 'status' => 'pending_verification', 'hash' => $hash];
    }

    public function prepare(string $id, string $ref, string $replacement): array
    {
        $this->ensureWrites();
        $context = $this->get($id, $ref);
        if (! $context['source'] || trim($replacement) === '' || strlen($replacement) > 12000) {
            throw new McpException('INVALID_PATCH', 'invalid_patch');
        }
        $patchId = (string) Str::uuid();
        $this->store->update($id, function ($run) use ($patchId, $ref, $context, $replacement) {
            if (count($run['patches'] ?? []) >= 100) {
                throw new McpException('RESULT_LIMIT', 'result_limit');
            }
            $run['patches'][$patchId] = ['issue_ref' => $ref, 'source' => $context['source'], 'replacement' => $replacement,
                'expires_at' => time() + 600, 'applied' => false];

            return $run;
        });

        return ['patch_id' => $patchId, 'path' => $context['source']['path'], 'original' => $context['source']['code'], 'replacement' => $replacement,
            'source_hash' => $context['source']['hash'], 'status' => 'prepared'];
    }

    public function apply(string $id, string $patchId): array
    {
        $this->ensureWrites();
        // The run lock also makes retrying the same patch idempotent.
        $result = null;
        $this->store->update($id, function ($run) use ($patchId, &$result) {
            $patch = $run['patches'][$patchId] ?? null;
            if (! $patch) {
                throw new McpException('PATCH_NOT_FOUND', 'invalid_patch');
            }
            if ($patch['applied']) {
                $result = ['patch_id' => $patchId, 'status' => 'pending_verification', 'already_applied' => true];

                return $run;
            }
            if ($patch['expires_at'] < time()) {
                throw new McpException('PATCH_EXPIRED', 'invalid_patch');
            }
            $s = $patch['source'];
            $hash = app(SourceFixApplier::class)->apply($s['file'], $s['code'], $patch['replacement'], $s['hash'], $s['offset']);
            $run['patches'][$patchId]['applied'] = true;
            $run['changes'][$patch['issue_ref']] = ['hash' => $hash, 'changed_at' => microtime(true), 'status' => 'pending_verification'];
            $result = ['patch_id' => $patchId, 'status' => 'pending_verification', 'hash' => $hash, 'already_applied' => false];

            return $run;
        });

        return $result;
    }

    private function ensureWrites(): void
    {
        if (! config('lens-for-laravel.mcp.allow_writes', false)) {
            throw new McpException('WRITES_DISABLED', 'writes_disabled');
        }
    }
}
