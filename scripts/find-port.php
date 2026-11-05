<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "Solo CLI\n"); exit(2); }
$start = filter_var($argv[1] ?? '8088', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1024,'max_range'=>65535]]);
if ($start === false) { fwrite(STDERR, "Puerto inicial inválido; usa un número entre 1024 y 65535.\n"); exit(2); }

for ($offset = 0; $offset < 128; $offset++) {
    $port = (int)$start + $offset;
    if ($port > 65535) $port = 1024 + (($port - 1024) % (65535 - 1024 + 1));
    $connection = @stream_socket_client("tcp://127.0.0.1:$port", $connectErrno, $connectError, 0.2, STREAM_CLIENT_CONNECT);
    if ($connection !== false) {
        fclose($connection);
        continue;
    }
    $socket = @stream_socket_server("tcp://127.0.0.1:$port", $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
    if ($socket === false) continue;
    fclose($socket);
    echo $port, PHP_EOL;
    exit(0);
}

fwrite(STDERR, "No hay un puerto local disponible cerca del puerto inicial $start.\n");
exit(1);
