<?php
// Router for PHP built-in dev server (php -S localhost:PORT router.php).
// Apache (.htaccess) does this in production; this file is dev-only.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// Block direct access to internal directories
if (preg_match('#^/(src|config|vendor|views|storage)/#', $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}
if ($path === '/.env' || $path === '/composer.json' || $path === '/composer.lock') {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

// Serve real files (CSS, JS, images) directly
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false; // let PHP serve the static file
}

// Front controller
require __DIR__ . '/index.php';
