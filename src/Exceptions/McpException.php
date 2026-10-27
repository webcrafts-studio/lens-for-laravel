<?php

namespace LensForLaravel\LensForLaravel\Exceptions;

use RuntimeException;

class McpException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $key, public readonly bool $retryable = false)
    {
        parent::__construct(__("lens-for-laravel::messages.mcp.{$key}"));
    }

    public function toArray(): array
    {
        return ['code' => $this->errorCode, 'message' => $this->getMessage(), 'retryable' => $this->retryable];
    }
}
