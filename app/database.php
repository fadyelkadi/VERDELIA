<?php
declare(strict_types=1);

function verdelia_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $config = require dirname(__DIR__) . '/config/database.php';
    if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$config['database'])) throw new RuntimeException('Configuración de base de datos inválida.');
    $port = filter_var($config['port'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>65535]]);
    if ($port === false || trim((string)$config['host']) === '') throw new RuntimeException('Configuración de base de datos inválida.');
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $port, $config['database']);
        $pdo = new PDO($dsn, $config['user'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5]);
        return $pdo;
    } catch (PDOException $exception) {
        throw new RuntimeException('No fue posible conectar con la base de datos.', 0, $exception);
    }
}

function verdelia_log_error(Throwable $exception, string $context): void
{
    $line = sprintf("[%s] %s: %s\n", date(DATE_ATOM), $context, $exception->getMessage());
    $directory = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($directory)) @mkdir($directory, 0775, true);
    if (is_dir($directory) && is_writable($directory)) @file_put_contents($directory . '/app.log', $line, FILE_APPEND | LOCK_EX);
    error_log(trim($line));
}
