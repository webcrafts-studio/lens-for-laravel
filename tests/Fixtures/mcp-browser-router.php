<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/axe.js') {
    header('Content-Type: application/javascript');
    readfile(getenv('LENS_MCP_BROWSER_MODULES').'/axe-core/axe.min.js');
} elseif ($path === '/redirect') {
    header('Location: /login', true, 302);
} else {
    header('Content-Type: text/html; charset=utf-8');
    readfile(getenv('LENS_MCP_BROWSER_ROOT').'/page.html');
}
