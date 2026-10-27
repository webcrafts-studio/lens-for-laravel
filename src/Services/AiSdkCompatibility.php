<?php

namespace LensForLaravel\LensForLaravel\Services;

use Composer\InstalledVersions;

class AiSdkCompatibility
{
    public const CONSTRAINT = '>=0.3.2 <0.12.0 || ^1.0';

    public function version(): ?string
    {
        return InstalledVersions::isInstalled('laravel/ai')
            ? InstalledVersions::getVersion('laravel/ai')
            : null;
    }

    public function supported(): bool
    {
        $version = $this->version();

        return $version !== null && (
            (version_compare($version, '0.3.2', '>=') && version_compare($version, '0.12.0', '<'))
            || (version_compare($version, '1.0.0', '>=') && version_compare($version, '2.0.0', '<'))
        );
    }

    public function geminiOptions(): array
    {
        $version = $this->version() ?? '0';

        return match (true) {
            version_compare($version, '1.0.0', '>=') => ['thinking_level' => 'low'],
            version_compare($version, '0.4.5', '>=') => ['thinkingConfig' => ['thinkingBudget' => 1024]],
            default => ['thinkingBudget' => 1024],
        };
    }

    public function dependenciesAvailable(): bool
    {
        // Early SDKs allow Prism 0.99.0, which lacks an interface their own
        // structured response implementation requires. Fail before loading it.
        return version_compare($this->version() ?? '0', '0.6.1', '>=')
            || interface_exists('Prism\\Prism\\Contracts\\HasSchemaType');
    }

    /**
     * Keep each SDK's token semantics: 1.x totals include cache and reasoning tokens.
     *
     * @return array<string, int|null>
     */
    public function usage(object $usage): array
    {
        $tokens = property_exists($usage, 'inputTokens')
            ? ['input_tokens' => $usage->inputTokens, 'output_tokens' => $usage->outputTokens ?? null]
            : ['prompt_tokens' => $usage->promptTokens ?? null, 'completion_tokens' => $usage->completionTokens ?? null];

        return [...$tokens,
            'reasoning_tokens' => $usage->reasoningTokens ?? null,
            'cache_read_input_tokens' => $usage->cacheReadInputTokens ?? null,
            'cache_write_input_tokens' => $usage->cacheWriteInputTokens ?? null,
        ];
    }
}
