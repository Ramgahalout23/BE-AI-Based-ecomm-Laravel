<?php

/**
 * Laravel - A PHP Framework For Web Artisans.
 *
 * @package  Laravel
 * @author   Taylor Otwell
 *
 * @see https://laravel.com/docs
 */

$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// Serve the service worker through PHP so we can send no-store headers.
// The built-in server strips router headers when it serves the file as-is
// (return false), and a cached sw.js would keep an old service worker active
// (e.g. one that intercepts /api/ OAuth callbacks and serves the SPA shell,
// breaking Google login with a 404 page).
if (basename($uri) === 'sw.js') {
    $swFile = $publicPath . '/sw.js';
    if (!file_exists($swFile)) {
        $swFile = __DIR__ . '/public/sw.js';
    }
    if (file_exists($swFile)) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Content-Type: application/javascript; charset=utf-8');
        header('Content-Length: ' . filesize($swFile));
        readfile($swFile);
        return true;
    }
}

// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having installed a "real" web server software here.
if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
