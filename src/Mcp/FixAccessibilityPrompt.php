<?php

namespace LensForLaravel\LensForLaravel\Mcp;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use LensForLaravel\LensForLaravel\Exceptions\McpException;
use LensForLaravel\LensForLaravel\Services\McpAvailability;

class FixAccessibilityPrompt extends Prompt
{
    protected string $name = 'lens_accessibility_fix';

    public function description(): string
    {
        return __('lens-for-laravel::messages.mcp.tools.lens_verify');
    }

    public function handle(): Response
    {
        try {
            app(McpAvailability::class)->ensureAvailable();

            return Response::text(LensServer::WORKFLOW);
        } catch (McpException $e) {
            return Response::error($e->getMessage());
        }
    }
}
