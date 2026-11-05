<?php
declare(strict_types=1);

/** Apply the additive checkout schema migration to both new and existing databases. */
function verdelia_apply_migrations(PDO $pdo): void
{
    $version = 'checkout_v1';
    $check = $pdo->prepare('SELECT COUNT(*) FROM schema_versions WHERE version=?');
    $check->execute([$version]);
    $checkoutApplied=(int)$check->fetchColumn()>0;

    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $query = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $query->execute([$table, $column]);
        return (int)$query->fetchColumn() > 0;
    };
    $index = static function (string $table, string $name) use ($pdo): ?array {
        $query = $pdo->prepare('SELECT NON_UNIQUE, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX');
        $query->execute([$table, $name]);
        $rows = $query->fetchAll();
        return $rows === [] ? null : $rows;
    };
    $addColumn = static function (string $name, string $definition) use ($pdo, $columnExists): void {
        if (!$columnExists('orders', $name)) $pdo->exec('ALTER TABLE orders ADD COLUMN ' . $name . ' ' . $definition);
    };

    if (!$checkoutApplied) {
    $addColumn('delivery_name', 'VARCHAR(120) NULL');
    $addColumn('delivery_email', 'VARCHAR(190) NULL');
    $addColumn('delivery_phone', 'VARCHAR(40) NULL');
    $addColumn('delivery_city', 'VARCHAR(100) NULL');
    $addColumn('delivery_state', 'VARCHAR(100) NULL');
    $addColumn('delivery_postal_code', 'VARCHAR(20) NULL');
    $addColumn('delivery_note', 'VARCHAR(255) NULL');
    $addColumn('idempotency_key', 'CHAR(64) NULL');
    if ($index('orders', 'uq_orders_idempotency') === null) $pdo->exec('ALTER TABLE orders ADD UNIQUE KEY uq_orders_idempotency (idempotency_key)');
    if ($index('orders', 'idx_orders_user_date') === null) $pdo->exec('ALTER TABLE orders ADD KEY idx_orders_user_date (user_id, created_at)');

    $pdo->exec("ALTER TABLE payments MODIFY COLUMN status ENUM('PENDING','APPROVED','REJECTED','CANCELLED') NOT NULL");
    // MySQL requires a supporting index for the FK before the old unique index can be dropped.
    if ($index('payments', 'idx_payments_order_id') === null) $pdo->exec('ALTER TABLE payments ADD KEY idx_payments_order_id (order_id)');
    $orderIndex = $index('payments', 'order_id');
    if ($orderIndex !== null && (int)$orderIndex[0]['NON_UNIQUE'] === 0) $pdo->exec('ALTER TABLE payments DROP INDEX order_id');
    if (!$columnExists('payments', 'attempt_key')) $pdo->exec('ALTER TABLE payments ADD COLUMN attempt_key CHAR(64) NULL');
    if ($index('payments', 'uq_payments_attempt_key') === null) $pdo->exec('ALTER TABLE payments ADD UNIQUE KEY uq_payments_attempt_key (attempt_key)');

    $mark = $pdo->prepare('INSERT INTO schema_versions(version) VALUES(?)');
    $mark->execute([$version]);
    }

    $mark = $pdo->prepare('INSERT INTO schema_versions(version) VALUES(?)');
    $adminVersion = 'admin_v1';
    $check->execute([$adminVersion]);
    $addIndex = static function (string $table, string $name, string $definition) use ($index, $pdo): void {
        if ($index($table, $name) === null) $pdo->exec('ALTER TABLE `' . $table . '` ADD ' . $definition);
    };
    if ((int)$check->fetchColumn() === 0) {
        $duplicates = (int)$pdo->query('SELECT COUNT(*) FROM (SELECT name FROM categories GROUP BY name HAVING COUNT(*)>1) duplicate_categories')->fetchColumn();
        if ($duplicates > 0) throw new RuntimeException('La migración administrativa requiere nombres de categoría únicos; revisa duplicados antes de continuar.');
        $addIndex('categories', 'uq_categories_name', 'UNIQUE KEY `uq_categories_name` (`name`)');
        $addIndex('products', 'idx_products_admin_listing', 'KEY `idx_products_admin_listing` (`active`,`category_id`,`id`)');
        $addIndex('products', 'idx_products_admin_search', 'KEY `idx_products_admin_search` (`active`,`name`)');
        $addIndex('users', 'idx_users_admin_listing', 'KEY `idx_users_admin_listing` (`role`,`created_at`,`id`)');
        $addIndex('users', 'idx_users_admin_search', 'KEY `idx_users_admin_search` (`role`,`name`)');
        $addIndex('orders', 'idx_orders_admin_listing', 'KEY `idx_orders_admin_listing` (`status`,`created_at`,`id`)');
        $addIndex('stock_movements', 'idx_stock_product_date', 'KEY `idx_stock_product_date` (`product_id`,`created_at`,`id`)');
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_order_status_history (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            admin_user_id INT UNSIGNED NOT NULL,
            from_status ENUM('PENDING_PAYMENT','PAID','CANCELLED','FULFILLED') NOT NULL,
            to_status ENUM('PENDING_PAYMENT','PAID','CANCELLED','FULFILLED') NOT NULL,
            reason VARCHAR(255) NOT NULL,
            changed_at DATETIME NOT NULL,
            CONSTRAINT fk_admin_order_history_order FOREIGN KEY (order_id) REFERENCES orders(id),
            CONSTRAINT fk_admin_order_history_admin FOREIGN KEY (admin_user_id) REFERENCES users(id),
            INDEX idx_admin_order_history_order (order_id, changed_at)
        ) ENGINE=InnoDB");
        $mark->execute([$adminVersion]);
    }

    $analyticsVersion = 'analytics_v1';
    $check->execute([$analyticsVersion]);
    if ((int)$check->fetchColumn() > 0) return;
    $itemCategoryExists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $itemCategoryExists->execute(['order_items','category_name_snapshot']);
    if ((int)$itemCategoryExists->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE order_items ADD COLUMN category_name_snapshot VARCHAR(100) NULL AFTER name_snapshot');
    }
    $pdo->exec('UPDATE order_items oi LEFT JOIN products p ON p.id=oi.product_id LEFT JOIN categories c ON c.id=p.category_id SET oi.category_name_snapshot=c.name WHERE oi.category_name_snapshot IS NULL AND c.name IS NOT NULL');
    $addIndex('orders', 'idx_orders_analytics', 'KEY `idx_orders_analytics` (`status`,`confirmed_at`,`id`)');
    $mark->execute([$analyticsVersion]);
}
