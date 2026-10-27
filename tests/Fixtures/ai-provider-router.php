<?php

$root = getenv('LENS_AI_NETWORK_ROOT');
$mode = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'))[0];
$counter = fopen($root.'/network-'.$mode.'.count', 'c+');
flock($counter, LOCK_EX);
$count = (int) stream_get_contents($counter) + 1;
rewind($counter);
ftruncate($counter, 0);
fwrite($counter, (string) $count);
flock($counter, LOCK_UN);
fclose($counter);

if ($mode === 'timeout') {
    usleep(2500000);
}
header('Content-Type: application/json');
if (in_array($mode, ['unauthorized', 'limited', 'unavailable'], true)) {
    http_response_code(match ($mode) {
        'unauthorized' => 401, 'limited' => 429, default => 503,
    });
    echo json_encode(['error' => 'SECRET_PROVIDER_DETAIL']);

    return;
}
$text = json_encode(['replacement' => '<img src="logo.png" alt="Logo">', 'explanation' => 'Adds alt.']);
if ($mode === 'invalid' || ($mode === 'invalid-once' && $count === 1)) {
    $text = '{"replacement":';
}
echo json_encode(['model' => 'fixture-model', 'message' => ['role' => 'assistant', 'content' => $text], 'done' => true,
    'done_reason' => $mode === 'length-once' && $count === 1 ? 'length' : 'stop', 'prompt_eval_count' => 100, 'eval_count' => 20]);
