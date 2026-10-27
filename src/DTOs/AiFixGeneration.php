<?php

namespace LensForLaravel\LensForLaravel\DTOs;

final readonly class AiFixGeneration
{
    /**
     * @param  array<string, int|null>  $usage
     */
    public function __construct(
        public string $replacement,
        public string $explanation,
        public ?string $provider = null,
        public ?string $model = null,
        public ?string $finishReason = null,
        public array $usage = [],
    ) {}
}
