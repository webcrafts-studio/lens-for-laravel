<?php

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use LensForLaravel\LensForLaravel\Ai\AccessibilityFixAgent;
use LensForLaravel\LensForLaravel\Services\AiFixPromptRunner;

test('optional accessibility agent keeps cloud provider defaults and supports bounded Ollama generation', function () {
    if (! interface_exists(Agent::class)) {
        $this->markTestSkipped('The optional laravel/ai SDK is not installed in the core test matrix.');
    }

    $reflection = new ReflectionClass(AccessibilityFixAgent::class);
    $maxTokens = $reflection->getAttributes(MaxTokens::class);
    $temperature = $reflection->getAttributes(Temperature::class);
    $model = $reflection->getAttributes(Model::class);
    $agent = new AccessibilityFixAgent;
    $providerMethod = new ReflectionMethod(AiFixPromptRunner::class, 'provider');

    expect($maxTokens)->toHaveCount(1)
        ->and($maxTokens[0]->newInstance()->value)->toBe(12000)
        ->and($temperature)->toHaveCount(1)
        ->and($temperature[0]->newInstance()->value)->toBe(0.0)
        ->and($model)->toBeEmpty()
        ->and($agent->providerOptions(Lab::Gemini))->toBe(['thinkingBudget' => 1024])
        ->and($agent->providerOptions(Lab::OpenAI))->toBe([])
        ->and($agent->providerOptions(Lab::Anthropic))->toBe([])
        ->and($agent->providerOptions(Lab::Ollama))->toBe([])
        ->and($providerMethod->invoke(new AiFixPromptRunner, 'ollama'))->toBe(Lab::Ollama);
});
