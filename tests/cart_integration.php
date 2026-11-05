<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo CLI\n"); }
require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/database.php';
require dirname(__DIR__) . '/app/CatalogRepository.php';
require dirname(__DIR__) . '/app/CartRepository.php';

$pdo = verdelia_db();
$cart = new CartRepository($pdo);
$products = $pdo->query('SELECT sku,price_cents,stock FROM products WHERE active=1 ORDER BY id LIMIT 2')->fetchAll();
if (count($products) !== 2) throw new RuntimeException('Se requieren dos productos activos para la prueba.');
$skuA = (string)$products[0]['sku'];
$skuB = (string)$products[1]['sku'];
$stockA = (int)$products[0]['stock'];
if ($stockA < 5) throw new RuntimeException('El primer producto no tiene stock suficiente para la prueba.');
$beforeStock = $pdo->query('SELECT sku,stock FROM products ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) throw new RuntimeException('FALLÓ: ' . $message);
    $assertions++;
};
$expectCartError = static function (callable $operation, string $message) use ($assert): void {
    try { $operation(); } catch (CartException) { $assert(true, $message); return; }
    $assert(false, $message);
};
$pdo->beginTransaction();
try {
    $insertUser = $pdo->prepare("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,'CUSTOMER')");
    $insertUser->execute(['Carrito prueba A','cart-test-' . bin2hex(random_bytes(8)) . '@verdelia.test',password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT)]);
    $userA = (int)$pdo->lastInsertId();
    $insertUser->execute(['Carrito prueba B','cart-test-' . bin2hex(random_bytes(8)) . '@verdelia.test',password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT)]);
    $userB = (int)$pdo->lastInsertId();

    $guest = [];
    $cart->mutate(null, $guest, 'add', $skuA, 2);
    $cart->mutate(null, $guest, 'add', $skuB, 1);
    $view = $cart->getCart(null, $guest);
    $expectedSubtotal = 2 * (int)$products[0]['price_cents'] + (int)$products[1]['price_cents'];
    $assert(count($view['items']) === 2 && $view['unit_count'] === 3, 'agrega dos productos y acumula cantidades en sesión');
    $assert($view['subtotal_cents'] === $expectedSubtotal, 'calcula precios y subtotal desde MySQL');

    $cart->mutate(null, $guest, 'set', $skuA, 3);
    $assert(($cart->getCart(null, $guest)['items'][0]['quantity'] ?? 0) === 3, 'cambia cantidades');
    $cart->mutate(null, $guest, 'remove', $skuB);
    $assert(count($cart->getCart(null, $guest)['items']) === 1, 'elimina una línea');
    $expectCartError(static fn() => $cart->mutate(null, $guest, 'set', $skuA, -2), 'rechaza cantidades negativas');
    $expectCartError(static fn() => $cart->mutate(null, $guest, 'set', $skuA, $stockA + 1), 'rechaza cantidades mayores al stock');
    $expectCartError(static fn() => $cart->mutate(null, $guest, 'add', 'ZZZ-999', 1), 'rechaza productos inexistentes');
    $cart->mutate(null, $guest, 'clear');
    $assert($cart->getCart(null, $guest)['items'] === [], 'vacía el carrito de visitante');

    $cart->mutate($userA, $guest, 'add', $skuA, 2);
    $reopened = new CartRepository($pdo);
    $userCart = $reopened->getCart($userA);
    $otherCart = $reopened->getCart($userB);
    $assert(($userCart['items'][0]['quantity'] ?? 0) === 2, 'el carrito autenticado persiste en carts/cart_items');
    $assert($otherCart['items'] === [], 'carritos de clientes aislados por user_id');

    $merge = [$skuA=>2,$skuB=>1];
    $cart->mergeGuestCart($userA, $merge);
    $merged = $reopened->getCart($userA);
    $bySku = array_column($merged['items'], null, 'sku');
    $assert(($bySku[$skuA]['quantity'] ?? 0) === 4 && ($bySku[$skuB]['quantity'] ?? 0) === 1, 'fusiona líneas del visitante con el carrito existente');

    $capUser = $userB;
    $cart->mutate($capUser, $guest, 'set', $skuA, max(1,min($stockA,999)-1));
    $cart->mergeGuestCart($capUser, [$skuA=>999]);
    $capped = $reopened->getCart($capUser);
    $cappedItem = array_values(array_filter($capped['items'], static fn(array $item): bool => $item['sku'] === $skuA))[0] ?? null;
    $assert(is_array($cappedItem) && $cappedItem['quantity'] === min($stockA,999), 'limita la fusión al stock real y al máximo permitido');

    $afterStock = $pdo->query('SELECT sku,stock FROM products ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
    $assert($beforeStock === $afterStock, 'agregar y fusionar no descuenta inventario');
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}

echo "Carrito transaccional aprobado: {$assertions} aserciones; pruebas revertidas, sin usuarios ni carritos residuales.\n";
