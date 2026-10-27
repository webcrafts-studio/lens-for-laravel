<?php

namespace LensForLaravel\LensForLaravel\Mcp;

class ToolCatalog
{
    public function definitions(): array
    {
        $string = ['type' => 'string', 'maxLength' => 2048];
        $ref = ['type' => 'string', 'format' => 'uuid'];
        $bool = ['type' => 'boolean'];
        $page = ['type' => 'integer', 'minimum' => 1];
        $paging = ['page' => $page, 'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50]];
        $run = ['run_id' => $ref];
        $issue = [...$run, 'issue_ref' => $ref];
        $enum = fn (array $values) => ['type' => 'string', 'enum' => $values];
        $definitions = [
            'lens_status' => ['properties' => [], 'required' => [], 'rules' => []],
            'lens_scan' => ['properties' => [
                'url' => [...$string, 'description' => 'Application URL or path; defaults to /.'],
                'urls' => ['type' => 'array', 'items' => $string, 'minItems' => 1, 'maxItems' => 50],
                'mode' => $enum(['single', 'multiple', 'website', 'states']),
                'wcag' => $enum(['2.0', '2.1', '2.2']), 'level' => [...$enum(['a', 'aa', 'all']), 'description' => 'aa includes A and AA; independent from WCAG version.'],
                'script' => ['type' => 'string', 'maxLength' => 10000], 'as_user_id' => ['type' => 'integer', 'minimum' => 1],
                'max_pages' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500],
                'async' => [...$bool, 'description' => 'Defaults to true. Poll lens_get_run until terminal.'],
                'save_history' => $bool, 'idempotency_key' => ['type' => 'string', 'maxLength' => 100],
            ], 'required' => [], 'rules' => []],
            'lens_get_run' => ['properties' => $run, 'required' => ['run_id'], 'rules' => ['run_id' => 'required|uuid']],
            'lens_cancel_run' => ['properties' => $run, 'required' => ['run_id'], 'rules' => ['run_id' => 'required|uuid']],
            'lens_list_issues' => ['properties' => [...$run, ...$paging, 'rule_id' => $string, 'impact' => $enum(['critical', 'serious', 'moderate', 'minor', 'unknown']), 'url' => $string, 'state' => $string, 'file' => $string, 'level' => $enum(['a', 'aa', 'aaa', 'other'])], 'required' => ['run_id'], 'rules' => ['run_id' => 'required|uuid', 'rule_id' => 'sometimes|string|max:255', 'impact' => 'sometimes|in:critical,serious,moderate,minor,unknown', 'url' => 'sometimes|string|max:2048', 'state' => 'sometimes|string|max:80', 'file' => 'sometimes|string|max:500', 'level' => 'sometimes|in:a,aa,aaa,other']],
            'lens_issue_context' => ['properties' => $issue, 'required' => ['run_id', 'issue_ref'], 'rules' => ['run_id' => 'required|uuid', 'issue_ref' => 'required|uuid']],
            'lens_record_change' => ['properties' => $issue, 'required' => ['run_id', 'issue_ref'], 'rules' => ['run_id' => 'required|uuid', 'issue_ref' => 'required|uuid']],
            'lens_verify' => ['properties' => [...$run, 'async' => $bool], 'required' => ['run_id'], 'rules' => ['run_id' => 'required|uuid', 'async' => 'sometimes|boolean']],
            'lens_compare' => ['properties' => ['base_run_id' => $ref, 'current_run_id' => $ref, ...$paging], 'required' => ['base_run_id', 'current_run_id'], 'rules' => ['base_run_id' => 'required|uuid', 'current_run_id' => 'required|uuid']],
            'lens_history' => ['properties' => ['action' => $enum(['list', 'show']), 'scan_id' => ['type' => 'integer', 'minimum' => 1], ...$paging], 'required' => [], 'rules' => ['action' => 'sometimes|in:list,show', 'scan_id' => 'required_if:action,show|integer|min:1']],
            'lens_preview' => ['properties' => $issue, 'required' => ['run_id', 'issue_ref'], 'rules' => ['run_id' => 'required|uuid', 'issue_ref' => 'required|uuid']],
            'lens_export' => ['properties' => [...$run, 'format' => $enum(['json', 'pdf'])], 'required' => ['run_id'], 'rules' => ['run_id' => 'required|uuid', 'format' => 'sometimes|in:json,pdf']],
            'lens_baseline' => ['properties' => [...$run, 'action' => $enum(['compare', 'save'])], 'required' => ['run_id'], 'rules' => ['run_id' => 'required|uuid', 'action' => 'sometimes|in:compare,save']],
            'lens_prepare_patch' => ['properties' => [...$issue, 'replacement' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 12000]], 'required' => ['run_id', 'issue_ref', 'replacement'], 'rules' => ['run_id' => 'required|uuid', 'issue_ref' => 'required|uuid', 'replacement' => 'required|string|max:12000']],
            'lens_apply_patch' => ['properties' => [...$run, 'patch_id' => $ref], 'required' => ['run_id', 'patch_id'], 'rules' => ['run_id' => 'required|uuid', 'patch_id' => 'required|uuid']],
        ];
        foreach ($definitions as $name => &$definition) {
            $definition['schema'] = ['type' => 'object', 'properties' => $definition['properties'] ?: (object) [], 'required' => $definition['required'], 'additionalProperties' => false];
            if (isset($definition['properties']['page'])) {
                $definition['rules'] += ['page' => 'sometimes|integer|min:1|max:100000', 'per_page' => 'sometimes|integer|min:1|max:50'];
            }
            $definition['read_only'] = in_array($name, ['lens_status', 'lens_get_run', 'lens_list_issues', 'lens_issue_context', 'lens_history', 'lens_compare'], true);
        }

        return $definitions;
    }
}
