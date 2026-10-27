<?php

namespace LensForLaravel\LensForLaravel\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LensForLaravel\LensForLaravel\Exceptions\McpException;
use LensForLaravel\LensForLaravel\Models\Scan;

class HistoryRepository
{
    public function available(): bool
    {
        try {
            return Schema::hasTable('lens_scans') && Schema::hasTable('lens_scan_issues');
        } catch (\Throwable) {
            return false;
        }
    }

    public function save(array $run): void
    {
        try {
            if (! $this->available() || ! Schema::hasColumn('lens_scans', 'run_id')) {
                throw new McpException('HISTORY_UNAVAILABLE', 'history_unavailable');
            }
            $scan = DB::transaction(function () use ($run) {
                $scan = Scan::firstOrCreate(['run_id' => $run['run_id']], [
                    'url' => $run['request']['urls'][0], 'scan_mode' => $run['request']['mode'],
                    'wcag_version' => $run['request']['wcag'], 'urls_scanned' => $run['urls_scanned'],
                    'total_issues' => count($run['issues']),
                    'level_a_count' => count(array_filter($run['issues'], fn ($i) => $i['level'] === 'a')),
                    'level_aa_count' => count(array_filter($run['issues'], fn ($i) => $i['level'] === 'aa')),
                    'level_aaa_count' => count(array_filter($run['issues'], fn ($i) => $i['level'] === 'aaa')),
                ]);
                if ($scan->wasRecentlyCreated) {
                    foreach ($run['issues'] as $issue) {
                        $scan->issues()->create([
                            'rule_id' => $issue['id'], 'impact' => $issue['impact'], 'description' => $issue['description'],
                            'help_url' => $issue['helpUrl'], 'html_snippet' => $issue['htmlSnippet'], 'selector' => $issue['selector'],
                            'tags' => $issue['tags'], 'url' => $issue['url'], 'state_label' => $issue['stateLabel'],
                            'file_name' => $issue['fileName'], 'line_number' => $issue['lineNumber'], 'source_type' => $issue['sourceType'],
                        ]);
                    }
                }

                return $scan;
            });
            app(RunStore::class)->update($run['run_id'], function ($run) use ($scan) {
                $run['history_id'] = $scan->id;

                return $run;
            });
        } catch (\Throwable) {
            app(RunStore::class)->update($run['run_id'], function ($run) {
                $run['history_warning'] = __('lens-for-laravel::messages.mcp.history_unavailable');

                return $run;
            });
        }
    }

    public function read(array $input): array
    {
        if (! $this->available()) {
            throw new McpException('HISTORY_UNAVAILABLE', 'history_unavailable');
        }
        if (($input['action'] ?? 'list') === 'show') {
            $scan = Scan::find($input['scan_id'] ?? 0);
            if (! $scan) {
                throw new McpException('SCAN_NOT_FOUND', 'run_missing');
            }
            $page = (int) ($input['page'] ?? 1);
            $perPage = (int) ($input['per_page'] ?? 25);

            return ['scan' => $scan->toArray(), 'issues' => $scan->issues()->paginate($perPage, ['*'], 'page', $page)->toArray()];
        }

        return ['scans' => Scan::latest()->paginate((int) ($input['per_page'] ?? 25), ['*'], 'page', (int) ($input['page'] ?? 1))->toArray()];
    }
}
