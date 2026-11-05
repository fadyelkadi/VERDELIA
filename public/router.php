<?php
declare(strict_types=1);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$root = __DIR__;
// Solo permitir servir archivos existentes bajo /public; nunca config/, database/ o scripts/.
$real = realpath($root . $path);
if ($path !== '/' && $real !== false && is_file($real) && str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
    return false;
}
require $root . '/index.php';
