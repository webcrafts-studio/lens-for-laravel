<?php

namespace LensForLaravel\LensForLaravel\DTOs;

final readonly class AiFixCodeContext
{
    public function __construct(
        public string $code,
        public int $startLine,
        public string $scope,
    ) {}
}
