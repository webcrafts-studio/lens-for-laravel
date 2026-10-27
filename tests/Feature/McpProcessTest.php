<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Mcp\Server;
use LensForLaravel\LensForLaravel\Mcp\RunStore;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

beforeEach(function () {
    if (! class_exists(Server::class)) {
        $this->markTestSkipped('The optional laravel/mcp development dependency is not installed.');
    }
    $this->mcpDirectory = sys_get_temp_dir().'/lens-mcp-process-'.Str::uuid();
    mkdir($this->mcpDirectory, 0700, true);
});

afterEach(function () {
    if (isset($this->mcpDirectory)) {
        File::deleteDirectory($this->mcpDirectory);
    }
});

test('MCP stdio stays responsive during real worker processes and cancels long scans', function () {
    $root = $this->mcpDirectory.'/consumer';
    mkdir($root);
    file_put_contents($root.'/artisan', '<?php require '.var_export(dirname(__DIR__).'/Fixtures/mcp-artisan.php', true).';');
    $input = new InputStream;
    $process = new Process([PHP_BINARY, $root.'/artisan', 'lens:mcp', '--enabled'], null, ['LENS_MCP_TEST_ROOT' => $root]);
    $process->setInput($input);
    $process->setTimeout(20);
    $process->start();
    $buffer = '';
    $messages = [];
    $request = function (int $id, string $method, array $params = []) use ($input, $process, &$buffer, &$messages) {
        $input->write(json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params])."\n");
        $deadline = microtime(true) + 8;
        do {
            $buffer .= $process->getIncrementalOutput();
            while (($end = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $end);
                $buffer = substr($buffer, $end + 1);
                try {
                    $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new RuntimeException('Unexpected stdout: '.$process->getOutput().' STDERR: '.$process->getErrorOutput(), 0, $e);
                }
                $messages[$decoded['id']] = $decoded;
            }
            if (isset($messages[$id])) {
                return $messages[$id];
            }
            if (! $process->isRunning()) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        throw new RuntimeException('MCP subprocess did not answer: '.$process->getErrorOutput().$buffer);
    };
    try {
        $hello = $request(1, 'initialize', ['protocolVersion' => '2025-11-25', 'clientInfo' => ['name' => 'stdio-test', 'version' => '1'], 'capabilities' => (object) []]);
        expect($hello['result']['serverInfo']['name'])->toBe('Lens for Laravel');
        $started = $request(2, 'tools/call', ['name' => 'lens_scan', 'arguments' => ['url' => '/slow', 'save_history' => false]]);
        expect($started['result']['isError'])->toBeFalse();
        $id = $started['result']['structuredContent']['run_id'];
        $quick = microtime(true);
        $status = $request(3, 'tools/call', ['name' => 'lens_get_run', 'arguments' => ['run_id' => $id]]);
        expect(microtime(true) - $quick)->toBeLessThan(1.5);
        $counter = 4;
        while (! in_array($status['result']['structuredContent']['status'], RunStore::TERMINAL, true) && $counter < 30) {
            usleep(100000);
            $status = $request($counter++, 'tools/call', ['name' => 'lens_get_run', 'arguments' => ['run_id' => $id]]);
        }
        expect($status['result']['structuredContent']['status'])->toBe('completed')
            ->and($status['result']['structuredContent']['issue_count'])->toBe(1);
        $started = $request(40, 'tools/call', ['name' => 'lens_scan', 'arguments' => ['url' => '/slow', 'save_history' => false]]);
        $id = $started['result']['structuredContent']['run_id'];
        $cancelled = $request(41, 'tools/call', ['name' => 'lens_cancel_run', 'arguments' => ['run_id' => $id]]);
        expect($cancelled['result']['structuredContent']['status'])->toBe('cancelled');
        $input->close();
        $process->wait();
        expect($process->getExitCode())->toBe(0)->and($process->getErrorOutput())->toBe('');
    } finally {
        $input->close();
        $process->stop(0.5);
    }
});
