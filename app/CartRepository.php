<?php
declare(strict_types=1);

final class CartException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }
}

final class CartRepository
{
    public function __construct(private PDO $pdo) {}

    /** @param array<string, mixed> $guestCart
     *  @return array{items:array,subtotal_cents:int,total_cents:int,unit_count:int,valid:bool}
     */
    public function getCart(?int $userId, array $guestCart = []): array
    {
        if ($userId !== null) {
            $statement = $this->pdo->prepare('SELECT p.sku, ci.quantity FROM carts c JOIN cart_items ci ON ci.cart_id=c.id JOIN products p ON p.id=ci.product_id WHERE c.user_id=? ORDER BY ci.id');
            $statement->execute([$userId]);
            $quantities = [];
            foreach ($statement->fetchAll() as $row) $quantities[(string)$row['sku']] = (int)$row['quantity'];
        } else {
            $quantities = $this->normalizeGuestCart($guestCart);
        }

        $products = (new CatalogRepository($this->pdo))->bySkus(array_keys($quantities));
        $items = [];
        $subtotal = 0;
        $units = 0;
        $valid = true;
        foreach ($products as $product) {
            $quantity = $quantities[$product['sku']] ?? 0;
            if ($quantity < 1) continue;
            $stock = max(0, (int)$product['stock']);
            $lineTotal = (int)$product['price_cents'] * $quantity;
            $overStock = $quantity > $stock;
            $product['quantity'] = $quantity;
            $product['stock'] = $stock;
            $product['line_total_cents'] = $lineTotal;
            $product['over_stock'] = $overStock;
            $items[] = $product;
            $subtotal += $lineTotal;
            $units += $quantity;
            if ($overStock) $valid = false;
        }
        return ['items'=>$items,'subtotal_cents'=>$subtotal,'total_cents'=>$subtotal,'unit_count'=>$units,'valid'=>$valid];
    }

    /** @param array<string, mixed> $guestCart
     *  @return array{items:array,subtotal_cents:int,total_cents:int,unit_count:int,valid:bool}
     */
    public function mutate(?int $userId, array &$guestCart, string $action, mixed $sku = null, mixed $quantity = null): array
    {
        if (!in_array($action, ['add','set','remove','clear'], true)) throw new CartException('La acción del carrito no es válida.', 400);
        if (in_array($action, ['add','set','remove'], true) && (!is_string($sku) || preg_match('/^[A-Z]{3}-\d{3}$/', $sku) !== 1)) {
            throw new CartException('El producto seleccionado no es válido.', 400);
        }
        if (in_array($action, ['add','set'], true) && (!is_int($quantity) || $quantity < 1 || $quantity > 999)) {
            throw new CartException('La cantidad debe ser un entero entre 1 y 999.');
        }

        if ($userId === null) {
            $guestCart = $this->normalizeGuestCart($guestCart);
            if ($action === 'clear') $guestCart = [];
            elseif ($action === 'remove') unset($guestCart[$sku]);
            else {
                $product = $this->lockableProduct((string)$sku);
                $stock = max(0, (int)$product['stock']);
                if ($stock < 1) throw new CartException('Este producto está agotado.');
                $next = $action === 'add' ? ($guestCart[$sku] ?? 0) + $quantity : $quantity;
                if ($next > min($stock, 999)) throw new CartException('La cantidad supera las existencias disponibles. Stock actual: ' . $stock . '.');
                $guestCart[$sku] = $next;
            }
            return $this->getCart(null, $guestCart);
        }

        $this->transaction(function () use ($userId, $action, $sku, $quantity): void {
            $cartId = $this->ensureUserCart($userId);
            if ($action === 'clear') {
                $delete = $this->pdo->prepare('DELETE FROM cart_items WHERE cart_id=?');
                $delete->execute([$cartId]);
                return;
            }
            if ($action === 'remove') {
                $delete = $this->pdo->prepare('DELETE ci FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.cart_id=? AND p.sku=?');
                $delete->execute([$cartId,(string)$sku]);
                return;
            }
            $product = $this->lockableProduct((string)$sku);
            $stock = max(0, (int)$product['stock']);
            if ($stock < 1) throw new CartException('Este producto está agotado.');
            $existing = $this->pdo->prepare('SELECT quantity FROM cart_items WHERE cart_id=? AND product_id=? FOR UPDATE');
            $existing->execute([$cartId,(int)$product['id']]);
            $current = (int)($existing->fetchColumn() ?: 0);
            $next = $action === 'add' ? $current + $quantity : $quantity;
            if ($next > min($stock, 999)) throw new CartException('La cantidad supera las existencias disponibles. Stock actual: ' . $stock . '.');
            $save = $this->pdo->prepare('INSERT INTO cart_items (cart_id,product_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)');
            $save->execute([$cartId,(int)$product['id'],$next]);
        });
        return $this->getCart($userId);
    }

    /** Merge a visitor cart into the customer's durable cart, capped to live stock. */
    public function mergeGuestCart(int $userId, array $guestCart): void
    {
        $guestCart = $this->normalizeGuestCart($guestCart);
        if ($guestCart === []) return;
        ksort($guestCart, SORT_STRING);
        $this->transaction(function () use ($userId, $guestCart): void {
            $cartId = $this->ensureUserCart($userId);
            foreach ($guestCart as $sku => $guestQuantity) {
                $product = $this->findProduct($sku, true);
                if ($product === null) continue;
                $stock = max(0, (int)$product['stock']);
                if ($stock < 1) continue;
                $existing = $this->pdo->prepare('SELECT quantity FROM cart_items WHERE cart_id=? AND product_id=? FOR UPDATE');
                $existing->execute([$cartId,(int)$product['id']]);
                $current = (int)($existing->fetchColumn() ?: 0);
                $merged = min(min($stock, 999), $current + $guestQuantity);
                if ($merged < 1) continue;
                $save = $this->pdo->prepare('INSERT INTO cart_items (cart_id,product_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)');
                $save->execute([$cartId,(int)$product['id'],$merged]);
            }
        });
    }

    /** @return array<string, int> */
    private function normalizeGuestCart(array $cart): array
    {
        $normalized = [];
        foreach ($cart as $sku => $quantity) {
            if (!is_string($sku) || preg_match('/^[A-Z]{3}-\d{3}$/', $sku) !== 1 || !is_int($quantity) || $quantity < 1 || $quantity > 999) continue;
            $normalized[$sku] = $quantity;
        }
        return $normalized;
    }

    private function ensureUserCart(int $userId): int
    {
        $insert = $this->pdo->prepare('INSERT INTO carts (user_id) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
        $insert->execute([$userId]);
        $query = $this->pdo->prepare('SELECT id FROM carts WHERE user_id=? FOR UPDATE');
        $query->execute([$userId]);
        $id = $query->fetchColumn();
        if ($id === false) throw new RuntimeException('No se pudo abrir el carrito del cliente.');
        return (int)$id;
    }

    private function lockableProduct(string $sku): array
    {
        $product = $this->findProduct($sku, true);
        if ($product === null) throw new CartException('No encontramos ese producto en el catálogo.', 404);
        return $product;
    }

    private function findProduct(string $sku, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT p.id,p.sku,p.stock FROM products p JOIN categories c ON c.id=p.category_id WHERE p.sku=? AND p.active=1 AND c.active=1 LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $query = $this->pdo->prepare($sql);
        $query->execute([$sku]);
        $row = $query->fetch();
        return is_array($row) ? $row : null;
    }

    private function transaction(callable $operation): void
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $operation();
            if ($ownsTransaction) $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }
}
