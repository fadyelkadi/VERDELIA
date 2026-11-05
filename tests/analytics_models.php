<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/AnalyticsModels.php';
$assertions=0;
$assert=static function(bool $ok,string $message)use(&$assertions):void{if(!$ok)throw new RuntimeException('FALLÓ: '.$message);$assertions++;};
$near=static function(?float $actual,float $expected,float $tolerance,string $message)use($assert):void{$assert($actual!==null&&abs($actual-$expected)<=$tolerance,$message);};

$line=AnalyticsModels::linear([2,4,6,8],2);
$assert($line['available'],'ajuste lineal conocido disponible');
$near($line['slope'],2.0,1.0e-9,'pendiente exacta y=2x+2');
$near($line['intercept'],2.0,1.0e-9,'intercepto exacto y=2x+2');
$near($line['r_squared'],1.0,1.0e-9,'R² lineal perfecto');
$near($line['forecast'][0]??null,10.0,1.0e-9,'primera proyección lineal');
$assert(count($line['fitted'])===4&&count($line['forecast'])===2,'valores estimados y proyecciones tienen tamaño esperado');

$exponential=AnalyticsModels::exponential([2,4,8,16],2);
$assert($exponential['available'],'ajuste exponencial positivo disponible');
$near($exponential['a'],2.0,1.0e-9,'parámetro A de y=2·e^(ln2 x)');
$near($exponential['b'],log(2),1.0e-9,'parámetro B exponencial');
$near($exponential['r_squared'],1.0,1.0e-9,'R² exponencial perfecto en escala original');
$near($exponential['forecast'][0]??null,32.0,1.0e-8,'primera proyección exponencial');

$constant=AnalyticsModels::linear([5,5,5]);
$assert($constant['available']&&$constant['slope']===0.0,'serie constante mantiene pendiente cero');
$assert($constant['r_squared']===null&&$constant['rmse']===0.0,'R² no se calcula para una serie constante');
$constantExp=AnalyticsModels::exponential([5,5,5]);
$assert($constantExp['available']&&$constantExp['b']===0.0&&$constantExp['r_squared']===null,'serie exponencial constante no divide por cero');

$assert(!AnalyticsModels::linear([])['available'],'serie vacía no produce modelo');
$assert(!AnalyticsModels::linear([9])['available'],'un solo período no produce pendiente artificial');
$assert(!AnalyticsModels::exponential([3,6])['available'],'menos de tres puntos no ajusta curva exponencial');
$assert(!AnalyticsModels::exponential([1,0,3])['available'],'ventas cero impiden logaritmos exponenciales');
$assert(!AnalyticsModels::exponential([-1,2,3])['available'],'ventas negativas impiden logaritmos exponenciales');
$assert(!AnalyticsModels::linear([INF,1])['available'],'valores infinitos no contaminan el ajuste');
$assert(AnalyticsModels::compare($line,$exponential)['available'],'comparación produce resultado para dos ajustes válidos');
$assert(AnalyticsModels::compare(AnalyticsModels::linear([1]),AnalyticsModels::exponential([1,0,2]))['model']===null,'sin modelos válidos no se elige ganador');

echo "Modelos de analítica aprobados: {$assertions} aserciones.\n";
