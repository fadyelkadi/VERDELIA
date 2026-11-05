<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "Solo CLI\n"); exit(2); }
if (PHP_VERSION_ID < 80000 || !extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "Se requiere PHP 8+ con pdo_mysql.\n"); exit(1);
}
$config = require dirname(__DIR__) . '/config/database.php';
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']);
try {
    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $dir = dirname(__DIR__) . '/database/backups';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('No se pudo crear database/backups.');
    $file = $dir . '/verdelia_' . date('Ymd_His') . '.sql';
    $out = @fopen($file, 'x');
    if ($out === false) throw new RuntimeException('No se pudo crear el archivo de exportación; no se sobrescriben respaldos.');
    $write = static function (string $line) use ($out): void {
        if (fwrite($out, $line) === false) throw new RuntimeException('Error al escribir la exportación.');
    };
    $write("-- VERDELIA export; no incluye credenciales de conexión.\n-- Creado: " . date(DATE_ATOM) . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
    $db = $config['database'];
    if (!preg_match('/^[A-Za-z0-9_]+$/', $db)) throw new RuntimeException('Nombre de BD no valido.');
    $write("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\nUSE `$db`;\n");
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $quoted = '`' . str_replace('`', '``', (string)$table) . '`';
        $create = $pdo->query("SHOW CREATE TABLE $quoted")->fetch(PDO::FETCH_NUM);
        $createSql = preg_replace('/^CREATE TABLE /i', 'CREATE TABLE IF NOT EXISTS ', $create[1], 1);
        $write("\n-- Table `$table`\n" . $createSql . ";\n");
        $rows = $pdo->query("SELECT * FROM $quoted");
        foreach ($rows as $row) {
            $columns = array_map(static fn(string $c): string => '`' . str_replace('`', '``', $c) . '`', array_keys($row));
            $values = array_map(static fn($v): string => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
            $write('INSERT INTO ' . $quoted . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n");
        }
    }
    $write("\nSET FOREIGN_KEY_CHECKS=1;\n");
    fclose($out);
    echo "Respaldo creado: $file\n";
} catch (Throwable $e) {
    if (isset($out) && is_resource($out)) fclose($out);
    fwrite(STDERR, 'ERROR de respaldo: ' . $e->getMessage() . "\n"); exit(1);
}
