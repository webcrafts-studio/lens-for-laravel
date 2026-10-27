<?php

namespace LensForLaravel\LensForLaravel\Mcp;

use Illuminate\Support\Str;
use Laravel\Mcp\Server\Transport\StdioTransport;
use LensForLaravel\LensForLaravel\Exceptions\McpException;

class ManagedStdioTransport extends StdioTransport
{
    public function __construct()
    {
        // Laravel MCP 1.x removed protocol sessions from the stdio constructor.
        if (method_exists(StdioTransport::class, 'sessionId')) {
            parent::__construct((string) Str::uuid());
        } else {
            parent::__construct();
        }
    }

    public function run(): void
    {
        stream_set_blocking(STDIN, false);
        $buffer = '';
        while (! feof(STDIN)) {
            app(RunProcessManager::class)->tick();
            $read = [STDIN];
            $write = $except = [];
            if (@stream_select($read, $write, $except, 0, 200000) !== 1) {
                continue;
            }
            $chunk = fread(STDIN, 65536);
            if ($chunk === false) {
                break;
            }
            $buffer .= $chunk;
            while (($end = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $end);
                $buffer = substr($buffer, $end + 1);
                if (strlen($line) > 65536) {
                    throw new McpException('INPUT_LIMIT', 'result_limit');
                }
                // Accidental echo from application callbacks must not corrupt protocol output.
                ob_start();
                try {
                    ($this->handler)($line);
                } finally {
                    ob_end_clean();
                }
            }
            if (strlen($buffer) > 65536) {
                throw new McpException('INPUT_LIMIT', 'result_limit');
            }
        }
    }
}
