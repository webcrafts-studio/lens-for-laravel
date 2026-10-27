<?php

namespace LensForLaravel\LensForLaravel\Mcp;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;
use LensForLaravel\LensForLaravel\Exceptions\McpException;
use LensForLaravel\LensForLaravel\Services\McpAvailability;

class RunSummaryResource extends Resource implements HasUriTemplate
{
    protected string $name = 'lens_run_summary';

    protected string $mimeType = 'application/json';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('lens://runs/{run_id}/summary');
    }

    public function description(): string
    {
        return __('lens-for-laravel::messages.mcp.tools.lens_get_run');
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        try {
            app(McpAvailability::class)->ensureAvailable();

            return Response::json(app(RunService::class)->get((string) $request->get('run_id')));
        } catch (McpException $e) {
            return Response::error($e->getMessage());
        }
    }
}
