<?php

namespace LensForLaravel\LensForLaravel\Exceptions;

use RuntimeException;
use Throwable;

class AiFixGenerationException extends RuntimeException
{
    public static function incomplete(?Throwable $previous = null): self
    {
        return new self(
            __('lens-for-laravel::messages.ai_fix.incomplete_response'),
            previous: $previous,
        );
    }

    public static function providerFailed(?Throwable $previous = null): self
    {
        return new self(
            __('lens-for-laravel::messages.ai_fix.generation_failed'),
            previous: $previous,
        );
    }
}
