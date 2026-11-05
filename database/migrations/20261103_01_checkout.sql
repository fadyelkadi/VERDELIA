-- Fase 5 (checkout): ampliación aditiva; conserva pedidos, pagos y detalles históricos.
ALTER TABLE orders
  ADD COLUMN delivery_name VARCHAR(120) NULL,
  ADD COLUMN delivery_email VARCHAR(190) NULL,
  ADD COLUMN delivery_phone VARCHAR(40) NULL,
  ADD COLUMN delivery_city VARCHAR(100) NULL,
  ADD COLUMN delivery_state VARCHAR(100) NULL,
  ADD COLUMN delivery_postal_code VARCHAR(20) NULL,
  ADD COLUMN delivery_note VARCHAR(255) NULL,
  ADD COLUMN idempotency_key CHAR(64) NULL,
  ADD UNIQUE KEY uq_orders_idempotency (idempotency_key),
  ADD KEY idx_orders_user_date (user_id, created_at);

ALTER TABLE payments
  MODIFY COLUMN status ENUM('PENDING','APPROVED','REJECTED','CANCELLED') NOT NULL,
  ADD KEY idx_payments_order_id (order_id),
  DROP INDEX order_id,
  ADD COLUMN attempt_key CHAR(64) NULL,
  ADD UNIQUE KEY uq_payments_attempt_key (attempt_key);
