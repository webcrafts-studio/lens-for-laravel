<?php

namespace LensForLaravel\LensForLaravel\Services;

use Laravel\Ai\Enums\Lab;
use LensForLaravel\LensForLaravel\Ai\AccessibilityFixAgent;
use LensForLaravel\LensForLaravel\DTOs\AiFixGeneration;
use LensForLaravel\LensForLaravel\Exceptions\AiFixResponseException;

class AiFixPromptRunner
{
    public function generate(string $prompt, string $provider, ?string $model = null, ?int $timeout = null): AiFixGeneration
    {
        $response = AccessibilityFixAgent::make()->prompt(
            $prompt,
            provider: $this->provider($provider),
            model: $model,
            timeout: $timeout,
        );

        $replacement = $response['replacement'] ?? null;
        if (is_string($replacement)) {
            $replacement = $this->unwrapCodeFence($replacement);
        }
        $explanation = $response['explanation'] ?? null;
        $finishReason = $response->steps->last()?->finishReason?->value;
        $usage = app(AiSdkCompatibility::class)->usage($response->usage);
        $resolvedProvider = $response->meta->provider ?? $provider;
        $resolvedModel = $response->meta->model ?? null;
        $retryable = ! in_array($finishReason, ['content_filter', 'error', 'cancelled'], true);

        // A complete-looking object can still contain a truncated or filtered replacement.
        if (! $retryable || $finishReason === 'length'
            || ! is_string($replacement) || trim($replacement) === '' || ! is_string($explanation) || trim($explanation) === '') {
            throw new AiFixResponseException($resolvedProvider, $resolvedModel, $finishReason, $usage, $retryable);
        }

        return new AiFixGeneration(
            replacement: $replacement,
            explanation: trim($explanation),
            provider: $resolvedProvider,
            model: $resolvedModel,
            finishReason: $finishReason,
            usage: $usage,
        );
    }

    protected function provider(string $provider): Lab
    {
        return match (strtolower($provider)) {
            'openai' => Lab::OpenAI,
            'anthropic' => Lab::Anthropic,
            'ollama' => Lab::Ollama,
            'openrouter' => Lab::OpenRouter,
            'xai' => Lab::xAI,
            'deepseek' => Lab::DeepSeek,
            'mistral' => Lab::Mistral,
            default => Lab::Gemini,
        };
    }

    protected function unwrapCodeFence(string $replacement): string
    {
        $trimmed = trim($replacement);

        if (preg_match('/^```[^\r\n]*\R(.*)\R```$/s', $trimmed, $matches)) {
            return $matches[1];
        }

        return $replacement;
    }
}
