<?php

namespace LensForLaravel\LensForLaravel\Exceptions;

use RuntimeException;

class AiFixResponseException extends RuntimeException
{
    /** @param array<string, int|null> $usage */
    public function __construct(
        public readonly string $provider,
        public readonly ?string $model,
        public readonly ?string $finishReason,
        public readonly array $usage,
        public readonly bool $retryable = true,
    ) {
        parent::__construct('The AI response did not contain a complete, valid suggestion.');
    }
}
