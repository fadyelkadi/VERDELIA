<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI');
}

echo "Iniciando VERDELIA desde PHP...\n";
require __DIR__ . '/check-runtime.php';

if (!function_exists('proc_open')) {
    fwrite(STDERR, "ERROR: Este PHP no permite iniciar el servidor local con proc_open.\n");
    exit(1);
}

$configuredPort = getenv('VERDELIA_WEB_PORT');
$automaticPort = $configuredPort === false || $configuredPort === '';
$firstPort = filter_var(
    $automaticPort ? '8088' : $configuredPort,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1024, 'max_range' => 65535]]
);
if ($firstPort === false) {
    fwrite(STDERR, "ERROR: VERDELIA_WEB_PORT debe estar entre 1024 y 65535.\n");
    exit(2);
}

$port = null;
$lastError = 'puerto ocupado';
for ($candidate = $firstPort; $candidate <= min(65535, $firstPort + ($automaticPort ? 127 : 0)); $candidate++) {
    $address = "tcp://127.0.0.1:$candidate";
    $connection = @stream_socket_client($address, $errno, $error, 0.2);
    if (is_resource($connection)) {
        fclose($connection);
        continue;
    }
    $listener = @stream_socket_server($address, $errno, $error);
    if (is_resource($listener)) {
        fclose($listener);
        $port = $candidate;
        break;
    }
    $lastError = $error;
}
if ($port === null) {
    fwrite(STDERR, "ERROR: No se pudo abrir un puerto local desde $firstPort ($lastError).\n");
    exit(1);
}

echo "Verificando MySQL e inicializando VERDELIA...\n";
require __DIR__ . '/bootstrap.php';

$projectRoot = dirname(__DIR__);
$public = $projectRoot . '/public';
echo "Abre http://127.0.0.1:$port/ en el navegador. Presiona Ctrl+C para detener el servidor.\n";
$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $public, $public . '/router.php'],
    [STDIN, STDOUT, STDERR],
    $pipes,
    $projectRoot,
    null,
    ['bypass_shell' => true]
);
if (!is_resource($server)) {
    fwrite(STDERR, "ERROR: No se pudo iniciar el servidor PHP.\n");
    exit(1);
}
exit(proc_close($server));
