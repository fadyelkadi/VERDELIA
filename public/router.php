<?php
declare(strict_types=1);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (preg_match('~^/assets/(?:[a-z0-9_-]+/)*[a-z0-9][a-z0-9._-]*\.(?:css|js|svg|png|webp|jpeg|jpg|ico|woff2?)$~iD', $path)) {
    $real = realpath(__DIR__ . $path);
    $assets = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR;
    if ($real !== false && is_file($real) && str_starts_with($real, $assets)) {
        return false;
    }
}
require __DIR__ . '/index.php';
