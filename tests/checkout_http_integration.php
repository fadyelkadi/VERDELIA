<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR,"Solo CLI\n"); exit(2); }
$root = dirname(__DIR__);
$config = require $root . '/config/database.php';
$backupPath = $argv[1] ?? '';
if ($backupPath === '') { $backups=glob($root.'/database/backups/verdelia_*.sql')?:[]; usort($backups,static fn($a,$b)=>filemtime($b)<=>filemtime($a)); $backupPath=$backups[0]??''; }
if ($backupPath==='' || !is_file($backupPath)) { fwrite(STDERR,"Indica un respaldo SQL para usar una base aislada.\n"); exit(2); }
$database='verdelia_checkout_http_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
$created=false; $process=null; $stdout=null; $stderr=null; $assertions=0; $handles=[];
$assert=static function(bool $condition,string $message) use (&$assertions): void { if(!$condition) throw new RuntimeException('FALLÓ: '.$message); $assertions++; };
$newClient=static function() use (&$handles): CurlHandle { $h=curl_init(); if(!$h instanceof CurlHandle) throw new RuntimeException('No se pudo iniciar cURL.'); curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>10,CURLOPT_COOKIEFILE=>'']); $handles[]=$h; return $h; };
$request=static function(CurlHandle $h,string $base,string $method,string $path,?array $data=null,?string $csrf=null,bool $json=false): array {
    $headers=['Accept: text/html, application/json']; if($csrf!==null) $headers[]='X-CSRF-Token: '.$csrf;
    $location=''; $headers[]='Connection: close';
    $options=[CURLOPT_URL=>$base.$path,CURLOPT_HTTPHEADER=>$headers,CURLOPT_HEADERFUNCTION=>static function($curl,string $line) use (&$location): int { if(stripos($line,'Location:')===0) $location=trim(substr($line,9)); return strlen($line); },CURLOPT_HTTPGET=>$method==='GET',CURLOPT_POST=>false,CURLOPT_CUSTOMREQUEST=>$method];
    if($method==='POST') { $options[CURLOPT_POST]=true; $options[CURLOPT_HTTPGET]=false; $options[CURLOPT_CUSTOMREQUEST]='POST'; if($data!==null){$options[CURLOPT_POSTFIELDS]=$json?json_encode($data,JSON_THROW_ON_ERROR):http_build_query($data);$headers[]=$json?'Content-Type: application/json':'Content-Type: application/x-www-form-urlencoded';$options[CURLOPT_HTTPHEADER]=$headers;} }
    curl_setopt_array($h,$options); $body=curl_exec($h); if(!is_string($body)) throw new RuntimeException('HTTP local falló: '.curl_error($h));
    return ['status'=>(int)curl_getinfo($h,CURLINFO_RESPONSE_CODE),'body'=>$body,'location'=>$location,'url'=>(string)curl_getinfo($h,CURLINFO_EFFECTIVE_URL)];
};
$csrf=static function(string $html): string { if(preg_match('/<meta name="csrf-token" content="([a-f0-9]{64})"/',$html,$m)!==1) throw new RuntimeException('No se encontró token CSRF en HTML.'); return $m[1]; };
$hidden=static function(string $html,string $name): string { $pattern='/name="'.preg_quote($name,'/').'" value="([^"]*)"/'; if(preg_match($pattern,$html,$m)!==1) throw new RuntimeException('Falta campo '.$name.' en checkout.'); return html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8'); };
$formData=static function(string $html,string $outcome) use ($hidden): array { return ['csrf_token'=>$hidden($html,'csrf_token'),'order_key'=>$hidden($html,'order_key'),'attempt_key'=>$hidden($html,'attempt_key'),'expected_total_cents'=>$hidden($html,'expected_total_cents'),'name'=>'Compradora Ficticia','email'=>'demo-cliente@verdelia.test','phone'=>'+58 412 000 0000','address'=>'Avenida de prueba 100','city'=>'Caracas','state'=>'Distrito Capital','postal_code'=>'1010','note'=>'Sin datos reales','payment_outcome'=>$outcome]; };

try {
    $server=new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4',$config['host'],$config['port']),$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5]);
    $sql=file_get_contents($backupPath); if($sql===false) throw new RuntimeException('No se pudo leer el respaldo.');
    $sql=preg_replace('/CREATE DATABASE IF NOT EXISTS `verdelia_demo`/','CREATE DATABASE IF NOT EXISTS `'.$database.'`',$sql,1,$createCount);
    $sql=preg_replace('/USE `verdelia_demo`/','USE `'.$database.'`',$sql,1,$useCount);
    if($createCount!==1||$useCount!==1||!is_string($sql)) throw new RuntimeException('El archivo no es un respaldo VERDELIA esperado.');
    $server->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $created=true;
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement) if(trim($statement)!=='') $server->exec($statement);
    $server->exec('USE `'.$database.'`');
    require $root.'/scripts/migrations.php'; verdelia_apply_migrations($server);
    $salesBefore=(int)$server->query("SELECT COUNT(*) FROM orders WHERE status IN ('PAID','FULFILLED')")->fetchColumn();

    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr); if($socket===false) throw new RuntimeException('No se pudo reservar puerto local de prueba. '.$errstr); $address=stream_socket_get_name($socket,false); fclose($socket);
    if(!is_string($address)||preg_match('/:(\d+)$/',$address,$match)!==1) throw new RuntimeException('No se pudo determinar el puerto temporal.'); $port=(int)$match[1];
    $logPath=$root.'/storage/logs/checkout-http-test-'.$port.'.log'; if(!is_dir(dirname($logPath))) mkdir(dirname($logPath),0775,true);
    $stdout=fopen($logPath,'a'); $stderr=fopen($logPath,'a'); if(!$stdout||!$stderr) throw new RuntimeException('No se pudo crear log temporal bajo storage/logs.');
    $environment=getenv(); if(!is_array($environment)) $environment=[];
    $environment['VERDELIA_DB_HOST']=$config['host']; $environment['VERDELIA_DB_PORT']=(string)$config['port']; $environment['VERDELIA_DB_NAME']=$database; $environment['VERDELIA_DB_USER']=$config['user']; $environment['VERDELIA_DB_PASSWORD']=$config['password'];
    $descriptors=[0=>['pipe','r'],1=>$stdout,2=>$stderr];
    $process=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$root.'/public',$root.'/public/router.php'],$descriptors,$pipes,$root,$environment);
    if(!is_resource($process)) throw new RuntimeException('No se pudo iniciar el servidor PHP temporal.'); fclose($pipes[0]); $base='http://127.0.0.1:'.$port;
    $probe=$newClient(); $ready=false; for($i=0;$i<50;$i++){try{$response=$request($probe,$base,'GET','/');if($response['status']===200){$ready=true;break;}}catch(Throwable){} usleep(100000);} if(!$ready) throw new RuntimeException('El servidor HTTP temporal no respondió.');

    $guest=$newClient(); $home=$request($guest,$base,'GET','/'); $guestCsrf=$csrf($home['body']);
    $add=$request($guest,$base,'POST','/api/cart',['action'=>'add','sku'=>'PLA-001','quantity'=>2,'price_cents'=>1],$guestCsrf,true);
    $addData=json_decode($add['body'],true);
    $assert($add['status']===200 && ($addData['items'][0]['price_cents']??0)===1490,'API ignora el precio que intentó enviar el navegador');
    $checkoutGuest=$request($guest,$base,'GET','/checkout');
    $assert($checkoutGuest['status']===303 && str_contains($checkoutGuest['location'],'/login?next=%2Fcheckout'),'visitante conserva su carrito y recibe retorno seguro a checkout');
    $loginPage=$request($guest,$base,'GET',$checkoutGuest['location']); $loginToken=$csrf($loginPage['body']);
    $login=$request($guest,$base,'POST','/login',['csrf_token'=>$loginToken,'email'=>'cliente@verdelia.test','password'=>'VerdeliaCliente2026!','next'=>'/checkout']);
    $assert($login['status']===303 && $login['location']==='/checkout','login vuelve al checkout sin una URL externa');
    $page=$request($guest,$base,'GET','/checkout');
    $assert($page['status']===200 && str_contains($page['body'],'$29.80') && str_contains($page['body'],'Simular pago rechazado'),'checkout muestra subtotal real y estados explícitos del simulador');
    $first=$formData($page['body'],'reject');
    $missingCsrf=$first; unset($missingCsrf['csrf_token']); $blocked=$request($guest,$base,'POST','/checkout',$missingCsrf);
    $assert($blocked['status']===419,'checkout exige CSRF antes de procesar');
    $invalid=$first; $invalid['phone']='no es teléfono'; $badAddress=$request($guest,$base,'POST','/checkout',$invalid);
    $assert($badAddress['status']===303 && $badAddress['location']==='/checkout','checkout valida datos desde PHP y no crea pedido incompleto');
    $samePage=$request($guest,$base,'GET','/checkout'); $sameOrderKey=$hidden($samePage['body'],'order_key');
    $tampered=$formData($samePage['body'],'approve'); $tampered['expected_total_cents']='1'; $tamperedResult=$request($guest,$base,'POST','/checkout',$tampered);
    $assert($tamperedResult['status']===303 && $tamperedResult['location']==='/checkout','total alterado se invalida y exige revisar antes de confirmar');
    $page=$request($guest,$base,'GET','/checkout'); $reject=$formData($page['body'],'reject');
    $assert($reject['order_key']!==$sameOrderKey,'una revisión de total obsoleto rota la clave de pedido antes de aceptar un nuevo intento');
    $beforeStock=(int)$server->query("SELECT stock FROM products WHERE sku='PLA-001'")->fetchColumn();
    $rejected=$request($guest,$base,'POST','/checkout',$reject);
    $assert($rejected['status']===303 && preg_match('#^/pedido/VD-[0-9]{8}-[A-F0-9]{10}\\?resultado=rechazado$#',$rejected['location'])===1,'pago rechazado lleva a detalle del pedido');
    preg_match('#^/pedido/([^?]+)#',$rejected['location'],$orderMatch); $orderPath=$orderMatch[0]??''; $orderNumber=basename($orderPath);
    $orderPage=$request($guest,$base,'GET',$orderPath);
    $assert($orderPage['status']===200 && str_contains($orderPage['body'],'Rechazado'),'el recibo indica claramente rechazo y mantiene pedido no pagado; status='.$orderPage['status'].' location='.$orderPage['url'].' excerpt='.substr(strip_tags($orderPage['body']),0,400));
    $assert((int)$server->query("SELECT stock FROM products WHERE sku='PLA-001'")->fetchColumn()===$beforeStock,'rechazo HTTP no descuenta stock');
    $page=$request($guest,$base,'GET','/checkout'); $approve=$formData($page['body'],'approve');
    $assert($approve['order_key']===$reject['order_key'] && $approve['attempt_key']!==$reject['attempt_key'],'el nuevo intento reutiliza pedido y obtiene clave propia');
    $approved=$request($guest,$base,'POST','/checkout',$approve);
    $receiptLocation=$approved['location'];
    $assert($approved['status']===303 && preg_match('#^/pedido/VD-[0-9]{8}-[A-F0-9]{10}/recibo$#',$receiptLocation)===1,'aprobación redirige al recibo imprimible');
    $receipt=$request($guest,$base,'GET',$receiptLocation);
    $assert($receipt['status']===200 && str_contains($receipt['body'],'Imprimir o guardar como PDF') && str_contains($receipt['body'],'SIM-'),'recibo contiene referencia y control de impresión');
    $approvedStock=(int)$server->query("SELECT stock FROM products WHERE sku='PLA-001'")->fetchColumn();
    $assert($approvedStock===$beforeStock-2,'aprobación HTTP rebaja stock de manera transaccional');
    $replay=$request($guest,$base,'POST','/checkout',$approve);
    $assert($replay['status']===303 && $replay['location']===$receiptLocation,'doble clic/reenvío reutiliza el resultado aprobado');
    $assert((int)$server->query("SELECT COUNT(*) FROM orders WHERE order_number=".$server->quote($orderNumber))->fetchColumn()===1,'una clave de checkout produce un solo pedido');
    $assert((int)$server->query("SELECT COUNT(*) FROM payments WHERE order_id=(SELECT id FROM orders WHERE order_number=".$server->quote($orderNumber).')')->fetchColumn()===2,'reintento registra exactamente dos intentos simulados');
    $assert((int)$server->query("SELECT COUNT(*) FROM stock_movements WHERE order_id=(SELECT id FROM orders WHERE order_number=".$server->quote($orderNumber).')')->fetchColumn()===1,'el reenvío no duplica movimientos de inventario');
    $empty=$request($guest,$base,'GET','/checkout'); $assert($empty['status']===200 && str_contains($empty['body'],'Tu carrito está vacío'),'carrito aprobado queda vacío al volver al checkout');
    $history=$request($guest,$base,'GET','/cuenta/pedidos'); $assert($history['status']===200 && str_contains($history['body'],$orderNumber),'historial personal incluye el pedido nuevo');

    $userB=$newClient(); $registerPage=$request($userB,$base,'GET','/registro'); $registerToken=$csrf($registerPage['body']);
    $emailB='checkout-http-'.bin2hex(random_bytes(5)).'@verdelia.test';
    $registered=$request($userB,$base,'POST','/registro',['csrf_token'=>$registerToken,'name'=>'Cliente sin acceso','email'=>$emailB,'password'=>'DemoCheckout2026!','password_confirmation'=>'DemoCheckout2026!','academic_demo'=>'1']);
    $assert($registered['status']===303,'segunda cuenta ficticia puede iniciar su sesión de prueba');
    $privateReceipt=$request($userB,$base,'GET',$receiptLocation);
    $assert($privateReceipt['status']===404,'otro cliente no obtiene detalle ni recibo del pedido');
    $admin=$newClient(); $adminLoginPage=$request($admin,$base,'GET','/login'); $adminLogin=$request($admin,$base,'POST','/login',['csrf_token'=>$csrf($adminLoginPage['body']),'email'=>'admin@verdelia.test','password'=>'VerdeliaAdmin2026!']);
    $assert($adminLogin['status']===303 && $adminLogin['location']==='/admin','la cuenta ADMIN conserva su flujo de autenticación');
    $adminOrders=$request($admin,$base,'GET','/admin/pedidos');
    $assert($adminOrders['status']===200 && str_contains($adminOrders['body'],$orderNumber),'ADMIN consulta pedidos reales persistidos');
    $adminReceipt=$request($admin,$base,'GET','/admin/pedidos/'.$orderNumber);
    $assert($adminReceipt['status']===200 && str_contains($adminReceipt['body'],$orderNumber),'ADMIN consulta el detalle y referencias del pedido');
    $clientAdmin=$request($guest,$base,'GET','/admin/pedidos');
    $assert($clientAdmin['status']===403,'cliente no entra a la vista administrativa de pedidos');

    $guestCart=$request($guest,$base,'GET','/api/cart'); $token=$csrf($receipt['body']);
    $addAgain=$request($guest,$base,'POST','/api/cart',['action'=>'add','sku'=>'PLA-002','quantity'=>1],$token,true);
    $assert($addAgain['status']===200,'carrito sigue disponible después de una compra aprobada');
    $cancelStockBefore=(int)$server->query("SELECT stock FROM products WHERE sku='PLA-002'")->fetchColumn();
    $page=$request($guest,$base,'GET','/checkout'); $cancel=$formData($page['body'],'cancel');
    $cancelResult=$request($guest,$base,'POST','/checkout',$cancel);
    $assert($cancelResult['status']===303 && str_contains($cancelResult['location'],'resultado=cancelado'),'cancelación simulator produce estado cancelado');
    $cancelPage=$request($guest,$base,'GET',preg_replace('/\\?resultado=.*/','',$cancelResult['location']));
    $assert($cancelPage['status']===200 && str_contains($cancelPage['body'],'Cancelado · simulación local'),'cancelación queda visible en detalle');
    $assert((int)$server->query("SELECT stock FROM products WHERE sku='PLA-002'")->fetchColumn()===$cancelStockBefore,'la cancelación no realiza un descuento de inventario');
    $assert((int)$server->query("SELECT COUNT(*) FROM orders WHERE status IN ('PAID','FULFILLED')")->fetchColumn()===$salesBefore+1,'rechazo/cancelación no aumentan ventas completadas');

    echo "Checkout HTTP aislado aprobado: {$assertions} aserciones; login, carrito, validación, rechazo, aprobación, cancelación, idempotencia, historial y admin.\n";
} catch(Throwable $exception) {
    fwrite(STDERR,'ERROR: '.$exception->getMessage()."\n");
    if(isset($logPath)&&is_file($logPath)) fwrite(STDERR,"Log local: {$logPath}\n".implode('',array_slice(file($logPath,FILE_IGNORE_NEW_LINES)?:[],-30))."\n");
    $exitCode=1;
} finally {
    foreach($handles as $h) if($h instanceof CurlHandle) curl_close($h);
    if(is_resource($process)){proc_terminate($process);proc_close($process);}
    if(is_resource($stdout)) fclose($stdout); if(is_resource($stderr)) fclose($stderr);
    if(isset($logPath)&&is_file($logPath)) @unlink($logPath);
    if(isset($server)&&$created){try{$server->exec('DROP DATABASE IF EXISTS `'.$database.'`');echo "Limpieza: solo se eliminó la BD temporal {$database}.\n";}catch(Throwable){fwrite(STDERR,"Aviso: no se pudo limpiar la BD temporal {$database}.\n");$exitCode=1;}}
}
if(isset($exitCode)) exit($exitCode);
