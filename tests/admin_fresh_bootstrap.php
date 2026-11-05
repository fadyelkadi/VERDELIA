<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_VERSION_ID<80000||!extension_loaded('pdo_mysql')){fwrite(STDERR,"Se requiere PHP CLI 8+ con pdo_mysql.\n");exit(1);}
$root=dirname(__DIR__);$config=require $root.'/config/database.php';$database='verdelia_fresh_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));$created=false;$assertions=0;$exitCode=0;
$assert=static function(bool $condition,string $message)use(&$assertions):void{if(!$condition)throw new RuntimeException('FALLÓ: '.$message);$assertions++;};
try{
    $server=new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4',$config['host'],$config['port']),$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5]);
    $environment=getenv();if(!is_array($environment))$environment=[];$environment['VERDELIA_DB_HOST']=$config['host'];$environment['VERDELIA_DB_PORT']=(string)$config['port'];$environment['VERDELIA_DB_USER']=$config['user'];$environment['VERDELIA_DB_PASSWORD']=$config['password'];$environment['VERDELIA_DB_NAME']=$database;
    $run=static function()use($root,$environment):void{$spec=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];$process=proc_open([PHP_BINARY,$root.'/scripts/bootstrap.php'],$spec,$pipes,$root,$environment);if(!is_resource($process))throw new RuntimeException('No se pudo iniciar bootstrap CLI.');fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);if($status!==0)throw new RuntimeException('Bootstrap falló ('.$status.'): '.$stdout.$stderr);};
    $created=true;$run();$server->exec('USE `'.$database.'`');
    $tables=(int)$server->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn();
    $products=(int)$server->query('SELECT COUNT(*) FROM products')->fetchColumn();$images=(int)$server->query('SELECT COUNT(*) FROM product_images')->fetchColumn();$users=(int)$server->query('SELECT COUNT(*) FROM users')->fetchColumn();$orders=(int)$server->query('SELECT COUNT(*) FROM orders')->fetchColumn();$payments=(int)$server->query('SELECT COUNT(*) FROM payments')->fetchColumn();
    $versions=(int)$server->query('SELECT COUNT(*) FROM schema_versions WHERE version IN (\'demo_seed_v1\',\'catalog_images_v1\',\'checkout_v1\',\'admin_v1\',\'analytics_v1\')')->fetchColumn();
    $snapshots=(int)$server->query('SELECT COUNT(*) FROM order_items WHERE category_name_snapshot IS NOT NULL')->fetchColumn();
    $assert($tables===12&&$products===12&&$images===12&&$users===2&&$orders===96&&$payments===96&&$versions===5&&$snapshots===96,'primer bootstrap crea esquema, admin_v1, analytics_v1 y snapshots esperados');
    $run();
    $assert((int)$server->query('SELECT COUNT(*) FROM products')->fetchColumn()===12&&(int)$server->query('SELECT COUNT(*) FROM orders')->fetchColumn()===96,'segundo bootstrap no duplica productos ni pedidos');
    $assert((int)$server->query('SELECT COUNT(*) FROM schema_versions WHERE version=\'admin_v1\'')->fetchColumn()===1,'migración admin no se duplica');
    $assert((int)$server->query('SELECT COUNT(*) FROM schema_versions WHERE version=\'analytics_v1\'')->fetchColumn()===1,'migración analytics no se duplica');
    echo "Bootstrap limpio/idempotente aprobado: {$assertions} aserciones; base temporal nueva.\n";
}catch(Throwable $e){fwrite(STDERR,'ERROR: '.$e->getMessage()."\n");$exitCode=1;}
finally{if(isset($server)&&$created)try{$server->exec('DROP DATABASE IF EXISTS `'.$database.'`');echo "Limpieza: solo se eliminó la BD temporal {$database}.\n";}catch(Throwable){fwrite(STDERR,"ERROR: no se pudo limpiar la BD temporal {$database}.\n");$exitCode=1;}}
if($exitCode!==0)exit($exitCode);
