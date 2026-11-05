<?php
declare(strict_types=1);

if (PHP_SAPI!=='cli' || PHP_VERSION_ID<80000 || !extension_loaded('pdo_mysql')) { fwrite(STDERR,"Se requiere PHP CLI 8+ con pdo_mysql.\n"); exit(1); }
require dirname(__DIR__).'/app/CatalogRepository.php';
require dirname(__DIR__).'/app/CartRepository.php';
require dirname(__DIR__).'/app/OrderRepository.php';
require dirname(__DIR__).'/app/AdminRepository.php';
require dirname(__DIR__).'/scripts/migrations.php';
$config=require dirname(__DIR__).'/config/database.php';
$backup=$argv[1]??dirname(__DIR__).'/database/backups/verdelia_20261103_183812.sql';
$database='verdelia_admin_test_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3)); $created=false; $assertions=0;
$assert=static function(bool $ok,string $message)use(&$assertions):void{if(!$ok)throw new RuntimeException('FALLÓ: '.$message);$assertions++;};
$expect=static function(callable $fn,string $message,int $status=400)use($assert):void{try{$fn();}catch(AdminException $e){$assert($e->httpStatus===$status,$message.' (HTTP '.$e->httpStatus.')');return;} $assert(false,$message.' (no se rechazó)');};
$expectCart=static function(callable $fn,string $message)use($assert):void{try{$fn();}catch(CartException){$assert(true,$message);return;} $assert(false,$message.' (no se rechazó)');};
try {
    $server=new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4',$config['host'],$config['port']),$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5]);
    $sql=file_get_contents($backup); if($sql===false)throw new RuntimeException('No se pudo leer el respaldo de base aislada.');
    $sql=preg_replace('/CREATE DATABASE IF NOT EXISTS `verdelia_demo`/','CREATE DATABASE IF NOT EXISTS `'.$database.'`',$sql,1,$createCount);
    $sql=preg_replace('/USE `verdelia_demo`/','USE `'.$database.'`',$sql,1,$useCount);
    if($createCount!==1||$useCount!==1||!is_string($sql))throw new RuntimeException('Respaldo VERDELIA inválido.');
    $server->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)as$statement)if(trim($statement)!=='')$server->exec($statement);
    $server->exec('USE `'.$database.'`'); verdelia_apply_migrations($server); verdelia_apply_migrations($server);
    $assert((int)$server->query("SELECT COUNT(*) FROM schema_versions WHERE version='admin_v1'")->fetchColumn()===1,'migración admin idempotente');
    $admin=new AdminRepository($server); $clientId=(int)$server->query("SELECT id FROM users WHERE email='cliente@verdelia.test'")->fetchColumn(); $adminId=(int)$server->query("SELECT id FROM users WHERE email='admin@verdelia.test'")->fetchColumn();
    $assert($clientId>0&&$adminId>0,'restore conserva cuentas demo');
    $dash=$admin->dashboard(); $assert((int)$dash['active_products']===12&&(int)$dash['orders_total']===96,'dashboard cuenta datos persistidos reales');
    $sales=(int)$server->query("SELECT COALESCE(SUM(o.total_cents),0) FROM orders o WHERE o.status IN ('PAID','FULFILLED') AND EXISTS(SELECT 1 FROM payments p WHERE p.order_id=o.id AND p.status='APPROVED')")->fetchColumn();
    $assert((int)$dash['confirmed_sales']===$sales,'ventas del dashboard solo incluyen pago aprobado y estado pagado/entregado');
    $assert(count($admin->products('','',1,5)['items'])===5,'listado de productos paginado');
    $assert(count($admin->orders('','',1,5)['items'])===5,'listado de pedidos paginado');
    $assert($admin->orders('DEMO-0009','',1,15)['total']===7,'búsqueda de pedidos por prefijo de identificador');

    $imageOptions=$admin->imageOptions(); if($imageOptions===[])throw new RuntimeException('No hay imágenes locales disponibles en BD para probar.');
    $image=basename((string)$imageOptions[0]['local_path']); $categoryId=(int)$server->query("SELECT id FROM categories WHERE slug='interior'")->fetchColumn();
    $productInput=['sku'=>'ADM-901','slug'=>'planta-prueba-admin','name'=>'Planta de prueba admin','scientific_name'=>'Planta test','description'=>'Registro temporal de integración','category_id'=>(string)$categoryId,'price'=>'10.00','cost'=>'4.25','initial_stock'=>'8','light'=>'Indirecta','care_level'=>'Fácil','image'=>$image,'featured'=>'1','active'=>'1'];
    $productId=$admin->saveProduct($productInput); $saved=$admin->product($productId);
    $assert((int)$saved['stock']===8&&(int)$saved['price_cents']===1000&&(int)$saved['active']===1,'crear producto persiste precio, costo, estado e inventario');
    $assert((int)$server->query("SELECT COUNT(*) FROM stock_movements WHERE product_id=$productId AND reason='stock inicial admin' AND delta=8")->fetchColumn()===1,'stock inicial genera movimiento auditable');
    $catalog=new CatalogRepository($server); $assert($catalog->bySlug('planta-prueba-admin')!==null,'producto creado aparece en catálogo');
    $expect(static fn()=>$admin->saveProduct($productInput+['sku'=>'ADM-902']), 'datos duplicados se rechazan',409);
    $negative=$productInput; $negative['sku']='ADM-902'; $negative['slug']='producto-invalido'; $negative['price']='-1';
    $expect(static fn()=>$admin->saveProduct($negative),'precio negativo rechazado');
    $badStock=$productInput; $badStock['sku']='ADM-903'; $badStock['slug']='stock-invalido'; $badStock['initial_stock']='-2';
    $expect(static fn()=>$admin->saveProduct($badStock),'stock inicial negativo rechazado');
    $productInput['id']=(string)$productId; $productInput['sku']='ADM-901'; $productInput['slug']='planta-prueba-admin'; $productInput['name']='Planta Admin Editada'; $productInput['price']='12.50'; unset($productInput['initial_stock']);
    $admin->saveProduct($productInput); $assert((int)$admin->product($productId)['price_cents']===1250,'edición actualiza precio y descripción actuales');

    $cart=new CartRepository($server); $guest=[]; $cart->mutate($clientId,$guest,'add','ADM-901',2);
    $cartSummary=$cart->getCart($clientId); $assert((int)$cartSummary['total_cents']===2500,'carrito vuelve a consultar el precio administrativo nuevo');
    $admin->adjustStock($productId,8,2,'Recepción temporal');
    $assert((int)$admin->product($productId)['stock']===10,'ajuste de stock actualiza existencias');
    $expect(static fn()=>$admin->adjustStock($productId,8,1,'Valor obsoleto'),'conflicto de edición concurrente se rechaza',409);
    $expect(static fn()=>$admin->adjustStock($productId,10,-11,'Salida excesiva'),'ajuste que llevaría stock a negativo se rechaza');
    $assert(count($admin->movements($productId))===2,'movimientos conservan inicial y ajuste con fecha/motivo');

    $delivery=['name'=>'Cliente Demo','email'=>'cliente@verdelia.test','phone'=>'000','address'=>'Dirección ficticia','city'=>'Caracas','state'=>'Distrito Capital','postal_code'=>'1010','note'=>''];
    $orders=new OrderRepository($server); $checkout=$orders->processCheckout($clientId,$delivery,bin2hex(random_bytes(32)),bin2hex(random_bytes(32)),'approve',2500);
    $line=$server->prepare('SELECT name_snapshot,unit_price_cents FROM order_items WHERE order_id=(SELECT id FROM orders WHERE order_number=?)'); $line->execute([$checkout['order_number']]); $snapshot=$line->fetch();
    $assert($checkout['order_status']==='PAID'&&$snapshot['name_snapshot']==='Planta Admin Editada'&&(int)$snapshot['unit_price_cents']===1250,'checkout usa producto/precio actuales en un pedido nuevo');
    $assert((int)$admin->product($productId)['stock']===8,'checkout aprobado y administración comparten inventario transaccional');
    $admin->setProductActive($productId,false); $assert($catalog->bySlug('planta-prueba-admin')===null,'desactivación lógica oculta la ficha del catálogo');
    $assert((int)$server->query('SELECT COUNT(*) FROM order_items WHERE order_id=(SELECT id FROM orders WHERE order_number='.$server->quote($checkout['order_number']).') AND name_snapshot=\'Planta Admin Editada\' AND unit_price_cents=1250')->fetchColumn()===1,'desactivar/editar no altera snapshots del pedido');
    $expectCart(static fn()=>$cart->mutate($clientId,$guest,'add','ADM-901',1),'carrito rechaza producto desactivado');
    $admin->setProductActive($productId,true); $assert($catalog->bySlug('planta-prueba-admin')!==null,'reactivar producto lo restaura al catálogo');

    $existingLine=$server->query('SELECT p.id,p.category_id,p.sku,p.slug,p.name,p.scientific_name,p.description,p.price_cents,p.cost_cents,p.light,p.care_level,p.featured,p.active,pi.local_path FROM order_items oi JOIN products p ON p.id=oi.product_id LEFT JOIN product_images pi ON pi.product_id=p.id ORDER BY oi.id LIMIT 1')->fetch();
    if(!is_array($existingLine))throw new RuntimeException('El respaldo no contiene líneas históricas vinculadas.');
    $oldSnapshot=$server->query('SELECT name_snapshot,unit_price_cents FROM order_items WHERE product_id='.(int)$existingLine['id'].' ORDER BY id LIMIT 1')->fetch();
    $edit=['id'=>(string)$existingLine['id'],'sku'=>$existingLine['sku'],'slug'=>$existingLine['slug'],'name'=>'Nombre actual editado','scientific_name'=>$existingLine['scientific_name']??'','description'=>$existingLine['description']??'','category_id'=>(string)$existingLine['category_id'],'price'=>number_format(((int)$existingLine['price_cents']+111)/100,2,'.',''),'cost'=>number_format((int)$existingLine['cost_cents']/100,2,'.',''),'light'=>$existingLine['light']??'','care_level'=>$existingLine['care_level']??'','image'=>basename((string)$existingLine['local_path']),'featured'=>(string)$existingLine['featured'],'active'=>'1'];
    $admin->saveProduct($edit); $snapshotAfter=$server->query('SELECT name_snapshot,unit_price_cents FROM order_items WHERE product_id='.(int)$existingLine['id'].' ORDER BY id LIMIT 1')->fetch();
    $assert($snapshotAfter===$oldSnapshot,'editar producto conserva precio y nombre de pedidos históricos');

    $newCategory=$admin->saveCategory(['name'=>'Temporal de prueba','slug'=>'temporal-de-prueba','description'=>'categoría de integración','active'=>'1']);
    $assert($admin->category($newCategory)!==null,'crear categoría persiste');
    $admin->saveCategory(['id'=>(string)$newCategory,'name'=>'Temporal editada','slug'=>'temporal-editada','description'=>'actualizada','active'=>'1']);
    $assert($admin->category($newCategory)['name']==='Temporal editada','editar categoría persiste');
    $expect(static fn()=>$admin->saveCategory(['name'=>'Temporal editada','slug'=>'otro-slug','active'=>'1']),'nombres de categoría duplicados rechazados',409);
    $productInput['category_id']=(string)$newCategory; $admin->saveProduct($productInput); $assert((int)$admin->product($productId)['category_id']===$newCategory,'producto se puede asociar a la nueva categoría');
    $expect(static fn()=>$admin->setCategoryActive($newCategory,false),'categoría asociada a producto activo no se desactiva',409);
    $admin->setProductActive($productId,false);
    $admin->setCategoryActive($newCategory,false); $assert((int)$admin->category($newCategory)['active']===0,'categoría libre se desactiva');
    $usedCategory=(int)$existingLine['category_id']; $expect(static fn()=>$admin->setCategoryActive($usedCategory,false),'categoría con producto activo no se desactiva',409);

    $customers=$admin->customers('cliente@verdelia.test',1,5); $assert(count($customers['items'])>=1,'búsqueda de clientes encuentra correo');
    $assert(!array_key_exists('password_hash',$customers['items'][0]),'listado de clientes no expone hashes');
    $customerOrders=$admin->orders('','',1,10,$clientId); $assert($customerOrders['total']>=97,'pedidos de cliente se filtran y consultan por cuenta');
    $paid=$admin->orders('','PAID',1,1); $orderNumber=(string)$paid['items'][0]['order_number'];
    $admin->transitionOrder($orderNumber,'FULFILLED',$adminId,'Entrega temporal de prueba');
    $assert((string)$server->query('SELECT status FROM orders WHERE order_number='.$server->quote($orderNumber))->fetchColumn()==='FULFILLED','transición PAID a FULFILLED permitida');
    $history=$admin->orderHistory((int)$server->query('SELECT id FROM orders WHERE order_number='.$server->quote($orderNumber))->fetchColumn());
    $assert(count($history)===1&&$history[0]['reason']==='Entrega temporal de prueba','cambio administrativo registra autor, motivo y fecha');
    $expect(static fn()=>$admin->transitionOrder($orderNumber,'CANCELLED',$adminId,'No permitido'),'transición desde entregado rechazada',409);
    $pendingNumber='ADMIN-PENDING-TEST'; $server->prepare("INSERT INTO orders(order_number,user_id,status,subtotal_cents,shipping_cents,total_cents,delivery_address,is_demo,created_at) VALUES(?,?,'PENDING_PAYMENT',100,0,100,'Dirección de prueba',1,NOW())")->execute([$pendingNumber,$clientId]); $pendingId=(int)$server->lastInsertId();
    $server->prepare("INSERT INTO payments(order_id,status,fake_reference,created_at) VALUES(?,'REJECTED',?,NOW())")->execute([$pendingId,'SIM-ADMIN-PENDING']);
    $admin->transitionOrder($pendingNumber,'CANCELLED',$adminId,'Cancelación de prueba');
    $assert((string)$server->query("SELECT status FROM orders WHERE order_number='ADMIN-PENDING-TEST'")->fetchColumn()==='CANCELLED','pedido sin pago aprobado puede cancelarse administrativamente');
    $assert((int)$admin->dashboard()['orders_total']===98,'dashboard refresca contadores después de cambios');
    echo "Admin integration aprobado: {$assertions} aserciones en BD temporal; CRUD, inventario, pedidos, clientes, dashboard y checkout integrado.\n";
} catch(Throwable $e) { fwrite(STDERR,'ERROR: '.$e->getMessage()."\n"); $exitCode=1; }
finally { if(isset($server)&&$created) try{$server->exec('DROP DATABASE IF EXISTS `'.$database.'`');echo "Limpieza: solo se eliminó la BD temporal {$database}.\n";}catch(Throwable){fwrite(STDERR,"ERROR: no se pudo limpiar la BD temporal {$database}.\n");$exitCode=1;} }
if(isset($exitCode))exit($exitCode);
