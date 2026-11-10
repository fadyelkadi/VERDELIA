<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "Solo CLI\n"); exit(2); }
$port = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]]);
if ($port === false) { fwrite(STDERR, "Puerto invalido: usa un numero entre 1024 y 65535.\n"); exit(2); }

$deadline = microtime(true) + 5.0;
do {
    $connection = @stream_socket_client("tcp://[::1]:$port", $errno, $error, 0.25, STREAM_CLIENT_CONNECT);
    if ($connection !== false) {
        fclose($connection);
        echo "Listener localhost IPv6 activo en el puerto $port.\n";
        exit(0);
    }
    usleep(100000);
} while (microtime(true) < $deadline);

fwrite(STDERR, "No se pudo iniciar la escucha localhost IPv6 en el puerto $port.\n");
exit(1);
