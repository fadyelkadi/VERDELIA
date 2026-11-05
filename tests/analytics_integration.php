<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'||PHP_VERSION_ID<80000||!extension_loaded('pdo_mysql')){fwrite(STDERR,"Se requiere PHP CLI 8+ con pdo_mysql.\n");exit(1);}
require dirname(__DIR__).'/app/AnalyticsModels.php';
require dirname(__DIR__).'/app/AnalyticsRepository.php';
require dirname(__DIR__).'/scripts/migrations.php';
$config=require dirname(__DIR__).'/config/database.php';
$backup=$argv[1]??dirname(__DIR__).'/database/backups/verdelia_20261104_024900.sql';
$database='verdelia_analytics_test_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));$created=false;$assertions=0;$exitCode=0;
$assert=static function(bool $ok,string $message)use(&$assertions):void{if(!$ok)throw new RuntimeException('FALLÓ: '.$message);$assertions++;};
$expectInvalid=static function(callable $fn,string $message)use($assert):void{try{$fn();}catch(InvalidArgumentException){$assert(true,$message);return;}$assert(false,$message.' (no se rechazó)');};
try{
    $server=new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4',$config['host'],$config['port']),$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5]);
    $sql=file_get_contents($backup);if($sql===false)throw new RuntimeException('No se pudo leer el respaldo anterior a analytics_v1.');
    $sql=preg_replace('/CREATE DATABASE IF NOT EXISTS `verdelia_demo`/','CREATE DATABASE IF NOT EXISTS `'.$database.'`',$sql,1,$createCount);
    $sql=preg_replace('/USE `verdelia_demo`/','USE `'.$database.'`',$sql,1,$useCount);
    if($createCount!==1||$useCount!==1||!is_string($sql))throw new RuntimeException('Respaldo VERDELIA inválido.');
    $server->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)as$statement)if(trim($statement)!=='')$server->exec($statement);
    $server->exec('USE `'.$database.'`');verdelia_apply_migrations($server);verdelia_apply_migrations($server);
    $assert((int)$server->query("SELECT COUNT(*) FROM schema_versions WHERE version='analytics_v1'")->fetchColumn()===1,'migración analytics idempotente');
    $analyticsIndexes=$server->query("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND INDEX_NAME='idx_orders_analytics' ORDER BY SEQ_IN_INDEX")->fetchAll(PDO::FETCH_COLUMN);
    $assert($analyticsIndexes===['status','confirmed_at','id'],'índice por estado y fecha confirmada creado con columnas en el orden esperado');
    $backfill=(int)$server->query('SELECT COUNT(*) FROM order_items WHERE category_name_snapshot IS NULL')->fetchColumn();
    $assert($backfill===0,'categoría histórica completada para las líneas existentes');

    $repository=new AnalyticsRepository($server);
    $bounds=$server->query("SELECT DATE(MIN(confirmed_at)) AS first_day,DATE(MAX(confirmed_at)) AS last_day FROM orders WHERE status IN ('PAID','FULFILLED') AND confirmed_at IS NOT NULL")->fetch();
    if(!is_array($bounds)||!is_string($bounds['first_day'])||!is_string($bounds['last_day']))throw new RuntimeException('No hay historial confirmado en el respaldo.');
    $report=$repository->analyze($bounds['first_day'],$bounds['last_day'],'month');
    $expected=$server->prepare("SELECT COUNT(DISTINCT o.id) AS confirmed_orders,COALESCE(SUM(oi.line_total_cents),0) AS revenue_cents,COALESCE(SUM(oi.unit_cost_cents*oi.quantity),0) AS cogs_cents,COALESCE(SUM(oi.quantity),0) AS units FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE o.status IN ('PAID','FULFILLED') AND o.confirmed_at>=? AND o.confirmed_at<? AND EXISTS(SELECT 1 FROM payments p WHERE p.order_id=o.id AND p.status='APPROVED')");
    $expected->execute([$bounds['first_day'].' 00:00:00',(new DateTimeImmutable($bounds['last_day']))->modify('+1 day')->format('Y-m-d 00:00:00')]);$expectedRow=$expected->fetch();
    foreach(['confirmed_orders','revenue_cents','cogs_cents','units']as$key)$assert((int)$report['metrics'][$key]===(int)$expectedRow[$key],'métrica '.$key.' coincide con consulta SQL independiente');
    $assert(array_sum(array_column($report['series'],'revenue_cents'))===$report['metrics']['revenue_cents'],'serie temporal suma los mismos ingresos');
    $assert(array_sum(array_column($report['categories'],'revenue_cents'))===$report['metrics']['revenue_cents'],'distribución por categoría suma los ingresos');
    $assert(count($report['products'])>0&&count($report['categories'])>0,'productos y categorías más vendidos vienen de datos persistidos');
    $assert(count($report['series'])>=6,'intervalo histórico agrupa meses y rellena períodos sin ventas');
    $assert(count($report['series'][0])>0,'serie preparada para barras y evolución');

    $before=(string)$server->query("SELECT category_name_snapshot FROM order_items WHERE category_name_snapshot IS NOT NULL ORDER BY id LIMIT 1")->fetchColumn();
    $categoryId=(int)$server->query("SELECT category_id FROM products p JOIN order_items oi ON oi.product_id=p.id WHERE oi.category_name_snapshot=".$server->quote($before).' LIMIT 1')->fetchColumn();
    if($categoryId>0){$server->prepare('UPDATE categories SET name=? WHERE id=?')->execute(['Renombrada solo en prueba '.$database,$categoryId]);$after=$repository->analyze($bounds['first_day'],$bounds['last_day'],'month');$assert(in_array($before,array_column($after['categories'],'category'),true),'categoría histórica usa snapshot aunque cambie el nombre actual');}

    $daily=$repository->analyze($bounds['first_day'],$bounds['last_day'],'day');
    $assert(count($daily['series'])===(int)(new DateTimeImmutable($bounds['first_day']))->diff(new DateTimeImmutable($bounds['last_day']))->days+1,'agrupación diaria incluye intervalos sin ventas');
    $emptyFrom=(new DateTimeImmutable($bounds['last_day']))->modify('+2 days')->format('Y-m-d');$emptyTo=(new DateTimeImmutable($emptyFrom))->modify('+9 days')->format('Y-m-d');
    $empty=$repository->analyze($emptyFrom,$emptyTo,'week');
    $assert($empty['metrics']['revenue_cents']===0&&$empty['metrics']['confirmed_orders']===0,'intervalo sin ventas devuelve ceros finitos');
    $assert(!$empty['models']['exponential']['available']&&!$empty['models']['linear']['available'],'intervalo vacío no inventa regresiones');
    $expectInvalid(static fn()=>$repository->analyze('2026-02-31','2026-03-02','day'),'fecha calendario inválida rechazada');
    $expectInvalid(static fn()=>$repository->analyze('2026-06-02','2026-06-01','day'),'intervalo invertido rechazado');
    $expectInvalid(static fn()=>$repository->analyze('2020-01-01','2026-01-01','day'),'intervalo mayor de 730 días rechazado');
    echo "Analítica MariaDB aislada aprobada: {$assertions} aserciones; consultas reales, filtros, snapshots, agregados y migración.\n";
}catch(Throwable $e){fwrite(STDERR,'ERROR: '.$e->getMessage()."\n");$exitCode=1;}
finally{if(isset($server)&&$created)try{$server->exec('DROP DATABASE IF EXISTS `'.$database.'`');echo "Limpieza: solo se eliminó la BD temporal {$database}.\n";}catch(Throwable){fwrite(STDERR,"ERROR: no se pudo limpiar la BD temporal {$database}.\n");$exitCode=1;}}
if($exitCode!==0)exit($exitCode);
