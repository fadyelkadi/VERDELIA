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
        $existingData = (int)$pdo->query('SELECT (SELECT COUNT(*) FROM products) + (SELECT COUNT(*) FROM users) + (SELECT COUNT(*) FROM orders)')->fetchColumn();
        if ($existingData > 0) {
            throw new RuntimeException('La base ya contiene datos sin la marca de inicialización. Restaura el respaldo completo o usa una base vacía para la carga inicial.');
        }
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
        $ustmt->execute(['Cliente Verdelia','cliente@verdelia.test',$customerHash,'CUSTOMER']);
        $customerEmails = ['cliente@verdelia.test'];
        foreach (['Camila Rojas', 'Diego Torres', 'Elena Silva', 'Mariana Acosta', 'Nicolás Vega', 'Lucía Méndez'] as $index => $name) {
            $email = 'cliente-'.($index + 1).'@verdelia.test';
            $ustmt->execute([$name, $email, password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT), 'CUSTOMER']);
            $customerEmails[] = $email;
        }
        $sampleUsers = $pdo->prepare('SELECT id,name,email FROM users WHERE role=\'CUSTOMER\' AND email IN ('.implode(',', array_fill(0, count($customerEmails), '?')).') ORDER BY id');
        $sampleUsers->execute($customerEmails);
        $customers = $sampleUsers->fetchAll();
        $catalog = $pdo->query('SELECT p.id,p.sku,p.name,p.price_cents,p.cost_cents,c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id ORDER BY p.sku')->fetchAll();
        require_once __DIR__.'/sales-seed.php';
        verdelia_seed_sales($pdo, $catalog, $customers, new DateTimeImmutable('now'));
        $pdo->prepare('INSERT INTO schema_versions(version) VALUES(?)')->execute(['sales_sample_v2']);
        $pdo->prepare('INSERT INTO schema_versions(version) VALUES(?)')->execute([$version]);
        $pdo->commit();
    }

    // Replace only the original sample labels, preserving user-edited values and order references.
    $labelVersion = 'sample_labels_v1';
    $check->execute([$labelVersion]);
    if ((int)$check->fetchColumn() === 0) {
        $pdo->beginTransaction();
        $sampleAccount = $pdo->prepare("UPDATE users SET name=? WHERE email='cliente@verdelia.test' AND role='CUSTOMER' AND BINARY name=?");
        $sampleAccount->execute(['Cliente Verdelia', 'Cliente Demo']);
        $sampleAddress = $pdo->prepare("UPDATE orders o JOIN users u ON u.id=o.user_id
            SET o.delivery_address=? WHERE u.email='cliente@verdelia.test' AND o.is_demo=1
            AND o.order_number REGEXP '^DEMO-[0-9]{5}$' AND BINARY o.delivery_address=?");
        $sampleAddress->execute(['Dirección no registrada', 'Dirección ficticia de demostración']);
        $pdo->prepare('INSERT INTO schema_versions(version) VALUES(?) ON DUPLICATE KEY UPDATE version=VALUES(version)')->execute([$labelVersion]);
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
