-- Fase de analítica: conservar categoría de venta y acelerar ventanas por fecha confirmada.
-- La ejecución idempotente condicional está implementada en scripts/migrations.php.
ALTER TABLE order_items ADD COLUMN category_name_snapshot VARCHAR(100) NULL AFTER name_snapshot;
UPDATE order_items oi
LEFT JOIN products p ON p.id = oi.product_id
LEFT JOIN categories c ON c.id = p.category_id
SET oi.category_name_snapshot = c.name
WHERE oi.category_name_snapshot IS NULL AND c.name IS NOT NULL;
ALTER TABLE orders ADD KEY idx_orders_analytics (status, confirmed_at, id);
INSERT INTO schema_versions(version) VALUES ('analytics_v1');
