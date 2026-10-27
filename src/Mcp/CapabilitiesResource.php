<?php

namespace LensForLaravel\LensForLaravel\Mcp;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Resource;
use LensForLaravel\LensForLaravel\Exceptions\McpException;
use LensForLaravel\LensForLaravel\Services\McpAvailability;

class CapabilitiesResource extends Resource
{
    protected string $name = 'lens_capabilities';

    protected string $uri = 'lens://capabilities';

    protected string $mimeType = 'application/json';

    public function description(): string
    {
        return __('lens-for-laravel::messages.mcp.tools.lens_status');
    }

    public function handle(): Response|ResponseFactory
    {
        try {
            app(McpAvailability::class)->ensureAvailable();

            return Response::json(app(ToolActions::class)->status());
        } catch (McpException $e) {
            return Response::error($e->getMessage());
        }
    }
}
