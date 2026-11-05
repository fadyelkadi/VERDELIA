<?php
declare(strict_types=1);

final class OrderException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }
}

final class OrderRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Create/update the user's unpaid order and record one local payment attempt atomically.
     * Rejected attempts leave the order retryable; cancellation closes it. Only approval moves stock.
     * @param array{name:string,email:string,phone:string,address:string,city:string,state:string,postal_code:string,note:string} $delivery
     * @return array{order_number:string,order_status:string,payment_status:string,fake_reference:string,replayed:bool}
     */
    public function processCheckout(int $userId, array $delivery, string $orderKey, string $attemptKey, string $decision, int $expectedTotal): array
    {
        if (!in_array($decision, ['approve','reject','cancel'], true)) throw new OrderException('Selecciona un resultado válido del simulador.', 400);
        foreach ([$orderKey,$attemptKey] as $key) if (preg_match('/^[a-f0-9]{64}$/', $key) !== 1) throw new OrderException('La sesión de compra venció. Vuelve a cargar el checkout.', 419);

        $this->pdo->beginTransaction();
        try {
            $priorAttempt = $this->pdo->prepare('SELECT o.order_number,o.status AS order_status,p.status AS payment_status,p.fake_reference,o.idempotency_key FROM payments p JOIN orders o ON o.id=p.order_id WHERE p.attempt_key=? AND o.user_id=? FOR UPDATE');
            $priorAttempt->execute([$attemptKey,$userId]);
            $prior = $priorAttempt->fetch();
            if (is_array($prior)) {
                if (!hash_equals((string)$prior['idempotency_key'], $orderKey)) throw new OrderException('La referencia de la operación no coincide.', 409);
                $this->pdo->commit();
                return $this->result($prior, true);
            }

            $orderQuery = $this->pdo->prepare('SELECT * FROM orders WHERE user_id=? AND idempotency_key=? FOR UPDATE');
            $orderQuery->execute([$userId,$orderKey]);
            $order = $orderQuery->fetch();
            if (is_array($order) && $order['status'] !== 'PENDING_PAYMENT') {
                $latest = $this->latestPayment((int)$order['id']);
                $this->pdo->commit();
                return [
                    'order_number'=>(string)$order['order_number'],
                    'order_status'=>(string)$order['status'],
                    'payment_status'=>(string)($latest['status'] ?? ($order['status'] === 'PAID' || $order['status'] === 'FULFILLED' ? 'APPROVED' : 'CANCELLED')),
                    'fake_reference'=>(string)($latest['fake_reference'] ?? ''),
                    'replayed'=>true,
                ];
            }

            $cartQuery = $this->pdo->prepare('SELECT id FROM carts WHERE user_id=? FOR UPDATE');
            $cartQuery->execute([$userId]);
            $cartId = $cartQuery->fetchColumn();
            if ($cartId === false) throw new OrderException('Tu carrito está vacío. Añade productos antes de continuar.');
            $lineQuery = $this->pdo->prepare('SELECT ci.product_id,ci.quantity,p.sku,p.name,p.price_cents,p.cost_cents,p.stock,p.active,c.active AS category_active,c.name AS category_name FROM cart_items ci JOIN products p ON p.id=ci.product_id JOIN categories c ON c.id=p.category_id WHERE ci.cart_id=? ORDER BY ci.product_id FOR UPDATE');
            $lineQuery->execute([(int)$cartId]);
            $cartRows = $lineQuery->fetchAll();
            if ($cartRows === []) throw new OrderException('Tu carrito está vacío. Añade productos antes de continuar.');

            $lines = [];
            $subtotal = 0;
            foreach ($cartRows as $row) {
                $productLock = $this->pdo->prepare('SELECT p.id,p.sku,p.name,p.price_cents,p.cost_cents,p.stock,p.active,c.active AS category_active,c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? FOR UPDATE');
                $productLock->execute([(int)$row['product_id']]);
                $product = $productLock->fetch();
                $quantity = (int)$row['quantity'];
                if (!is_array($product) || (int)$product['active'] !== 1 || (int)$product['category_active'] !== 1) throw new OrderException('Un producto del carrito ya no está disponible. Revisa tu carrito.');
                if ($quantity < 1 || $quantity > 999) throw new OrderException('El carrito contiene una cantidad inválida.');
                $stock = (int)$product['stock'];
                if ($quantity > $stock) throw new OrderException('El stock cambió para ' . (string)$product['name'] . '. Ajusta la cantidad antes de confirmar.');
                $price = (int)$product['price_cents'];
                $cost = (int)$product['cost_cents'];
                if ($price < 0 || $cost < 0 || $quantity > intdiv(PHP_INT_MAX, max(1,$price))) throw new OrderException('No se pudo calcular un total seguro.');
                $lineTotal = $price * $quantity;
                if ($subtotal > 4294967295 - $lineTotal) throw new OrderException('El total supera el límite permitido.');
                $subtotal += $lineTotal;
                $lines[] = ['product_id'=>(int)$product['id'],'sku'=>(string)$product['sku'],'name'=>(string)$product['name'],'category_name'=>(string)$product['category_name'],'price'=>$price,'cost'=>$cost,'quantity'=>$quantity,'line_total'=>$lineTotal];
            }
            if ($subtotal !== $expectedTotal) throw new OrderException('El precio o el contenido del carrito cambió. Revisa el total actualizado y vuelve a confirmar.', 409);

            $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            $address = trim($delivery['address'] . ', ' . $delivery['city'] . ', ' . $delivery['state'] . ($delivery['postal_code'] !== '' ? ' ' . $delivery['postal_code'] : ''));
            if (strlen($address) > 255) throw new OrderException('La dirección completa es demasiado larga.');
            $shipping = 0; // Entrega ficticia gratuita en esta demostración académica.
            $total = $subtotal + $shipping;

            if (is_array($order)) {
                $orderId = (int)$order['id'];
                $update = $this->pdo->prepare('UPDATE orders SET status=\'PENDING_PAYMENT\',subtotal_cents=?,shipping_cents=?,total_cents=?,delivery_address=?,delivery_name=?,delivery_email=?,delivery_phone=?,delivery_city=?,delivery_state=?,delivery_postal_code=?,delivery_note=? WHERE id=? AND status=\'PENDING_PAYMENT\'');
                $update->execute([$subtotal,$shipping,$total,$address,$delivery['name'],$delivery['email'],$delivery['phone'],$delivery['city'],$delivery['state'],$delivery['postal_code'] !== '' ? $delivery['postal_code'] : null,$delivery['note'] !== '' ? $delivery['note'] : null,$orderId]);
                $this->pdo->prepare('DELETE FROM order_items WHERE order_id=?')->execute([$orderId]);
            } else {
                $orderNumber = 'VD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
                $insertOrder = $this->pdo->prepare('INSERT INTO orders(order_number,user_id,status,subtotal_cents,shipping_cents,total_cents,delivery_address,delivery_name,delivery_email,delivery_phone,delivery_city,delivery_state,delivery_postal_code,delivery_note,idempotency_key,is_demo,created_at,confirmed_at) VALUES(?,?,\'PENDING_PAYMENT\',?,?,?,?,?,?,?,?,?,?,?,?,1,?,NULL)');
                $insertOrder->execute([$orderNumber,$userId,$subtotal,$shipping,$total,$address,$delivery['name'],$delivery['email'],$delivery['phone'],$delivery['city'],$delivery['state'],$delivery['postal_code'] !== '' ? $delivery['postal_code'] : null,$delivery['note'] !== '' ? $delivery['note'] : null,$orderKey,$now]);
                $orderId = (int)$this->pdo->lastInsertId();
            }
            $insertLine = $this->pdo->prepare('INSERT INTO order_items(order_id,product_id,sku_snapshot,name_snapshot,category_name_snapshot,unit_price_cents,unit_cost_cents,quantity,line_total_cents) VALUES(?,?,?,?,?,?,?,?,?)');
            foreach ($lines as $line) $insertLine->execute([$orderId,$line['product_id'],$line['sku'],$line['name'],$line['category_name'],$line['price'],$line['cost'],$line['quantity'],$line['line_total']]);

            $paymentStatus = match ($decision) {'approve'=>'APPROVED','reject'=>'REJECTED','cancel'=>'CANCELLED'};
            $reference = 'SIM-' . strtoupper(bin2hex(random_bytes(12)));
            $insertPayment = $this->pdo->prepare('INSERT INTO payments(order_id,status,fake_reference,attempt_key,created_at) VALUES(?,?,?,?,?)');
            $insertPayment->execute([$orderId,$paymentStatus,$reference,$attemptKey,$now]);

            if ($decision === 'approve') {
                $decrement = $this->pdo->prepare('UPDATE products SET stock=stock-? WHERE id=? AND active=1 AND stock>=?');
                $movement = $this->pdo->prepare('INSERT INTO stock_movements(product_id,order_id,delta,reason,created_at) VALUES(?,?,?,\'venta demo aprobada\',?)');
                foreach ($lines as $line) {
                    $decrement->execute([$line['quantity'],$line['product_id'],$line['quantity']]);
                    if ($decrement->rowCount() !== 1) throw new OrderException('El stock cambió durante la compra. No se registró el pago; revisa tu carrito.', 409);
                    $movement->execute([$line['product_id'],$orderId,-$line['quantity'],$now]);
                }
                $this->pdo->prepare('UPDATE orders SET status=\'PAID\',confirmed_at=? WHERE id=? AND status=\'PENDING_PAYMENT\'')->execute([$now,$orderId]);
                $this->pdo->prepare('DELETE FROM cart_items WHERE cart_id=?')->execute([(int)$cartId]);
            } elseif ($decision === 'cancel') {
                $this->pdo->prepare('UPDATE orders SET status=\'CANCELLED\' WHERE id=? AND status=\'PENDING_PAYMENT\'')->execute([$orderId]);
            }

            $this->pdo->commit();
            return ['order_number'=>$orderNumber ?? (string)$order['order_number'],'order_status'=>$decision === 'approve' ? 'PAID' : ($decision === 'cancel' ? 'CANCELLED' : 'PENDING_PAYMENT'),'payment_status'=>$paymentStatus,'fake_reference'=>$reference,'replayed'=>false];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @return array{items:array,subtotal_cents:int,total_cents:int,unit_count:int,valid:bool} */
    public function cartSummary(int $userId): array
    {
        return (new CartRepository($this->pdo))->getCart($userId);
    }

    /** @return array{items:array,subtotal_cents:int,total_cents:int,unit_count:int,valid:bool} */
    public function guestCartSummary(array $guestCart): array
    {
        return (new CartRepository($this->pdo))->getCart(null, $guestCart);
    }

    /** @return array{items:array,subtotal_cents:int,total_cents:int,unit_count:int,valid:bool} */
    public function accountOrders(int $userId, int $page = 1, int $perPage = 10): array
    {
        return $this->listOrders('user_id = ?', [$userId], $page, $perPage);
    }

    /** @return array{items:array,subtotal_cents:int,total_cents:int,unit_count:int,valid:bool} */
    public function adminOrders(int $page = 1, int $perPage = 15): array
    {
        return $this->listOrders('1=1', [], $page, $perPage);
    }

    private function listOrders(string $where, array $params, int $page, int $perPage): array
    {
        $page = max(1,min(100000,$page));
        $perPage = max(1,min(30,$perPage));
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM orders o WHERE ' . $where);
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $pages = max(1,(int)ceil($total / $perPage));
        $page = min($page,$pages);
        $sql = 'SELECT o.order_number,o.status,o.total_cents,o.created_at,o.confirmed_at,u.name AS customer_name,(SELECT p.status FROM payments p WHERE p.order_id=o.id ORDER BY p.id DESC LIMIT 1) AS payment_status,(SELECT p.fake_reference FROM payments p WHERE p.order_id=o.id ORDER BY p.id DESC LIMIT 1) AS fake_reference FROM orders o JOIN users u ON u.id=o.user_id WHERE ' . $where . ' ORDER BY o.created_at DESC,o.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
        $query = $this->pdo->prepare($sql);
        $query->execute($params);
        return ['items'=>$query->fetchAll(),'page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>$pages];
    }

    public function findOrder(string $orderNumber, int $userId, bool $admin = false): ?array
    {
        $sql = 'SELECT o.*,u.name AS customer_name,u.email AS customer_email,(SELECT p.status FROM payments p WHERE p.order_id=o.id ORDER BY p.id DESC LIMIT 1) AS payment_status,(SELECT p.fake_reference FROM payments p WHERE p.order_id=o.id ORDER BY p.id DESC LIMIT 1) AS fake_reference,(SELECT p.created_at FROM payments p WHERE p.order_id=o.id ORDER BY p.id DESC LIMIT 1) AS payment_created_at FROM orders o JOIN users u ON u.id=o.user_id WHERE o.order_number=?' . ($admin ? '' : ' AND o.user_id=?') . ' LIMIT 1';
        $query = $this->pdo->prepare($sql);
        $query->execute($admin ? [$orderNumber] : [$orderNumber,$userId]);
        $order = $query->fetch();
        if (!is_array($order)) return null;
        $lines = $this->pdo->prepare('SELECT sku_snapshot,name_snapshot,unit_price_cents,unit_cost_cents,quantity,line_total_cents FROM order_items WHERE order_id=? ORDER BY id');
        $lines->execute([(int)$order['id']]);
        $order['items'] = $lines->fetchAll();
        $attempts = $this->pdo->prepare('SELECT status,fake_reference,created_at FROM payments WHERE order_id=? ORDER BY id DESC');
        $attempts->execute([(int)$order['id']]);
        $order['payment_attempts'] = $attempts->fetchAll();
        return $order;
    }

    public function cancelPendingOrder(string $orderNumber, int $userId): bool
    {
        $query = $this->pdo->prepare('UPDATE orders SET status=\'CANCELLED\' WHERE order_number=? AND user_id=? AND status=\'PENDING_PAYMENT\'');
        $query->execute([$orderNumber,$userId]);
        return $query->rowCount() === 1;
    }

    private function latestPayment(int $orderId): ?array
    {
        $query = $this->pdo->prepare('SELECT status,fake_reference FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1');
        $query->execute([$orderId]);
        $row = $query->fetch();
        return is_array($row) ? $row : null;
    }

    private function result(array $row, bool $replayed): array
    {
        return ['order_number'=>(string)$row['order_number'],'order_status'=>(string)$row['order_status'],'payment_status'=>(string)$row['payment_status'],'fake_reference'=>(string)$row['fake_reference'],'replayed'=>$replayed];
    }
}
