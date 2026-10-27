<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Ai;
use LensForLaravel\LensForLaravel\Exceptions\AiFixGenerationException;
use LensForLaravel\LensForLaravel\Services\AiFixAvailability;
use LensForLaravel\LensForLaravel\Services\AiFixer;
use LensForLaravel\LensForLaravel\Services\AiFixPromptRunner;
use LensForLaravel\LensForLaravel\Services\AiSdkCompatibility;

$app = require __DIR__.'/bootstrap.php';
if (getenv('LENS_COMPAT_EXPECT_AI_UNAVAILABLE')) {
    lensCheck(! app(AiFixAvailability::class)->available(), 'Broken upstream dependency set was accepted');
    $response = app(Kernel::class)->handle(Request::create('/lens-for-laravel/fix/suggest', 'POST'));
    lensCheck($response->getStatusCode() === 503, 'Unsupported dependencies did not return a clear 503');
    lensCheck(! str_contains($response->getContent(), 'HasSchemaType'), 'Unsupported SDK exposed dependency internals');
    echo json_encode(['available' => false, 'checks' => $GLOBALS['lens_checks']], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
lensCheck(app(AiFixAvailability::class)->available(), 'Real AI SDK is unavailable: '.app(AiFixAvailability::class)->message());
$version = app(AiSdkCompatibility::class)->version();
$providers = ['openai', 'anthropic', 'gemini', 'ollama', 'openrouter', 'xai', 'deepseek', 'mistral'];
foreach ($providers as $provider) {
    config(['ai.providers.'.$provider.'.key' => 'lens-fixture-key']);
}

function lensAiPayload(string $provider, array $body, string $text, string $reason = 'stop'): array
{
    $structured = json_decode($text, true) ?? [];
    $payload = [
        'id' => 'fixture-response', 'model' => $body['model'] ?? 'fixture-model', 'created' => 1,
        'object' => 'chat.completion', 'status' => $reason === 'length' ? 'incomplete' : 'completed',
        'incomplete_details' => $reason === 'length' ? ['reason' => 'max_output_tokens'] : null,
        'output' => [['type' => 'message', 'role' => 'assistant', 'status' => $reason === 'length' ? 'incomplete' : 'completed', 'content' => [['type' => 'output_text', 'text' => $text, 'annotations' => []]]]],
        'choices' => [['index' => 0, 'finish_reason' => $reason, 'message' => ['role' => 'assistant', 'content' => $text]]],
        'usage' => ['input_tokens' => 100, 'output_tokens' => 25, 'prompt_tokens' => 100, 'completion_tokens' => 25,
            'input_tokens_details' => ['cached_tokens' => 10], 'output_tokens_details' => ['reasoning_tokens' => 5],
            'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 10,
            'total_input_tokens' => 100, 'total_output_tokens' => 25, 'total_thought_tokens' => 5],
        'candidates' => [['finishReason' => $reason === 'length' ? 'MAX_TOKENS' : 'STOP', 'content' => ['role' => 'model', 'parts' => [['text' => $text]]]]],
        'usageMetadata' => ['promptTokenCount' => 100, 'candidatesTokenCount' => 25, 'totalTokenCount' => 130, 'thoughtsTokenCount' => 5],
        'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $text]]]],
        'message' => ['role' => 'assistant', 'content' => $text], 'done' => true, 'done_reason' => $reason,
        'prompt_eval_count' => 100, 'eval_count' => 25,
    ];
    if ($provider === 'anthropic') {
        $toolName = $body['tools'][0]['name'] ?? null;
        $payload['type'] = 'message';
        $payload['stop_reason'] = $reason === 'length' ? 'max_tokens' : ($toolName ? 'tool_use' : 'end_turn');
        $payload['role'] = 'assistant';
        $payload['content'] = $toolName
            ? [['type' => 'tool_use', 'id' => 'fixture-tool', 'name' => $toolName, 'input' => $structured]]
            : [['type' => 'text', 'text' => $text]];
    }

    return $payload;
}

$replacement = '<img src="logo.png" alt="Logo">';
$valid = json_encode(['replacement' => $replacement, 'explanation' => 'Adds alternative text.']);
$provider = 'openai';
$responses = [];
$requests = [];
Http::preventStrayRequests();
Http::fake(function ($request) use (&$provider, &$responses, &$requests, $valid) {
    $body = $request->data();
    $requests[] = ['url' => $request->url(), 'body' => $body];
    $next = array_shift($responses) ?? [$valid, 'stop'];
    if (is_int($next)) {
        return Http::response(['error' => ['message' => 'SECRET_PROVIDER_DETAIL', 'type' => 'invalid_request_error', 'code' => 'invalid_api_key']], $next);
    }

    return Http::response(lensAiPayload($provider, $body, $next[0], $next[1]), 200);
});

foreach ($providers as $provider) {
    $requests = [];
    $result = app(AiFixPromptRunner::class)->generate('Fix <img src="logo.png">', $provider);
    lensCheck($result->replacement === $replacement, $provider.': wrong replacement');
    lensCheck($result->provider === $provider && $result->model !== null, $provider.': missing resolved provider/model');
    lensCheck(count($requests) === 1, $provider.': unexpected SDK retries');
    $request = $requests[0]['body'];
    $defaultModel = Ai::textProvider($provider)->defaultTextModel();
    lensCheck(($request['model'] ?? null) === $defaultModel || str_contains(urldecode($requests[0]['url']), $defaultModel), $provider.': SDK default model was overridden');
    $tokenLimit = $request['max_output_tokens'] ?? $request['max_completion_tokens'] ?? $request['max_tokens']
        ?? $request['generation_config']['max_output_tokens'] ?? $request['generationConfig']['maxOutputTokens'] ?? $request['options']['num_predict'] ?? null;
    lensCheck($tokenLimit === 12000, $provider.': output token ceiling missing: '.json_encode($request));
    if ($provider === 'gemini') {
        $expected = app(AiSdkCompatibility::class)->geminiOptions();
        $config = $request['generation_config'] ?? $request['generationConfig'] ?? [];
        // Prism maps the old flat option to the nested Google API shape.
        if (version_compare($version, '0.4.5', '<')) {
            $expected = ['thinkingConfig' => ['thinkingBudget' => 1024]];
        }
        foreach ($expected as $key => $value) {
            lensCheck(($config[$key] ?? null) === $value, 'Gemini options not sent correctly: '.json_encode($config));
        }
    }
    lensCheck(! array_key_exists('temperature', $request), $provider.': temperature was pinned');
    lensCheck(array_sum(array_filter($result->usage, 'is_int')) > 0, $provider.': token diagnostics missing');
    $requests = [];
    app(AiFixPromptRunner::class)->generate('Fix <img src="logo.png">', $provider, 'fixture-manual-model');
    lensCheck(count($requests) === 1 && (($requests[0]['body']['model'] ?? '') === 'fixture-manual-model' || str_contains(urldecode($requests[0]['url']), 'fixture-manual-model')), $provider.': explicit model override lost');
}

// Exercise the real SDK boundary plus Lens retry orchestration, without writing a suggestion.
$provider = 'openai';
$file = resource_path('views/ai-fixture.blade.php');
file_put_contents($file, '<img src="logo.png">');
config(['lens-for-laravel.ai_provider' => $provider]);
foreach (['en', 'pl', 'es', 'fr', 'de'] as $locale) {
    app()->setLocale($locale);
    foreach (['{"replacement":"incomplete"}', '{"replacement":', '', json_encode(['replacement' => "```html\n\n```", 'explanation' => 'Empty fence'])] as $invalid) {
        $requests = [];
        $responses = [[$invalid, 'stop'], [$valid, 'stop']];
        $result = app(AiFixer::class)->suggestFix('<img src="logo.png">', 'Image needs alt.', 'ai-fixture.blade.php', 1);
        lensCheck($result['fixedCode'] === $replacement && count($requests) === 2, $locale.': malformed output did not retry exactly once');
        lensCheck(file_get_contents($file) === '<img src="logo.png">', 'Suggestion changed source');
    }
}
foreach ([[$valid, 'length'], ['{"replacement":', 'length']] as $truncated) {
    $requests = [];
    $responses = [$truncated, [$valid, 'stop']];
    $result = app(AiFixer::class)->suggestFix('<img src="logo.png">', 'Image needs alt.', 'ai-fixture.blade.php', 1);
    lensCheck($result['fixedCode'] === $replacement && count($requests) === 2, 'Truncated response was accepted or failed without retry');
}
foreach ([401, 403] as $status) {
    $requests = [];
    $responses = [$status];
    try {
        app(AiFixer::class)->suggestFix('<img src="logo.png">', 'Image needs alt.', 'ai-fixture.blade.php', 1);
        throw new RuntimeException('Authentication failure was accepted');
    } catch (AiFixGenerationException $e) {
        lensCheck(count($requests) === 1 && ! str_contains($e->getMessage(), 'SECRET_PROVIDER_DETAIL'), 'Authentication retried or leaked provider details');
    }
}
$requests = [];
$responses = [['{}', 'stop'], ['{}', 'stop']];
try {
    app(AiFixer::class)->suggestFix('<img src="logo.png">', 'Image needs alt.', 'ai-fixture.blade.php', 1);
    throw new RuntimeException('Repeated invalid response was accepted');
} catch (AiFixGenerationException $e) {
    lensCheck(count($requests) === 2 && $e->getMessage() === __('lens-for-laravel::messages.ai_fix.incomplete_response'), 'Retry bound or safe message failed');
}
lensCheck(file_get_contents($file) === '<img src="logo.png">', 'Failure changed source');
$stressIterations = max(0, min(100, (int) getenv('LENS_AI_STRESS_ITERATIONS')));
$stressStart = microtime(true);
foreach ($providers as $provider) {
    config(['lens-for-laravel.ai_provider' => $provider]);
    for ($i = 0; $i < $stressIterations; $i++) {
        $requests = [];
        $responses = $i % 3 === 0 ? [['{}', 'stop'], [$valid, 'stop']] : [[$valid, 'stop']];
        $result = app(AiFixer::class)->suggestFix('<img src="logo.png">', 'Image needs alt.', 'ai-fixture.blade.php', 1);
        lensCheck($result['fixedCode'] === $replacement && count($requests) === ($i % 3 === 0 ? 2 : 1), $provider.': repeated fault/recovery failed');
        lensCheck(file_get_contents($file) === '<img src="logo.png">', 'Repeated suggestions changed source');
    }
}
echo json_encode(['php' => PHP_VERSION, 'laravel' => app()->version(), 'ai' => $version, 'providers' => count($providers), 'checks' => $GLOBALS['lens_checks'],
    'stress_generations' => count($providers) * $stressIterations, 'stress_seconds' => round(microtime(true) - $stressStart, 3), 'peak_memory_bytes' => memory_get_peak_usage(true)], JSON_THROW_ON_ERROR).PHP_EOL;
