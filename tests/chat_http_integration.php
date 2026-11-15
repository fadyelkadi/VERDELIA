<?php
declare(strict_types=1);

// Start VERDELIA against a fresh demo database and set VERDELIA_TEST_BASE_URL.
$base = rtrim((string)getenv('VERDELIA_TEST_BASE_URL'), '/');
if (PHP_SAPI !== 'cli' || !extension_loaded('curl') || !preg_match('~^http://127\.0\.0\.1:\d{1,5}$~D', $base)) {
    fwrite(STDERR, "Se requiere PHP CLI con curl y VERDELIA_TEST_BASE_URL en 127.0.0.1 con puerto explícito.\n");
    exit(2);
}

$client = curl_init();
curl_setopt_array($client, [
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_COOKIEFILE=>'',
    CURLOPT_CONNECTTIMEOUT=>2,
    CURLOPT_TIMEOUT=>5,
    CURLOPT_URL=>$base.'/tienda',
]);
$page = curl_exec($client);
if (!is_string($page) || curl_getinfo($client, CURLINFO_RESPONSE_CODE) !== 200
    || !preg_match('/<meta name="csrf-token" content="([a-f0-9]{64})"/', $page, $token)) {
    throw new RuntimeException('La tienda de prueba no respondió con una sesión válida.');
}

$post = static function (array $payload, bool $includeToken = true) use ($client, $base, $token): array {
    $headers = ['Content-Type: application/json'];
    if ($includeToken) $headers[] = 'X-CSRF-Token: '.$token[1];
    curl_setopt_array($client, [
        CURLOPT_URL=>$base.'/api/chat',
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_POSTFIELDS=>json_encode($payload, JSON_THROW_ON_ERROR),
    ]);
    $body = curl_exec($client);
    if (!is_string($body)) throw new RuntimeException(curl_error($client));
    return [(int)curl_getinfo($client, CURLINFO_RESPONSE_CODE), json_decode($body, true, 16, JSON_THROW_ON_ERROR)];
};
$checks = 0;
$assert = static function (bool $condition, string $name, array $reply = []) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('FALLÓ: '.$name.' '.json_encode($reply, JSON_UNESCAPED_UNICODE));
    }
    $checks++;
};
$ask = static function (string $message, ?string $context = null) use ($post, $assert): array {
    [$status, $reply] = $post(['message'=>$message, 'product_context'=>$context]);
    $assert($status === 200 && ($reply['ok'] ?? false) === true, 'responde por HTTP: '.$message, $reply);
    return $reply;
};
$paths = static fn(array $reply): array => array_column($reply['products'] ?? [], 'url');
$monstera = '/producto/monstera-deliciosa';
$aloe = '/producto/aloe-vera-compacta';
$cactus = '/producto/cactus-mini-desierto';
$bambu = '/producto/bambu-de-la-suerte';
$sansevieria = '/producto/sansevieria';

try {
    foreach (['Una planta para poca luz que sea segura para mi gato', '¿Es pet friendly la Monstera?', 'Para mi gata, ¿cuál recomiendas para poca luz?', 'Una planta con la que no se intoxique mi gatico'] as $message) {
        $reply = $ask($message, 'monstera-deliciosa');
        $assert($paths($reply) === [] && str_contains($reply['message'], 'información verificada'), 'petición de seguridad sin ficha verificada', $reply);
    }

    foreach (['Una planta que no reciba sol directo', 'No quiero luz directa, ¿qué me recomiendas?', 'Una planta de poca luz sin sol directo', '¿Qué plantas recomiendas sin luz solar directa?', 'Quiero que no esté expuesta a la luz directa'] as $message) {
        $reply = $ask($message);
        $assert($paths($reply) !== [] && !in_array($cactus, $paths($reply), true)
            && str_contains($reply['message'], 'sin sol directo'), 'respeta la exclusión de sol', $reply);
    }
    $sun = $ask('No quiero una planta para poca luz, me da el sol todo el día');
    $assert($paths($sun) === [$cactus], 'una condición negada no prevalece sobre la luz solicitada', $sun);
    $dark = $ask('Recomiéndame una planta fácil para un cuarto sin ventanas');
    $assert($paths($dark) === [] && str_contains($dark['message'], 'sin ventanas'), 'no sugiere luz natural inexistente', $dark);

    $none = $ask('Recomiéndame una planta para poca luz por menos de 10 dólares');
    $assert($paths($none) === [] && str_contains($none['message'], 'todos los criterios'), 'no ignora un presupuesto imposible', $none);
    $inclusive = $ask('Recomienda una planta sin sol directo de hasta $18,00');
    $assert(in_array($bambu, $paths($inclusive), true) && !in_array($monstera, $paths($inclusive), true)
        && str_contains($inclusive['message'], '$18.00'), 'límite inclusivo con decimales', $inclusive);
    $exclusive = $ask('Recomienda una planta sin sol directo por menos de $18');
    $assert($paths($exclusive) === [$aloe], 'menos de excluye el precio exacto', $exclusive);
    $currency = $ask('Recomiéndame una planta para poca luz por menos de 30 euros');
    $assert($paths($currency) === [] && str_contains($currency['message'], 'USD'), 'no asume conversión de moneda', $currency);
    $negatedBudget = $ask('Recomiéndame una planta sin sol directo que no cueste más de 18 USD');
    $assert(in_array($bambu, $paths($negatedBudget), true) && !in_array($monstera, $paths($negatedBudget), true),
        'una negación de precio actúa como máximo', $negatedBudget);

    $named = $ask('Buenas, ¿me podría decir cuánto cuesta la Monstera?');
    $assert($paths($named) === [$monstera] && str_contains($named['message'], '$42.00'), 'consulta natural de precio', $named);
    $followUp = $ask('¿Y cuántas quedan?', $named['product_context']);
    $assert($paths($followUp) === [$monstera] && str_contains($followUp['message'], '14 unidades'), 'conserva el referente del seguimiento', $followUp);
    $unknown = $ask('¿Y cuántas quedan de Jazmín?', $named['product_context']);
    $assert($paths($unknown) === [], 'no usa un contexto obsoleto para un producto explícito desconocido', $unknown);
    $care = $ask('¿Cada cuánto riego la Monstera?');
    $assert($paths($care) === [$monstera] && str_contains($care['message'], 'no especifica la frecuencia de riego'), 'no inventa consejos de riego', $care);
    foreach (['¿Tienen 20 monsteras?', '¿Tienen 20 de PLA-006?'] as $message) {
        $stock = $ask($message);
        $assert($paths($stock) === [$monstera] && str_contains($stock['message'], 'No hay 20 unidades'), 'cantidad mayor que la existencia', $stock);
    }
    $plural = $ask('¿Cuánto cuestan las sansevierias?');
    $assert($paths($plural) === [$sansevieria] && str_contains($plural['message'], '$27.50'), 'reconoce el plural', $plural);
    $typo = $ask('¿Cuánto cuesta la mostrera?');
    $assert($paths($typo) === [] && str_contains($typo['message'], '¿Te refieres a Monstera'), 'pide confirmación de una errata', $typo);
    $both = $ask('¿Cuánto cuestan la Monstera y el Aloe?');
    $assert(count($paths($both)) === 2 && in_array($monstera, $paths($both), true)
        && in_array($aloe, $paths($both), true) && $both['product_context'] === null, 'consulta dos productos sin asignar contexto ambiguo', $both);
    $excluded = $ask('No quiero la Monstera, dime el precio del Aloe');
    $assert($paths($excluded) === [$aloe], 'exclusión expresa de producto', $excluded);

    $forecast = $ask('Tendencia de demanda del Aloe');
    $assert(isset($forecast['engine']) && in_array($forecast['engine'], ['php', 'python'], true)
        && str_contains($forecast['message'], 'regresión lineal'), 'regresión con historial de la demo', $forecast);
    $long = $ask('¿Cuál es la previsión de Aloe Vera Compacta para dentro de 12 meses?');
    $assert(!isset($long['engine']) && str_contains($long['message'], 'Solo puedo estimar un mes'), 'explica que el modelo no cubre un año', $long);
    $next = $ask('¿Cuántos Aloe creen que venderán el mes que viene?');
    $assert(str_contains($next['message'], 'No tengo una proyección para el mes que viene'), 'distingue el horizonte del calendario', $next);
    $explain = $ask('¿Y cómo llegaron a ese número?', $forecast['product_context']);
    $assert(str_contains($explain['message'], 'seis meses completos'), 'explica el cálculo como seguimiento', $explain);

    [$denied] = $post(['message'=>'stock PLA-006'], false);
    $assert($denied === 419, 'mantiene la protección CSRF');
    [$status, $spoofed] = $post(['message'=>'stock PLA-006', 'role'=>'ADMIN', 'action'=>'update_stock', 'stock'=>99999]);
    $assert($status === 200 && str_contains($spoofed['message'], '14 unidades') && !str_contains($spoofed['message'], '99999'),
        'ignora solicitudes de mutación y consulta el stock de la base', $spoofed);
    echo "Chat HTTP aprobado: {$checks} aserciones.\n";
} finally {
    curl_close($client);
}
