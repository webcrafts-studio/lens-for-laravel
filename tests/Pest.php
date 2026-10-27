<?php

use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

// The core graph stays independent of AI; matrix jobs may load a real optional SDK.
if ($aiAutoload = getenv('LENS_AI_TEST_AUTOLOAD')) {
    require $aiAutoload;
}

// Exercise another installed SDK line without changing the package development graph.
if ($mcpSdkPath = getenv('LENS_MCP_TEST_SDK_PATH')) {
    $mcpLoader = require dirname(__DIR__).'/vendor/autoload.php';
    $mcpLoader->addPsr4(implode(chr(92), ['Laravel', 'Mcp', 'Server', '']), $mcpSdkPath.'/src/Server', true);
    $mcpLoader->addPsr4(implode(chr(92), ['Laravel', 'Mcp', '']), $mcpSdkPath.'/src', true);
}
