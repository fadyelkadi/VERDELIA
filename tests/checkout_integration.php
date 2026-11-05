<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR,"Solo CLI\n"); exit(2); }
require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/database.php';
require dirname(__DIR__) . '/app/CatalogRepository.php';
require dirname(__DIR__) . '/app/CartRepository.php';
require dirname(__DIR__) . '/app/OrderRepository.php';
require dirname(__DIR__) . '/scripts/migrations.php';

$config = require dirname(__DIR__) . '/config/database.php';
$backupPath = $argv[1] ?? '';
if ($backupPath === '') {
    $backups = glob(dirname(__DIR__) . '/database/backups/verdelia_*.sql') ?: [];
    usort($backups, static fn(string $a,string $b): int => filemtime($b) <=> filemtime($a));
    $backupPath = $backups[0] ?? '';
}
if ($backupPath === '' || !is_file($backupPath)) { fwrite(STDERR,"Indica un respaldo SQL para usar una base aislada.\n"); exit(2); }
$database = 'verdelia_checkout_test_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
$created = false;
$assertions = 0;
$assert = static function(bool $condition,string $message) use (&$assertions): void {
    if (!$condition) throw new RuntimeException('FALLÓ: ' . $message);
    $assertions++;
};
$expectOrderError = static function(callable $operation,int $status,string $message) use ($assert): void {
    try { $operation(); } catch (OrderException $exception) { $assert($exception->httpStatus === $status,$message . ' (HTTP ' . $exception->httpStatus . ')'); return; }
    $assert(false,$message . ' (no se produjo error)');
};

try {
    $server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4',$config['host'],$config['port']),$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5]);
    $sql = file_get_contents($backupPath);
    if ($sql === false) throw new RuntimeException('No se pudo leer el respaldo de pruebas.');
    $sql = preg_replace('/CREATE DATABASE IF NOT EXISTS `verdelia_demo`/','CREATE DATABASE IF NOT EXISTS `' . $database . '`',$sql,1,$createCount);
    $sql = preg_replace('/USE `verdelia_demo`/','USE `' . $database . '`',$sql,1,$useCount);
    if ($createCount !== 1 || $useCount !== 1 || !is_string($sql)) throw new RuntimeException('El archivo no parece un respaldo VERDELIA esperado.');
    $server->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement) if (trim($statement) !== '') $server->exec($statement);
    $server->exec('USE `' . $database . '`');
    verdelia_apply_migrations($server);

    $userId = (int)$server->query("SELECT id FROM users WHERE email='cliente@verdelia.test'")->fetchColumn();
    $otherUserId = (int)$server->query("SELECT id FROM users WHERE email='admin@verdelia.test'")->fetchColumn();
    $products = $server->query('SELECT p.id,p.sku,p.price_cents,p.stock,c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.active=1 ORDER BY p.id LIMIT 2')->fetchAll();
    if ($userId < 1 || $otherUserId < 1 || count($products) < 2) throw new RuntimeException('La copia aislada no contiene sus datos base.');
    $sku = (string)$products[0]['sku'];
    $productId = (int)$products[0]['id'];
    $originalStock = (int)$products[0]['stock'];
    if ($originalStock < 5) throw new RuntimeException('Stock insuficiente para la prueba aislada.');
    $cart = new CartRepository($server);
    $orders = new OrderRepository($server);
    $guest = [];
    $cart->mutate($userId,$guest,'add',$sku,2);
    $total = (int)$products[0]['price_cents'] * 2;
    $delivery = ['name'=>'Cliente de prueba','email'=>'cliente@verdelia.test','phone'=>'+58 412 000 0000','address'=>'Av. Ficticia 100','city'=>'Caracas','state'=>'Distrito Capital','postal_code'=>'1010','note'=>'Entrega de prueba'];
    $orderKey = bin2hex(random_bytes(32));
    $rejectKey = bin2hex(random_bytes(32));
    $rejected = $orders->processCheckout($userId,$delivery,$orderKey,$rejectKey,'reject',$total);
    $assert($rejected['order_status']==='PENDING_PAYMENT' && $rejected['payment_status']==='REJECTED','un rechazo deja el pedido pendiente y registra el intento');
    $assert((int)$server->query('SELECT stock FROM products WHERE id=' . $productId)->fetchColumn()===$originalStock,'el rechazo no rebaja inventario');
    $assert(count($cart->getCart($userId)['items'])===1,'el rechazo conserva el carrito');
    $replayReject = $orders->processCheckout($userId,$delivery,$orderKey,$rejectKey,'approve',$total);
    $assert($replayReject['replayed'] && $replayReject['payment_status']==='REJECTED' && $replayReject['fake_reference']===$rejected['fake_reference'],'repetir la misma clave devuelve el rechazo previo sin cambiarlo');

    $approveKey = bin2hex(random_bytes(32));
    $approved = $orders->processCheckout($userId,$delivery,$orderKey,$approveKey,'approve',$total);
    $assert($approved['order_number']===$rejected['order_number'] && $approved['order_status']==='PAID','un reintento aprobado completa el mismo pedido');
    $historicalCategory=$server->query('SELECT category_name_snapshot FROM order_items WHERE order_id=(SELECT id FROM orders WHERE order_number='.$server->quote($approved['order_number']).') LIMIT 1')->fetchColumn();
    $assert($historicalCategory===$products[0]['category_name'],'checkout guarda la categoría del catálogo como snapshot histórico');
    $assert((int)$server->query('SELECT stock FROM products WHERE id=' . $productId)->fetchColumn()===$originalStock-2,'la aprobación descuenta el stock una sola vez');
    $assert((int)$server->query("SELECT COUNT(*) FROM stock_movements WHERE order_id=(SELECT id FROM orders WHERE order_number=" . $server->quote($approved['order_number']) . ')')->fetchColumn()===1,'registra un único movimiento por línea vendida');
    $assert($cart->getCart($userId)['items']===[],'la compra aprobada vacía el carrito');
    $replayApprove = $orders->processCheckout($userId,$delivery,$orderKey,$approveKey,'approve',$total);
    $assert($replayApprove['replayed'] && $replayApprove['order_number']===$approved['order_number'],'recarga/reenvío aprobado es idempotente');
    $assert((int)$server->query('SELECT stock FROM products WHERE id=' . $productId)->fetchColumn()===$originalStock-2,'el reenvío no vuelve a descontar inventario');
    $assert((int)$server->query('SELECT COUNT(*) FROM payments WHERE order_id=(SELECT id FROM orders WHERE order_number=' . $server->quote($approved['order_number']) . ')')->fetchColumn()===2,'cada reintento queda registrado una vez');
    $assert($orders->findOrder($approved['order_number'],$otherUserId)===null,'un usuario ajeno no puede consultar el recibo');
    $receipt = $orders->findOrder($approved['order_number'],$otherUserId,true);
    $assert(is_array($receipt) && count($receipt['items'])===1 && $receipt['payment_status']==='APPROVED','admin puede consultar el detalle y la referencia simulada');

    $cancelSku = (string)$products[1]['sku'];
    $cart->mutate($userId,$guest,'add',$cancelSku,1);
    $cancelTotal = (int)$products[1]['price_cents'];
    $cancelled = $orders->processCheckout($userId,$delivery,bin2hex(random_bytes(32)),bin2hex(random_bytes(32)),'cancel',$cancelTotal);
    $assert($cancelled['order_status']==='CANCELLED' && $cancelled['payment_status']==='CANCELLED','cancelar cierra el pedido con estado consistente');
    $assert((int)$server->query('SELECT stock FROM products WHERE id=' . (int)$products[1]['id'])->fetchColumn()===(int)$products[1]['stock'],'la cancelación no descuenta inventario');
    $assert(count($cart->getCart($userId)['items'])===1,'cancelar conserva el carrito para decidir después');

    $cart->mutate($userId,$guest,'clear');
    $expectOrderError(static fn()=>$orders->processCheckout($userId,$delivery,bin2hex(random_bytes(32)),bin2hex(random_bytes(32)),'approve',0),422,'checkout rechaza un carrito vacío');
    $cart->mutate($userId,$guest,'add',$sku,1);
    $beforeCount=(int)$server->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $expectOrderError(static fn()=>$orders->processCheckout($userId,$delivery,bin2hex(random_bytes(32)),bin2hex(random_bytes(32)),'approve',1),409,'total esperado manipulado no determina el importe');
    $assert((int)$server->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$beforeCount,'un total desactualizado no crea pedido');

    $server->exec('CREATE TRIGGER fail_stock_movement BEFORE INSERT ON stock_movements FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'checkout rollback test\'');
    $stockBeforeRollback=(int)$server->query('SELECT stock FROM products WHERE id=' . $productId)->fetchColumn();
    $beforeCount=(int)$server->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    try { $orders->processCheckout($userId,$delivery,bin2hex(random_bytes(32)),bin2hex(random_bytes(32)),'approve',(int)$products[0]['price_cents']); throw new RuntimeException('FALLÓ: error forzado debía abortar la compra.'); }
    catch (PDOException) { $assert(true,'error posterior a reservar stock provoca rollback'); }
    $server->exec('DROP TRIGGER fail_stock_movement');
    $assert((int)$server->query('SELECT stock FROM products WHERE id=' . $productId)->fetchColumn()===$stockBeforeRollback,'rollback restaura el stock');
    $assert((int)$server->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$beforeCount,'rollback elimina el pedido parcial');
    $assert(count($cart->getCart($userId)['items'])===1,'rollback conserva el carrito');

    echo "Checkout aislado aprobado: {$assertions} aserciones; rechazo, aprobación, cancelación, reintentos, autorización, stock y rollback.\n";
} catch (Throwable $exception) {
    fwrite(STDERR,'ERROR: ' . $exception->getMessage() . "\n");
    $exitCode=1;
} finally {
    if (isset($server) && $created) {
        try { $server->exec('DROP DATABASE IF EXISTS `' . $database . '`'); echo "Limpieza: solo se eliminó la BD temporal {$database}.\n"; }
        catch (Throwable $cleanupError) { fwrite(STDERR,"Aviso: no se pudo limpiar la BD temporal {$database}.\n"); $exitCode=1; }
    }
}
if (isset($exitCode)) exit($exitCode);
