<?php

use LensForLaravel\LensForLaravel\Exceptions\AiFixGenerationException;
use LensForLaravel\LensForLaravel\Services\AiFixer;
use Symfony\Component\Process\Process;

$app = require __DIR__.'/bootstrap.php';
$mode = $argv[1] ?? 'parent';
if ($mode !== 'parent') {
    config(['ai.providers.ollama.url' => getenv('LENS_AI_NETWORK_URL').'/'.$mode,
        'lens-for-laravel.ai_provider' => 'ollama', 'lens-for-laravel.ai_ollama_timeout' => 1]);
    $file = 'network-'.getmypid().'.blade.php';
    file_put_contents(resource_path('views/'.$file), '<img src="logo.png">');
    $start = microtime(true);
    try {
        $result = app(AiFixer::class)->suggestFix('<img src="logo.png">', 'Image needs alt.', $file, 1);
        lensCheck(in_array($mode, ['normal', 'invalid-once', 'length-once'], true), 'Provider failure unexpectedly succeeded');
        lensCheck($result['fixedCode'] === '<img src="logo.png" alt="Logo">', 'Network suggestion failed');
    } catch (AiFixGenerationException $e) {
        lensCheck(! in_array($mode, ['normal', 'invalid-once', 'length-once'], true), 'Network suggestion failed: '.$e->getMessage().' Previous: '.$e->getPrevious()?->getMessage());
        lensCheck(! str_contains($e->getMessage(), 'SECRET_PROVIDER_DETAIL'), 'Provider details leaked');
    }
    lensCheck(file_get_contents(resource_path('views/'.$file)) === '<img src="logo.png">', 'Network request modified source');
    lensCheck(microtime(true) - $start < 8, 'Provider failure exceeded bounded timeout');
    echo json_encode(['mode' => $mode, 'seconds' => round(microtime(true) - $start, 3)], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}

$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (! $socket) {
    throw new RuntimeException($error);
}
$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$url = 'http://127.0.0.1:'.$port;
foreach (glob(base_path('network-*.count')) as $file) {
    unlink($file);
}
$server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, dirname(__DIR__).'/Fixtures/ai-provider-router.php'], getcwd(), ['LENS_AI_NETWORK_ROOT' => getcwd()], timeout: 60);
$server->disableOutput()->start();
$children = [];
try {
    $ready = false;
    for ($i = 0; $i < 100; $i++) {
        if ($connection = @fsockopen('127.0.0.1', $port)) {
            fclose($connection);
            $ready = true;
            break;
        }
        usleep(10000);
    }
    lensCheck($ready, 'Local provider did not start');
    $evidence = [];
    foreach (['normal' => 1, 'invalid-once' => 2, 'length-once' => 2, 'invalid' => 2, 'unauthorized' => 1, 'limited' => 1, 'unavailable' => 1] as $scenario => $expected) {
        $child = new Process([PHP_BINARY, __FILE__, $scenario], getcwd(), ['LENS_AI_NETWORK_URL' => $url], timeout: 15);
        $child->run();
        lensCheck($child->isSuccessful(), $scenario.': '.$child->getErrorOutput().$child->getOutput());
        lensCheck((int) file_get_contents(base_path('network-'.$scenario.'.count')) === $expected, $scenario.': unexpected HTTP retries');
        $evidence[] = json_decode(trim($child->getOutput()), true, 512, JSON_THROW_ON_ERROR);
    }
    for ($i = 0; $i < 24; $i++) {
        $child = new Process([PHP_BINARY, __FILE__, 'normal'], getcwd(), ['LENS_AI_NETWORK_URL' => $url], timeout: 20);
        $child->start();
        $children[] = $child;
    }
    foreach ($children as $child) {
        $child->wait();
        lensCheck($child->isSuccessful(), 'Concurrent provider request failed: '.$child->getErrorOutput().$child->getOutput());
    }
    lensCheck((int) file_get_contents(base_path('network-normal.count')) === 25, 'Concurrent provider requests were lost or duplicated');
    // Run last: the single-process HTTP fixture remains busy after the client times out.
    $child = new Process([PHP_BINARY, __FILE__, 'timeout'], getcwd(), ['LENS_AI_NETWORK_URL' => $url], timeout: 15);
    $child->run();
    lensCheck($child->isSuccessful(), 'Timeout handling failed: '.$child->getErrorOutput().$child->getOutput());
    $evidence[] = json_decode(trim($child->getOutput()), true, 512, JSON_THROW_ON_ERROR);
    echo json_encode(['checks' => $GLOBALS['lens_checks'], 'concurrent_clients' => 24, 'scenarios' => $evidence], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    foreach ($children as $child) {
        $child->stop(0.1);
    }
    $server->stop(0.1);
}
