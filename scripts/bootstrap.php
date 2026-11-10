<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo CLI'); }
if (PHP_VERSION_ID < 80000) { fwrite(STDERR, "ERROR: VERDELIA requiere PHP 8.0 o superior. Version detectada: " . PHP_VERSION . "\n"); exit(1); }
if (!extension_loaded('pdo_mysql')) { fwrite(STDERR, "ERROR: PHP requiere pdo_mysql habilitado.\n"); exit(1); }
$config = require dirname(__DIR__) . '/config/database.php';
$dbName = $config['database'];
try {
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']);
    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) throw new RuntimeException('VERDELIA_DB_NAME solo puede contener letras, numeros y guion bajo.');
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . $dbName . '`');
    // Ejecutar sentencias por separado para mayor compatibilidad entre versiones de PDO/MySQL.
    $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
    if ($schema === false) throw new RuntimeException('No se pudo leer schema.sql');
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $schema) as $statement) {
        if (trim($statement) !== '') $pdo->exec($statement);
    }
    require_once __DIR__ . '/migrations.php';
    verdelia_apply_migrations($pdo);
    $products = require dirname(__DIR__) . '/app/data/catalog-seed.php';
    $version = 'demo_seed_v1';
    $check = $pdo->prepare('SELECT COUNT(*) FROM schema_versions WHERE version = ?');
    $check->execute([$version]);
    if ((int)$check->fetchColumn() === 0) {
        $pdo->beginTransaction();
        $categories = [
            ['interior', 'Plantas de interior'], ['suculentas', 'Suculentas y cactus'],
            ['macetas', 'Macetas'], ['accesorios', 'Accesorios']
        ];
        $stmt = $pdo->prepare('INSERT INTO categories(slug,name) VALUES(?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)');
        foreach ($categories as $row) $stmt->execute($row);
        $catIds = $pdo->query('SELECT slug,id FROM categories')->fetchAll(PDO::FETCH_KEY_PAIR);
        $pstmt = $pdo->prepare('INSERT INTO products(category_id,sku,slug,name,description,price_cents,cost_cents,stock,light,care_level,featured) VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE sku=VALUES(sku)');
        foreach ($products as $product) {
            $pstmt->execute([$catIds[$product['category']],$product['sku'],$product['slug'],$product['name'],$product['description'],$product['price'],$product['cost'],$product['stock'],$product['light'],$product['care'],(int)$product['featured']]);
        }
        $adminHash = password_hash('123456789', PASSWORD_BCRYPT);
        $customerHash = password_hash('VerdeliaCliente2026!', PASSWORD_BCRYPT);
        $ustmt = $pdo->prepare('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE email=VALUES(email)');
        $ustmt->execute(['Admin Verdelia','admin@verdelia.test',$adminHash,'ADMIN']);
        $ustmt->execute(['Cliente Demo','cliente@verdelia.test',$customerHash,'CUSTOMER']);
        $userId = (int)$pdo->query("SELECT id FROM users WHERE email = 'cliente@verdelia.test'")->fetchColumn();
        $catalog = $pdo->query('SELECT p.id,p.sku,p.name,p.price_cents,p.cost_cents,c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id ORDER BY p.sku')->fetchAll();
        $orderStmt = $pdo->prepare('INSERT INTO orders(order_number,user_id,status,subtotal_cents,shipping_cents,total_cents,delivery_address,is_demo,created_at,confirmed_at) VALUES(?,?,?,?,?,?,?,?,?,?)');
        $lineStmt = $pdo->prepare('INSERT INTO order_items(order_id,product_id,sku_snapshot,name_snapshot,category_name_snapshot,unit_price_cents,unit_cost_cents,quantity,line_total_cents) VALUES(?,?,?,?,?,?,?,?,?)');
        $payStmt = $pdo->prepare('INSERT INTO payments(order_id,status,fake_reference,created_at) VALUES(?,?,?,?)');
        // El historial demo no descuenta del inventario disponible actual.
        $start = new DateTimeImmutable('2026-05-01 12:00:00');
        for ($i = 1; $i <= 96; $i++) {
            $product = $catalog[($i * 7) % count($catalog)];
            $qty = 1 + ($i % 3);
            $subtotal = (int)$product['price_cents'] * $qty;
            $status = ($i % 17 === 0) ? 'CANCELLED' : (($i % 11 === 0) ? 'FULFILLED' : 'PAID');
            $date = $start->modify('+' . ($i * 45) . ' hours')->format('Y-m-d H:i:s');
            $confirmed = $status === 'CANCELLED' ? null : $date;
            $orderStmt->execute([sprintf('DEMO-%05d',$i),$userId,$status,$subtotal,0,$subtotal,'Dirección ficticia de demostración',1,$date,$confirmed]);
            $orderId = (int)$pdo->lastInsertId();
            $lineStmt->execute([$orderId,$product['id'],$product['sku'],$product['name'],$product['category_name'],$product['price_cents'],$product['cost_cents'],$qty,$subtotal]);
            $payStmt->execute([$orderId,$status === 'CANCELLED' ? 'REJECTED' : 'APPROVED',sprintf('SIM-DEMO-%05d',$i),$date]);
        }
        $pdo->prepare('INSERT INTO schema_versions(version) VALUES(?)')->execute([$version]);
        $pdo->commit();
    }

    // Migración de datos v1: vincula cada SKU con su recurso local sin tocar inventario ni pedidos.
    $imageVersion = 'catalog_images_v1';
    $check->execute([$imageVersion]);
    if ((int)$check->fetchColumn() === 0) {
        $pdo->beginTransaction();
        $productId = $pdo->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');
        $imageExists = $pdo->prepare('SELECT id FROM product_images WHERE product_id = ? AND local_path = ? LIMIT 1');
        $imageInsert = $pdo->prepare('INSERT INTO product_images(product_id,local_path,alt_text,position_num) VALUES(?,?,?,0)');
        foreach ($products as $product) {
            $productId->execute([$product['sku']]);
            $id = $productId->fetchColumn();
            if ($id === false) continue;
            $path = 'assets/images/products/' . $product['image'];
            $imageExists->execute([(int)$id,$path]);
            if ($imageExists->fetchColumn() === false) $imageInsert->execute([(int)$id,$path,$product['image_alt']]);
        }
        $pdo->prepare('INSERT INTO schema_versions(version) VALUES(?)')->execute([$imageVersion]);
        $pdo->commit();
    }
    echo "VERDELIA: base preparada; productos, categorías e imágenes locales listos. La semilla histórica existente se conserva.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "ERROR de inicialización: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Verifica MySQL en XAMPP, puerto 3306, credenciales y permisos CREATE DATABASE.\n");
    exit(1);
}
