<?php

namespace LensForLaravel\LensForLaravel\Services;

use Laravel\Ai\Enums\Lab;
use LensForLaravel\LensForLaravel\Ai\AccessibilityFixAgent;
use LensForLaravel\LensForLaravel\DTOs\AiFixGeneration;
use RuntimeException;

class AiFixPromptRunner
{
    public function generate(string $prompt, string $provider): AiFixGeneration
    {
        $response = AccessibilityFixAgent::make()->prompt(
            $prompt,
            provider: $this->provider($provider),
        );

        $replacement = $response['replacement'] ?? null;
        $explanation = $response['explanation'] ?? null;

        if (! is_string($replacement) || trim($replacement) === '' || ! is_string($explanation) || trim($explanation) === '') {
            throw new RuntimeException(__('lens-for-laravel::messages.errors.ai_response_missing'));
        }

        $lastStep = $response->steps->last();

        return new AiFixGeneration(
            replacement: $this->unwrapCodeFence($replacement),
            explanation: trim($explanation),
            provider: $response->meta->provider ?? $provider,
            model: $response->meta->model ?? null,
            finishReason: $lastStep?->finishReason?->value,
            usage: [
                'prompt_tokens' => $response->usage->promptTokens,
                'completion_tokens' => $response->usage->completionTokens,
                'reasoning_tokens' => $response->usage->reasoningTokens,
                'cache_read_input_tokens' => $response->usage->cacheReadInputTokens,
                'cache_write_input_tokens' => $response->usage->cacheWriteInputTokens,
            ],
        );
    }

    protected function provider(string $provider): Lab
    {
        return match (strtolower($provider)) {
            'openai' => Lab::OpenAI,
            'anthropic' => Lab::Anthropic,
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
