<?php

namespace LensForLaravel\LensForLaravel\Mcp;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use LensForLaravel\LensForLaravel\Exceptions\McpException;
use LensForLaravel\LensForLaravel\Services\McpAvailability;

class LensTool extends Tool
{
    public function __construct(string $name, protected array $definition)
    {
        $this->name = $name;
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'description' => __('lens-for-laravel::messages.mcp.tools.'.$this->name),
            'inputSchema' => $this->definition['schema'],
            'annotations' => ['readOnlyHint' => $this->definition['read_only'],
                'destructiveHint' => in_array($this->name, ['lens_apply_patch', 'lens_baseline'], true),
                'openWorldHint' => in_array($this->name, ['lens_scan', 'lens_verify', 'lens_preview'], true)],
        ];
    }

    public function eligibleForRegistration(): bool
    {
        return ! in_array($this->name, ['lens_prepare_patch', 'lens_apply_patch'], true) || config('lens-for-laravel.mcp.allow_writes', false);
    }

    public function handle(Request $request): ResponseFactory
    {
        try {
            app(McpAvailability::class)->ensureAvailable();
            $arguments = $request->all();
            if (array_diff(array_keys($arguments), array_keys($this->definition['properties'])) !== []) {
                throw new McpException('INVALID_ARGUMENTS', 'invalid_arguments');
            }
            Validator::make($arguments, $this->definition['rules'])->validate();
            $data = app(ToolActions::class)->call($this->name, $arguments);
            $image = $data['_image'] ?? null;
            unset($data['_image']);
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            if (strlen($json) > (int) config('lens-for-laravel.mcp.max_output_bytes', 131072)) {
                throw new McpException('RESULT_LIMIT', 'result_limit');
            }
            $responses = [Response::text($json)];
            if ($image !== null) {
                $responses[] = Response::image($image);
            }

            return Response::make($responses)->withStructuredContent($data);
        } catch (ValidationException|\InvalidArgumentException $e) {
            $error = (new McpException('INVALID_ARGUMENTS', 'invalid_arguments'))->toArray();
        } catch (McpException $e) {
            $error = $e->toArray();
        } catch (\Throwable $e) {
            logger()->warning('Lens MCP tool failed', ['tool' => $this->name, 'exception' => $e::class]);
            $error = (new McpException('TOOL_FAILED', 'tool_failed'))->toArray();
        }

        return Response::make(Response::error(json_encode($error, JSON_THROW_ON_ERROR)))->withStructuredContent(['error' => $error]);
    }
}
