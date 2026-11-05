<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "Solo CLI\n"); exit(2); }
require dirname(__DIR__) . '/app/database.php';

$backupPath = $argv[1] ?? '';
if ($backupPath === '' || !is_file($backupPath)) { fwrite(STDERR, "Indica un archivo SQL de respaldo existente.\n"); exit(2); }
$config = require dirname(__DIR__) . '/config/database.php';
$testDatabase = 'verdelia_restore_check_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
$created = false;

try {
    $pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4',$config['host'],$config['port']),$config['user'],$config['password'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_TIMEOUT=>5,
    ]);
    $sql = file_get_contents($backupPath);
    if ($sql === false) throw new RuntimeException('No se pudo leer el respaldo.');
    $sql = preg_replace('/CREATE DATABASE IF NOT EXISTS `verdelia_demo`/','CREATE DATABASE IF NOT EXISTS `' . $testDatabase . '`',$sql,1,$createCount);
    $sql = preg_replace('/USE `verdelia_demo`/','USE `' . $testDatabase . '`',$sql,1,$useCount);
    if ($createCount !== 1 || $useCount !== 1 || !is_string($sql)) throw new RuntimeException('El archivo no parece un respaldo VERDELIA esperado.');
    $pdo->exec('CREATE DATABASE `' . $testDatabase . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement) if (trim($statement) !== '') $pdo->exec($statement);
    $pdo->exec('USE `' . $testDatabase . '`');
    $tables=(int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn();
    $products=(int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $images=(int)$pdo->query('SELECT COUNT(*) FROM product_images')->fetchColumn();
    $orders=(int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $items=(int)$pdo->query('SELECT COUNT(*) FROM order_items')->fetchColumn();
    $adminMigration=(int)$pdo->query("SELECT COUNT(*) FROM schema_versions WHERE version='admin_v1'")->fetchColumn();
    $analyticsMigration=(int)$pdo->query("SELECT COUNT(*) FROM schema_versions WHERE version='analytics_v1'")->fetchColumn();
    $categorySnapshots=(int)$pdo->query('SELECT COUNT(*) FROM order_items WHERE category_name_snapshot IS NOT NULL')->fetchColumn();
    $statusHistory=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_order_status_history'")->fetchColumn();
    if ($tables!==12 || $products<12 || $images<12 || $orders<96 || $items<96 || $adminMigration!==1 || $analyticsMigration!==1 || $categorySnapshots<$items || $statusHistory!==1) throw new RuntimeException('El respaldo restaurado no conserva el esquema o los conteos esperados.');
    echo "RESTORE_OK tablas=$tables productos=$products imágenes=$images pedidos=$orders líneas=$items snapshots_categoría=$categorySnapshots en BD temporal aislada.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if (isset($pdo) && $created) {
        try { $pdo->exec('DROP DATABASE IF EXISTS `' . $testDatabase . '`'); echo "Limpieza: solo se eliminó la BD temporal $testDatabase.\n"; }
        catch (Throwable $cleanupError) { fwrite(STDERR, "Aviso: no se pudo limpiar la BD temporal. Nombre: $testDatabase\n"); $exitCode = 1; }
    }
}
if (isset($exitCode)) exit($exitCode);
