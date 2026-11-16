<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/helpers.php';
require dirname(__DIR__).'/app/CatalogRepository.php';
require dirname(__DIR__).'/app/ShopChatQuery.php';
require dirname(__DIR__).'/app/ShopChat.php';

$pdo = new PDO('sqlite::memory:');
$chat = new ShopChat(new CatalogRepository($pdo));
$inheritHistory = new ReflectionMethod(ShopChat::class, 'inheritRecommendationHistory');
$answer = new ReflectionMethod(ShopChat::class, 'answer');
$inheritHistory->setAccessible(true);
$answer->setAccessible(true);
$checks = 0;
$assert = static function (bool $ok, string $name, array $reply = []) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FALLÓ: '.$name.' '.json_encode($reply, JSON_UNESCAPED_UNICODE));
    $checks++;
};

$products = [
    ['id'=>1,'sku'=>'PLA-001','slug'=>'aloe-vera-compacta','name'=>'Aloe Vera Compacta','scientific_name'=>null,'price_cents'=>1490,'stock'=>24,'light'=>'Indirecta brillante','care_level'=>'Fácil','category'=>'suculentas','category_name'=>'Suculentas'],
    ['id'=>2,'sku'=>'PLA-002','slug'=>'cactus-mini-desierto','name'=>'Cactus Mini Desierto','scientific_name'=>null,'price_cents'=>1150,'stock'=>35,'light'=>'Luz directa','care_level'=>'Fácil','category'=>'suculentas','category_name'=>'Suculentas'],
    ['id'=>3,'sku'=>'PLA-003','slug'=>'bambu-de-la-suerte','name'=>'Bambú de la Suerte','scientific_name'=>null,'price_cents'=>1800,'stock'=>18,'light'=>'Luz indirecta','care_level'=>'Fácil','category'=>'interior','category_name'=>'Plantas de interior'],
    ['id'=>7,'sku'=>'PLA-007','slug'=>'sansevieria','name'=>'Sansevieria','scientific_name'=>null,'price_cents'=>2750,'stock'=>21,'light'=>'Baja a media','care_level'=>'Fácil','category'=>'interior','category_name'=>'Plantas de interior'],
];

$humidRequest = 'Tengo una casa cerrada, con poca ventilación y mucha humedad. ¿Qué me recomiendas?';
$lowLightEasy = 'Hay poca luz, no tengo presupuesto. Me gustaría que fuera fácil de cuidar.';
$first = new ShopChatQuery($humidRequest);
$firstReply = $answer->invoke($chat, $first, $products, null);
$assert(str_contains($firstReply['message'], 'mucha humedad') && str_contains($firstReply['message'], 'poca ventilación')
    && str_contains($firstReply['message'], '¿Qué luz recibe'), 'reconoce ambiente y pregunta la luz faltante', $firstReply);

$second = new ShopChatQuery($lowLightEasy);
$inheritHistory->invoke($chat, $second, [$humidRequest], $products);
$secondReply = $answer->invoke($chat, $second, $products, null);
$assert(str_contains($secondReply['message'], 'no tienes un límite definido')
    && str_contains($secondReply['message'], 'buscas algo gratis'), 'aclara presupuesto sin perder el contexto', $secondReply);

$third = new ShopChatQuery('entre 10 y 20 dólares');
$inheritHistory->invoke($chat, $third, [$humidRequest, $lowLightEasy], $products);
$thirdReply = $answer->invoke($chat, $third, $products, null);
$paths = array_column($thirdReply['products'] ?? [], 'url');
$assert($paths === ['/producto/sansevieria'] && str_contains($thirdReply['message'], '$27.50')
    && str_contains($thirdReply['message'], 'fuera del rango')
    && str_contains($thirdReply['message'], 'no registran tolerancia a humedad ni ventilación')
    && !in_array('/producto/aloe-vera-compacta', $paths, true)
    && !in_array('/producto/cactus-mini-desierto', $paths, true), 'retiene criterios, descarta falsos ajustes e informa la limitación ambiental', $thirdReply);

$fresh = new ShopChatQuery('Recomiéndame una planta para sol directo hasta $20');
$inheritHistory->invoke($chat, $fresh, [$humidRequest, $lowLightEasy, 'entre 10 y 20 dólares'], $products);
$freshReply = $answer->invoke($chat, $fresh, $products, null);
$assert(array_column($freshReply['products'] ?? [], 'url') === ['/producto/cactus-mini-desierto']
    && !str_contains($freshReply['message'], 'humedad'), 'reinicia las preferencias al iniciar otra recomendación', $freshReply);

$darkRequest = 'Recomiéndame una planta fácil para un cuarto sin ventanas';
$darkFollowUp = new ShopChatQuery('hasta $20');
$inheritHistory->invoke($chat, $darkFollowUp, [$darkRequest], $products);
$darkReply = $answer->invoke($chat, $darkFollowUp, $products, null);
$assert(($darkReply['products'] ?? []) === [] && str_contains($darkReply['message'], 'sin ventanas'),
    'no pierde la condición crítica de falta de luz natural', $darkReply);

echo "Chat recomendaciones aprobado: {$checks} aserciones.\n";
