CREATE TABLE IF NOT EXISTS schema_versions (version VARCHAR(40) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL, role ENUM('ADMIN','CUSTOMER') NOT NULL DEFAULT 'CUSTOMER', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_users_admin_listing (role,created_at,id), INDEX idx_users_admin_search (role,name)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS categories (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(80) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL UNIQUE, description TEXT, active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS products (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, category_id INT UNSIGNED NOT NULL, sku VARCHAR(30) NOT NULL UNIQUE,
 slug VARCHAR(120) NOT NULL UNIQUE, name VARCHAR(160) NOT NULL, scientific_name VARCHAR(160), description TEXT,
 price_cents INT UNSIGNED NOT NULL, cost_cents INT UNSIGNED NOT NULL, stock INT UNSIGNED NOT NULL DEFAULT 0,
 light VARCHAR(100), care_level VARCHAR(40), featured TINYINT(1) NOT NULL DEFAULT 0,
 active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_products_categories FOREIGN KEY (category_id) REFERENCES categories(id), INDEX idx_products_category (category_id),
 INDEX idx_products_admin_listing (active,category_id,id), INDEX idx_products_admin_search (active,name)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS product_images (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id INT UNSIGNED NOT NULL,
 local_path VARCHAR(255) NOT NULL, alt_text VARCHAR(200) NOT NULL, position_num INT UNSIGNED NOT NULL DEFAULT 0,
 source_url VARCHAR(500), license_name VARCHAR(120), attribution VARCHAR(255),
 CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS carts (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL UNIQUE, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cart_items (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cart_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL,
 quantity INT UNSIGNED NOT NULL, UNIQUE KEY uq_cart_product (cart_id, product_id),
 CONSTRAINT fk_cart_item_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
 CONSTRAINT fk_cart_item_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS orders (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_number VARCHAR(40) NOT NULL UNIQUE, user_id INT UNSIGNED NOT NULL,
 status ENUM('PENDING_PAYMENT','PAID','CANCELLED','FULFILLED') NOT NULL,
 subtotal_cents INT UNSIGNED NOT NULL, shipping_cents INT UNSIGNED NOT NULL DEFAULT 0, total_cents INT UNSIGNED NOT NULL,
 delivery_address VARCHAR(255) NOT NULL, delivery_name VARCHAR(120) NULL, delivery_email VARCHAR(190) NULL,
 delivery_phone VARCHAR(40) NULL, delivery_city VARCHAR(100) NULL, delivery_state VARCHAR(100) NULL,
 delivery_postal_code VARCHAR(20) NULL, delivery_note VARCHAR(255) NULL, idempotency_key CHAR(64) NULL,
 is_demo TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL, confirmed_at DATETIME NULL,
 CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id), INDEX idx_orders_date_status (created_at,status), INDEX idx_orders_admin_listing (status,created_at,id), INDEX idx_orders_analytics (status,confirmed_at,id),
 UNIQUE KEY uq_orders_idempotency (idempotency_key), INDEX idx_orders_user_date (user_id,created_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS order_items (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NULL,
 sku_snapshot VARCHAR(30) NOT NULL, name_snapshot VARCHAR(160) NOT NULL, category_name_snapshot VARCHAR(100) NULL,
 unit_price_cents INT UNSIGNED NOT NULL, unit_cost_cents INT UNSIGNED NOT NULL,
 quantity INT UNSIGNED NOT NULL, line_total_cents INT UNSIGNED NOT NULL,
 CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id),
 CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS payments (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id INT UNSIGNED NOT NULL,
 provider ENUM('LOCAL_SIMULATOR') NOT NULL DEFAULT 'LOCAL_SIMULATOR',
 status ENUM('PENDING','APPROVED','REJECTED','CANCELLED') NOT NULL,
 fake_reference VARCHAR(80) NOT NULL UNIQUE, attempt_key CHAR(64) NULL UNIQUE, created_at DATETIME NOT NULL,
 CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id), INDEX idx_payments_order_id (order_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS stock_movements (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id INT UNSIGNED NOT NULL,
 order_id INT UNSIGNED NULL, delta INT NOT NULL, reason VARCHAR(80) NOT NULL, created_at DATETIME NOT NULL,
 CONSTRAINT fk_stock_product FOREIGN KEY (product_id) REFERENCES products(id),
 CONSTRAINT fk_stock_order FOREIGN KEY (order_id) REFERENCES orders(id), INDEX idx_stock_product (product_id), INDEX idx_stock_product_date (product_id,created_at,id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_order_status_history (
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
