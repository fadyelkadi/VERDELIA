<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "Solo CLI\n"); exit(2); }
$port = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]]);
if ($port === false) { fwrite(STDERR, "Puerto invalido: usa un numero entre 1024 y 65535.\n"); exit(2); }
$connection = @stream_socket_client("tcp://127.0.0.1:$port", $connectErrno, $connectError, 0.2, STREAM_CLIENT_CONNECT);
if ($connection !== false) {
    fclose($connection);
    fwrite(STDERR, "Puerto $port ocupado: ya hay un servicio escuchando en localhost.\n");
    exit(1);
}
$socket = @stream_socket_server("tcp://127.0.0.1:$port", $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
if ($socket === false) { fwrite(STDERR, "Puerto $port ocupado o no disponible: $error ($errno).\n"); exit(1); }
fclose($socket);
exit(0);
