<?php

// Dev router for `php -S`. Static files (CSS/JS/images) are streamed directly
// with the right content type; everything else goes through Laravel's front
// controller. Without this, `php -S` with an explicit router hands EVERY
// request — including a request for build/assets/app-*.css — to index.php, so
// the browser receives the HTML page where it expected a stylesheet.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__.'/public'.$path;

if ($path !== '/' && is_file($file)) {
    $types = [
        'css' => 'text/css',
        'js' => 'text/javascript',
        'mjs' => 'text/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'json' => 'application/json',
        'map' => 'application/json',
    ];

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: '.($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: '.filesize($file));
    readfile($file);
    exit;
}

require __DIR__.'/public/index.php';
