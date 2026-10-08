<?php

/**
 * Router script for PHP's built-in server, used ONLY for local development
 * here because `php artisan serve` cannot bind a socket on this machine.
 *
 * Why this file exists rather than pointing the server straight at
 * public/index.php: when index.php is the router, EVERY request — including
 * /build/assets/app-*.js and .css — is handed to Laravel, which answers with
 * an HTML page. The browser then refuses the module with
 * "Expected a JavaScript-or-Wasm module script but the server responded with
 * a MIME type of text/html", and the app's JS never runs.
 *
 * The contract here mirrors what `php artisan serve` does:
 *   - a real file that exists under public/ is returned as-is (correct MIME);
 *   - anything else falls through to the Laravel front controller.
 */

$publicPath = __DIR__.'/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// Refuse path traversal, then serve the real file if it exists.
if ($uri !== '/' && strpos($uri, '..') === false) {
    $file = $publicPath.$uri;

    if (is_file($file)) {
        return false; // let the built-in server stream it with the right MIME
    }
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicPath.'/index.php';

require $publicPath.'/index.php';