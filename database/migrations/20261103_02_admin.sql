-- Referencia de la migración admin_v1. El runner PHP valida duplicados e
-- inspecciona information_schema para que cada índice sea idempotente.
ALTER TABLE categories ADD UNIQUE KEY uq_categories_name (name);
ALTER TABLE products ADD KEY idx_products_admin_listing (active, category_id, id);
ALTER TABLE products ADD KEY idx_products_admin_search (active, name);
ALTER TABLE users ADD KEY idx_users_admin_listing (role, created_at, id);
ALTER TABLE users ADD KEY idx_users_admin_search (role, name);
ALTER TABLE orders ADD KEY idx_orders_admin_listing (status, created_at, id);
ALTER TABLE stock_movements ADD KEY idx_stock_product_date (product_id, created_at, id);
CREATE TABLE admin_order_status_history (
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
) ENGINE=InnoDB;
INSERT INTO schema_versions(version) VALUES ('admin_v1');
