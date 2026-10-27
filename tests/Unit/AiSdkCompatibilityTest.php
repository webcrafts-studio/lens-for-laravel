<?php

use LensForLaravel\LensForLaravel\Services\AiSdkCompatibility;

function aiSdkCompatibility(?string $version): AiSdkCompatibility
{
    return new class($version) extends AiSdkCompatibility
    {
        public function __construct(private readonly ?string $fakeVersion) {}

        public function version(): ?string
        {
            return $this->fakeVersion;
        }
    };
}

test('AI SDK compatibility accepts only the supported release lines', function (?string $version, bool $supported) {
    expect(aiSdkCompatibility($version)->supported())->toBe($supported);
})->with([
    [null, false], ['0.3.1', false], ['0.3.2.0', true], ['0.4.5', true], ['0.6.0', true],
    ['0.6.1', true], ['0.11.3', true], ['0.12.0', false], ['0.99.0', false],
    ['1.0.0.0', true], ['1.3.0', true], ['1.99.0', true], ['2.0.0', false],
]);

test('Gemini receives the options expected by its SDK generation', function (string $version, array $options) {
    expect(aiSdkCompatibility($version)->geminiOptions())->toBe($options);
})->with([
    ['0.3.2', ['thinkingBudget' => 1024]],
    ['0.4.4', ['thinkingBudget' => 1024]],
    ['0.4.5', ['thinkingConfig' => ['thinkingBudget' => 1024]]],
    ['0.6.0', ['thinkingConfig' => ['thinkingBudget' => 1024]]],
    ['0.6.1', ['thinkingConfig' => ['thinkingBudget' => 1024]]],
    ['0.11.3', ['thinkingConfig' => ['thinkingBudget' => 1024]]],
    ['1.0.0', ['thinking_level' => 'low']],
    ['1.3.0', ['thinking_level' => 'low']],
]);

test('token diagnostics retain old SDK semantics and nullable new SDK counts', function () {
    $sdk = new AiSdkCompatibility;
    expect($sdk->usage((object) ['promptTokens' => 100, 'completionTokens' => 30, 'reasoningTokens' => 7]))
        ->toBe(['prompt_tokens' => 100, 'completion_tokens' => 30, 'reasoning_tokens' => 7, 'cache_read_input_tokens' => null, 'cache_write_input_tokens' => null])
        ->and($sdk->usage((object) ['inputTokens' => 150, 'outputTokens' => 37, 'cacheReadInputTokens' => 50]))
        ->toBe(['input_tokens' => 150, 'output_tokens' => 37, 'reasoning_tokens' => null, 'cache_read_input_tokens' => 50, 'cache_write_input_tokens' => null]);
});
