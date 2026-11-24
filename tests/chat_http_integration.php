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
$ask = static function (string $message, ?string $context = null, array $history = [], array $options = []) use ($post, $assert): array {
    [$status, $reply] = $post(['message'=>$message, 'product_context'=>$context, 'history'=>$history, 'product_options'=>$options]);
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

    $humidRequest = 'Tengo una casa cerrada, con poca ventilación y mucha humedad. ¿Qué me recomiendas?';
    $humidStart = $ask($humidRequest);
    $assert(str_contains($humidStart['message'], 'mucha humedad') && str_contains($humidStart['message'], 'poca ventilación')
        && $paths($humidStart) !== [] && str_contains($humidStart['follow_up'] ?? '', '¿El lugar recibe poca luz'),
        'muestra opciones con datos de la ficha y deja una pregunta opcional sobre luz', $humidStart);
    $lowLightEasy = 'Hay poca luz, no tengo presupuesto. Me gustaría que fuera fácil de cuidar.';
    $budgetClarifier = $ask($lowLightEasy, null, [$humidRequest]);
    $assert($paths($budgetClarifier) === [$sansevieria] && str_contains($budgetClarifier['message'], 'Bs. REF 27.50')
        && str_contains($budgetClarifier['message'], '21 unidades disponibles')
        && str_contains($budgetClarifier['message'], 'no registran tolerancia a humedad ni ventilación'),
        'interpreta que no hay límite de presupuesto y recomienda con precio y stock', $budgetClarifier);
    $noBudgetFollowUp = $ask('no tengo presupuesto', null, ['Recomiéndame una planta para poca luz hasta $20']);
    $assert($paths($noBudgetFollowUp) === [$sansevieria] && str_contains($noBudgetFollowUp['message'], 'Bs. REF 27.50'),
        'conserva la luz y elimina un límite de presupuesto previo cuando el cliente lo aclara', $noBudgetFollowUp);
    $budgetFollowUp = $ask('entre 10 y 20 dólares', null, [$humidRequest, $lowLightEasy]);
    $assert($paths($budgetFollowUp) === [$sansevieria] && str_contains($budgetFollowUp['message'], 'Bs. REF 27.50')
        && str_contains($budgetFollowUp['message'], 'fuera del rango')
        && str_contains($budgetFollowUp['message'], 'no registran tolerancia a humedad ni ventilación')
        && !in_array($aloe, $paths($budgetFollowUp), true) && !in_array($cactus, $paths($budgetFollowUp), true),
        'conserva criterios y no presenta falsos ajustes al presupuesto o ambiente', $budgetFollowUp);
    $freshRecommendation = $ask('Recomiéndame una planta para sol directo hasta $20', null, [$humidRequest, $lowLightEasy, 'entre 10 y 20 dólares']);
    $assert($paths($freshRecommendation) === [$cactus] && !str_contains($freshRecommendation['message'], 'humedad'),
        'una nueva recomendación empieza con criterios nuevos', $freshRecommendation);

    $patio = $ask('Quiero una planta para el patio, hay buena ilumoinacion y no tengo presupuesto');
    $assert(in_array('/producto/suculenta-echeveria', $paths($patio), true)
        && str_contains($patio['message'], 'Precio: Bs. REF 12.90') && str_contains($patio['message'], '27 unidades disponibles'),
        'entiende patio, error de escritura y muestra una planta en stock con precio', $patio);
    $flower = $ask('quiero una flor');
    $assert($paths($flower) === [] && str_contains($flower['message'], 'flores disponibles')
        && !str_contains($flower['message'], 'No identifiqué el producto') && ($flower['store_link'] ?? '') === '/tienda',
        'reconoce que pide una flor y aclara que no hay flores en el catálogo sin pedir nombre o SKU', $flower);
    $romanticGift = $ask('cual es la planta mas romantica para regalarle a mi novia?', null, ['quiero una flor']);
    $assert(count($paths($romanticGift)) > 0 && count($paths($romanticGift)) <= 4
        && !in_array($cactus, $paths($romanticGift), true)
        && str_contains($romanticGift['intro'] ?? '', 'no asignan un significado romántico')
        && str_contains($romanticGift['intro'] ?? '', 'No hay flores con existencias'),
        'recomienda pocas alternativas ornamentales en stock para un regalo sin inventar simbolismos', $romanticGift);
    $soilTypes = $ask('que tipos de tierras tienen disponibles ?');
    $assert($paths($soilTypes) === [] && str_contains($soilTypes['message'], 'no incluye tierra o sustrato')
        && ($soilTypes['store_link'] ?? '') === '/tienda',
        'responde la consulta plural sobre tierras con disponibilidad real del catálogo', $soilTypes);
    $maceta = $ask('quiero una meceta irrompible');
    $assert(in_array('/producto/maceta-arena-14', $paths($maceta), true)
        && str_contains($maceta['message'], 'Bs. REF 16.00') && str_contains($maceta['message'], '40 unidades disponibles')
        && str_contains($maceta['message'], 'no puedo asegurar que alguna sea irrompible'),
        'reconoce maceta con errata y no inventa resistencia', $maceta);
    $optionSlugs = array_map(static fn(array $product): string => substr($product['url'], strlen('/producto/')), $maceta['products'] ?? []);
    $selectedPlanter = $ask('Me quedo con la segunda', null, [], $optionSlugs);
    $assert(($paths($maceta)[1] ?? null) !== null
        && $paths($selectedPlanter) === [$paths($maceta)[1]]
        && str_contains($selectedPlanter['message'], $maceta['products'][1]['price']),
        'selecciona la segunda opción en el orden en que se mostraron las tarjetas', $selectedPlanter);
    $totalBudget = $ask('Quiero dos macetas de 14 cm por menos de $35 en total');
    $assert($paths($totalBudget) === ['/producto/maceta-arena-14']
        && str_contains($totalBudget['message'], 'subtotal de 2 unidades es Bs. REF 32.00'),
        'filtra por tamaño, unidades y presupuesto total', $totalBudget);
    $cannabis = $ask('quiero una planta de cannavis para mi patio');
    $assert(in_array('/producto/suculenta-echeveria', $paths($cannabis), true)
        && str_contains($cannabis['message'], 'no comercializa cannabis')
        && !str_contains($cannabis['message'], '¿El lugar recibe'), 'aplica la política a productos no ofrecidos', $cannabis);
    $cannabisFollowUp = $ask('quiero una cannavis', null, [
        'quiero una planta de cannabis para mi patio',
        'es una buena ilumoinacion con fuente de agua excelente',
        'quiero una cannavis',
    ]);
    $assert(in_array('/producto/suculenta-echeveria', $paths($cannabisFollowUp), true)
        && !str_contains($cannabisFollowUp['message'], 'Dime si el lugar recibe'),
        'recuerda la luz aunque el cliente repita que busca un producto no ofrecido', $cannabisFollowUp);

    $none = $ask('Recomiéndame una planta para poca luz por menos de 10 dólares');
    $assert($paths($none) === [] && str_contains($none['message'], 'poca luz')
        && !str_contains($none['message'], 'Encontré estas opciones'), 'no ofrece una alternativa demasiado alejada del presupuesto', $none);
    $inclusive = $ask('Recomienda una planta sin sol directo de hasta $18,00');
    $assert(in_array($bambu, $paths($inclusive), true) && !in_array($monstera, $paths($inclusive), true)
        && str_contains($inclusive['message'], 'Bs. REF 18.00'), 'límite inclusivo con decimales', $inclusive);
    $exclusive = $ask('Recomienda una planta sin sol directo por menos de $18');
    $assert($paths($exclusive) === [$aloe], 'menos de excluye el precio exacto', $exclusive);
    $currency = $ask('Recomiéndame una planta para poca luz por menos de 30 euros');
    $assert($paths($currency) === [] && str_contains($currency['message'], 'Bs. REF'), 'responde con la moneda de referencia del catálogo sin asumir una tasa actual', $currency);
    $negatedBudget = $ask('Recomiéndame una planta sin sol directo que no cueste más de 18 USD');
    $assert(in_array($bambu, $paths($negatedBudget), true) && !in_array($monstera, $paths($negatedBudget), true),
        'una negación de precio actúa como máximo', $negatedBudget);

    $named = $ask('Buenas, ¿me podría decir cuánto cuesta la Monstera?');
    $assert($paths($named) === [$monstera] && str_contains($named['message'], 'Bs. REF 42.00'), 'consulta natural de precio', $named);
    $followUp = $ask('¿Y cuántas quedan?', $named['product_context']);
    $assert($paths($followUp) === [$monstera] && str_contains($followUp['message'], '14 unidades'), 'conserva el referente del seguimiento', $followUp);
    $priceFollowUp = $ask('¿Cuánto me copstaria?', $named['product_context']);
    $assert($paths($priceFollowUp) === [$monstera] && str_contains($priceFollowUp['message'], 'Bs. REF 42.00')
        && str_contains($priceFollowUp['message'], '14 unidades'), 'resuelve consulta de precio con errata y contexto', $priceFollowUp);
    $unknown = $ask('¿Y cuántas quedan de Jazmín?', $named['product_context']);
    $assert($paths($unknown) === [], 'no usa un contexto obsoleto para un producto explícito desconocido', $unknown);
    $care = $ask('¿Cada cuánto riego la Monstera?');
    $assert($paths($care) === [$monstera] && str_contains($care['message'], 'los primeros centímetros del sustrato estén secos'), 'consulta la guía de riego de la Monstera', $care);
    foreach (['¿Tienen 20 monsteras?', '¿Tienen 20 de PLA-006?'] as $message) {
        $stock = $ask($message);
        $assert($paths($stock) === [$monstera] && str_contains($stock['message'], 'No hay 20 unidades'), 'cantidad mayor que la existencia', $stock);
    }
    $plural = $ask('¿Cuánto cuestan las sansevierias?');
    $assert($paths($plural) === [$sansevieria] && str_contains($plural['message'], 'Bs. REF 27.50'), 'reconoce el plural', $plural);
    $typo = $ask('¿Cuánto cuesta la mostrera?');
    $assert($paths($typo) === [] && str_contains($typo['message'], '¿Te refieres a Monstera'), 'pide confirmación de una errata', $typo);
    $both = $ask('¿Cuánto cuestan la Monstera y el Aloe?');
    $assert(count($paths($both)) === 2 && in_array($monstera, $paths($both), true)
        && in_array($aloe, $paths($both), true) && $both['product_context'] === null, 'consulta dos productos sin asignar contexto ambiguo', $both);
    $excluded = $ask('No quiero la Monstera, dime el precio del Aloe');
    $assert($paths($excluded) === [$aloe], 'exclusión expresa de producto', $excluded);

    $planters = $ask('Busco dos macetas de 14 cm');
    $assert(count($paths($planters)) === 1 && ($planters['products'][0]['size'] ?? '') === '14 cm'
        && ($planters['products'][0]['subtotal'] ?? '') === 'Bs. REF 32.00 por 2 unidades',
        'encuentra la maceta y publica su medida y subtotal para la ficha', $planters);
    $subtotal = $ask('Hasta $35 en total', null, ['Busco dos macetas de 14 cm', 'Gracias']);
    $assert($paths($subtotal) === $paths($planters) && str_contains($subtotal['message'], 'Bs. REF 32.00'),
        'conserva categoría, tamaño y cantidad después de una pausa', $subtotal);

    $options = ['monstera-deliciosa', 'aloe-vera-compacta'];
    $invalidOption = $ask('Elijo la opción 8', null, [], $options);
    $assert($paths($invalidOption) === [] && ($invalidOption['keep_product_options'] ?? false) === true,
        'la API pide corregir el número y conservar la lista', $invalidOption);
    $validOption = $ask('Me quedo con la segunda', null, ['Elijo la opción 8'], $options);
    $assert($paths($validOption) === [$aloe], 'acepta la segunda opción después de corregir el número', $validOption);

    $fractionalStock = $ask('Necesito 2,5 unidades de Monstera');
    $assert($paths($fractionalStock) === [] && str_contains($fractionalStock['message'], 'cantidad entera mayor que cero'),
        'rechaza cantidades fraccionarias en lugar de cotizar dos unidades', $fractionalStock);

    $browse = $ask('Quiero una planta');
    $assert(count($paths($browse)) === 8 && substr_count($browse['follow_up'] ?? '', '¿') === 1,
        'muestra todas las plantas disponibles de la demo y deja una pregunta opcional', $browse);
    foreach ($browse['products'] as $card) {
        $assert(!empty($card['description']) && !empty($card['light']) && !empty($card['care'])
            && str_starts_with($card['image'] ?? '', '/assets/images/products/')
            && (int)$card['stock'] > 0, 'usa los datos y la imagen de la ficha', $card);
    }
    $browseSlugs = array_map(static fn(string $path): string => substr($path, strlen('/producto/')), $paths($browse));
    $smaller = $ask('quiero algo mas barato y pequeño', null, ['hola, quiero una planta'], $browseSlugs);
    $assert($paths($smaller) === [$cactus, '/producto/suculenta-echeveria', $aloe]
        && str_contains($smaller['intro'] ?? '', 'de menor a mayor precio') && empty($smaller['follow_up']),
        'refina por tamaño y ordena por precio sin preguntar la categoría otra vez', $smaller);
    $smallSlugs = array_map('basename', $paths($smaller));
    $more = $ask('hay mas opciones ?', null, ['hola, quiero una planta', 'quiero algo mas barato y pequeño'], $smallSlugs);
    $assert($paths($more) === [] && str_contains($more['message'], 'Ya viste todas')
        && ($more['keep_product_options'] ?? false) === true, 'explica que se agotaron las opciones compactas sin perder la lista', $more);

    $potRequest = 'que maceta ba bien con Suculenta Echeveria';
    $potPair = $ask($potRequest, null, ['que macetas hay disponibles ?']);
    $assert(count($paths($potPair)) === 3 && !in_array('/producto/suculenta-echeveria', $paths($potPair), true)
        && str_contains($potPair['intro'] ?? '', 'Suculenta Echeveria') && str_contains($potPair['intro'] ?? '', 'falta la medida'),
        'muestra macetas al mencionar una planta y explica qué falta para confirmar el ajuste', $potPair);
    $potCorrection = $ask('pregunte por macetas para Suculenta Echeveria', null, ['que macetas hay disponibles ?', $potRequest]);
    $assert($paths($potCorrection) === $paths($potPair) && empty($potCorrection['follow_up']),
        'mantiene macetas como categoría aunque el cliente repita el nombre de la planta', $potCorrection);
    $exactPot = $ask('14 cm', null, [$potRequest]);
    $assert($paths($exactPot) === ['/producto/maceta-arena-14'] && str_contains($exactPot['intro'] ?? '', 'Suculenta Echeveria'),
        'aplica la medida al recipiente y conserva la planta de referencia', $exactPot);
    $lighting = $ask('Ahora mejor con luz indirecta', null, ['Busco plantas fáciles para poca luz hasta $20']);
    $assert(in_array($bambu, $paths($lighting), true) && !in_array($monstera, $paths($lighting), true)
        && str_contains($lighting['message'], 'presupuesto'), 'cambia la luz y conserva el presupuesto y nivel de cuidado', $lighting);
    $subcategory = $ask('Mejor una suculenta', null, ['Quiero una planta para luz indirecta hasta $20']);
    $assert($paths($subcategory) === [$aloe], 'refina por subcategoría y conserva la luz', $subcategory);
    $noNamedPlant = $ask('Hasta $20', null, ['Quiero una planta para luz indirecta', 'Sin el Aloe']);
    $assert($paths($noNamedPlant) === [$bambu], 'recuerda el producto excluido después de fijar el precio', $noNamedPlant);
    $diameter = $ask('Máximo 18 cm', null, ['Busco una maceta']);
    $assert($paths($diameter) === ['/producto/maceta-arena-14', '/producto/maceta-terracota-18'],
        'distingue un diámetro máximo de un presupuesto', $diameter);
    $plantForPot = $ask('Quiero una planta para una maceta de 18 cm');
    $assert($paths($plantForPot) === $paths($browse) && str_contains($plantForPot['intro'] ?? '', 'maceta de 18 cm'),
        'identifica la planta como producto y aclara si la compatibilidad con la maceta está verificada', $plantForPot);
    $pots = $ask('Busco una maceta');
    $assert(count($paths($pots)) === 3 && substr_count($pots['follow_up'] ?? '', '¿') === 1,
        'muestra tres macetas y hace una sola pregunta agrupada', $pots);
    $browseWithoutPrice = $ask('Prefiero no decir mi presupuesto', null, ['Busco una maceta']);
    $assert($paths($browseWithoutPrice) === $paths($pots) && empty($browseWithoutPrice['follow_up']),
        'sigue mostrando las macetas al omitir el presupuesto sin insistir', $browseWithoutPrice);
    $easy = $ask('Fácil de cuidar', null, ['Quiero una planta'], array_map(
        static fn(string $path): string => substr($path, strlen('/producto/')), $paths($browse)
    ));
    $assert(count($paths($easy)) === 5 && array_unique(array_column($easy['products'], 'care')) === ['Fácil']
        && empty($easy['follow_up']), 'filtra cuidados en el segundo turno sin volver a preguntar', $easy);

    $twoSpaces = 'me gustaria una planta para mi cuarto y otra para mi sala, que me recomiendas?';
    $rooms = $ask($twoSpaces);
    $placement = array_column($rooms['products'] ?? [], 'placement', 'url');
    $assert(($placement[$sansevieria] ?? '') === 'Tu cuarto · si recibe poca luz'
        && ($placement[$bambu] ?? '') === 'Tu sala · si recibe luz indirecta'
        && substr_count($rooms['follow_up'] ?? '', '¿') === 1, 'separa por habitación y pregunta por la luz una sola vez', $rooms);
    $roomLight = $ask('Tengo poca luz en mi cuarto y sol directo en mi sala', null, [$twoSpaces]);
    $assert(array_column($roomLight['products'] ?? [], 'placement', 'url') === [
        $sansevieria=>'Tu cuarto · poca luz', $cactus=>'Tu sala · sol directo',
    ] && empty($roomLight['follow_up']), 'asocia cada condición con su espacio aunque la luz se mencione antes del nombre', $roomLight);
    $soil = $ask('vender tierra aqui ?');
    $assert($paths($soil) === [] && str_contains($soil['message'], 'no incluye tierra o sustrato')
        && ($soil['store_link'] ?? null) === '/tienda', 'contesta si se vende tierra y ofrece acceso al catálogo', $soil);
    $storeHelp = $ask('¿Cómo los puedo contactar?');
    $assert(str_contains($storeHelp['message'], 'página Contacto') && ($storeHelp['store_link'] ?? null) === '/contacto',
        'enlaza a la página real de contacto', $storeHelp);
    $notSold = $ask('vender mariguana aqui ?');
    $assert($paths($notSold) === [] && str_contains($notSold['message'], 'no comercializa cannabis'),
        'reconoce la variante sin ofrecer un producto ajeno al catálogo', $notSold);
    $uncertainName = $ask('quiero janabi', null, ['quiero un arbol']);
    $assert($paths($uncertainName) === [] && str_contains($uncertainName['message'], '¿Te refieres a cannabis?'),
        'pide confirmar una posible errata sin asumir lo que pidió el cliente', $uncertainName);
    $restrictedName = $ask('quiero mariguana', null, ['quiero un arbol', 'quiero janabi']);
    $assert($paths($restrictedName) === [] && str_contains($restrictedName['message'], 'no comercializa cannabis')
        && !str_contains($restrictedName['message'], 'Dime si el lugar recibe'), 'responde la solicitud directa sin pedir condiciones para otro producto', $restrictedName);
    $owner = $ask('quien es el dueño de la tienda');
    $assert(str_contains($owner['message'], 'no publica quién es el propietario') && ($owner['store_link'] ?? '') === '/contacto',
        'distingue la información sobre propiedad del equipo que figura en Contacto', $owner);
    $venezuela = $ask('ofrecen envios a venezuela?');
    $assert(str_contains($venezuela['message'], 'no generan envíos ni entregas'), 'mantiene la respuesta sobre envíos en la compra simulada', $venezuela);
    $unusual = $ask('quiero una planta exotica', null, ['quiero una planta']);
    $assert(count($paths($unusual)) < count($paths($browse)) && in_array($monstera, $paths($unusual), true)
        && str_contains($unusual['intro'] ?? '', 'no documenta su rareza'),
        'muestra fichas con rasgos llamativos sin afirmar clasificación exótica', $unusual);
    $coldOnly = $ask('quiero una planta que solo propera ene l frio', null, ['quiero una planta', 'quiero una planta exotica']);
    $assert($paths($coldOnly) === [] && str_contains($coldOnly['message'], 'frío'),
        'respeta el frío como requisito y evita tarjetas sin respaldo', $coldOnly);
    $heatOnly = $ask('quiero una planta que soporte el calor extremo', null, ['quiero una planta que solo propera ene l frio']);
    $assert($paths($heatOnly) === [] && str_contains($heatOnly['message'], 'calor'),
        'responde al requisito actual de calor extremo', $heatOnly);
    $amazonPlants = $ask('tienen plantas del amazonas ?');
    $assert($paths($amazonPlants) === [] && str_contains($amazonPlants['message'], 'no puedo confirmar si alguna planta proviene de la Amazonía')
        && ($amazonPlants['store_link'] ?? '') === '/tienda', 'no presenta plantas comunes como si su origen amazónico estuviera confirmado', $amazonPlants);
    $amazonOrigin = $ask('quiero una planta que venga del amazonas', null, ['tienen plantas del amazonas ?']);
    $assert($paths($amazonOrigin) === [] && str_contains($amazonOrigin['message'], 'no registran procedencia geográfica'),
        'mantiene el origen como requisito al precisar la solicitud', $amazonOrigin);
    $amazonCorrection = $ask('no esas plantas no son del amazonas, quiero una que si lo sea', null,
        ['quiero una planta', 'quiero una planta que venga del amazonas']);
    $assert($paths($amazonCorrection) === [] && str_contains($amazonCorrection['message'], 'sería correcto mostrarte las opciones generales'),
        'respeta la corrección del cliente y no repite recomendaciones no verificadas', $amazonCorrection);
    $notInScope = $ask('tienen un toyota 2025?');
    $assert($paths($notInScope) === [] && str_contains($notInScope['message'], 'no vende vehículos'),
        'indica el alcance del catálogo en una consulta fuera de la tienda', $notInScope);
    $tree = $ask('quiero un arbol');
    $assert(str_contains($tree['intro'] ?? '', 'no hay productos publicados como árboles')
        && $paths($tree) === ['/producto/palma-areca'] && ($tree['store_link'] ?? '') === '/tienda',
        'explica la disponibilidad de árboles y describe la palma como alternativa de interior', $tree);
    $fruitTree = $ask('¿Tienen árboles frutales para el jardín?');
    $assert($paths($fruitTree) === [] && str_contains($fruitTree['message'], 'no hay productos publicados como árboles'),
        'no ofrece una alternativa de interior si se pide un árbol frutal', $fruitTree);
    $bonsai = $ask('Quiero un bonsái');
    $assert($paths($bonsai) === [] && str_contains($bonsai['message'], 'no hay bonsáis'),
        'reconoce el tipo de planta aunque esté ausente en el catálogo', $bonsai);
    $flowers = $ask('Quiero una planta fácil con flores');
    $assert($paths($flowers) === [] && str_contains($flowers['message'], 'no hay plantas con flores'),
        'mantiene la condición de flores en una petición con más de un criterio', $flowers);
    $priceDrainage = $ask('¿Cuánto cuesta la Maceta Arena 14 cm y tiene drenaje?');
    $assert($paths($priceDrainage) === ['/producto/maceta-arena-14']
        && str_contains($priceDrainage['message'], 'Bs. REF 16.00') && str_contains($priceDrainage['message'], 'no confirma el drenaje'),
        'responde las dos partes de la pregunta sobre un producto', $priceDrainage);
    $twoSupplies = $ask('¿Venden perlita o semillas?');
    $assert(str_contains($twoSupplies['message'], 'perlita') && str_contains($twoSupplies['message'], 'semillas'),
        'informa la disponibilidad de cada insumo solicitado', $twoSupplies);
    $newTotal = $ask('Hasta $35', null, ['Quiero dos macetas hasta $30 en total']);
    $assert($paths($newTotal) === ['/producto/maceta-arena-14'] && str_contains($newTotal['message'], 'Bs. REF 32.00'),
        'cambia el monto y conserva el tope para toda la compra', $newTotal);
    $sizeRange = $ask('Busco dos macetas entre 14 y 18 cm hasta $45 en total');
    $assert($paths($sizeRange) === ['/producto/maceta-arena-14', '/producto/maceta-terracota-18']
        && str_contains($sizeRange['message'], 'Bs. REF 39.80'), 'filtra el intervalo en centímetros y el presupuesto total', $sizeRange);
    $lastCard = $ask('¿Y el precio de la última?', null, [], ['maceta-arena-14', 'maceta-terracota-18']);
    $assert($paths($lastCard) === ['/producto/maceta-terracota-18'] && str_contains($lastCard['message'], 'Bs. REF 19.90'),
        'resuelve la última tarjeta de la lista enviada por el chat', $lastCard);

    $forecast = $ask('Tendencia de demanda del Aloe');
    $assert(isset($forecast['engine']) && in_array($forecast['engine'], ['php', 'python'], true)
        && str_contains($forecast['message'], 'regresión lineal'), 'regresión con historial de la demo', $forecast);
    $long = $ask('¿Cuál es la previsión de Aloe Vera Compacta para dentro de 12 meses?');
    $assert(!isset($long['engine']) && str_contains($long['message'], 'Solo puedo estimar un mes'), 'explica que el modelo no cubre un año', $long);
    $next = $ask('¿Cuántos Aloe creen que venderán el mes que viene?');
    $assert(str_contains($next['message'], 'No tengo una proyección para el mes que viene'), 'distingue el horizonte del calendario', $next);
    $explain = $ask('¿Y cómo llegaron a ese número?', $forecast['product_context']);
    $assert(str_contains($explain['message'], 'seis meses completos'), 'explica el cálculo como seguimiento', $explain);

    $lowLight = $ask('Recomiéndame una planta para poca luz');
    $previousOptions = array_map(static fn(string $url): string => basename($url), $paths($lowLight));
    foreach (['cual seria el nombre mas apropiado para esta mata', '¿Cómo se llama esta planta?'] as $message) {
        $name = $ask($message, $lowLight['product_context'], ['Recomiéndame una planta para poca luz'], $previousOptions);
        $assert($paths($name) === [$sansevieria] && str_contains($name['message'], 'Dracaena trifasciata')
            && str_contains($name['message'], 'lengua de suegra'), 'identifica la recomendación previa por ambos nombres', $name);
    }
    $ambiguous = $ask('¿Cómo se llama esta planta?', null, [], ['monstera-deliciosa', 'sansevieria']);
    $assert($paths($ambiguous) === [] && ($ambiguous['keep_product_options'] ?? false)
        && str_contains($ambiguous['message'], 'la primera'), 'pide elegir cuando hay varias opciones', $ambiguous);
    $echeveria = $ask('que es Suculenta Echeveria');
    $assert($paths($echeveria) === ['/producto/suculenta-echeveria'] && str_contains($echeveria['message'], 'roseta compacta')
        && !str_contains($echeveria['message'], 'cuesta'), 'describe la echeveria al pedir su definición', $echeveria);
    $mixedCare = $ask('¿Cómo riego la costilla de Adán y cuánto cuesta?');
    $assert($paths($mixedCare) === [$monstera] && str_contains($mixedCare['message'], 'sustrato estén secos')
        && str_contains($mixedCare['message'], 'Bs. REF 42.00'), 'reconoce el nombre común y responde sobre riego y precio', $mixedCare);
    $wateringCan = $ask('¿Cuántos litros caben en la regadera mini?');
    $assert($paths($wateringCan) === ['/producto/regadera-mini-verde'] && str_contains($wateringCan['message'], 'un litro'),
        'responde por las especificaciones de un accesorio', $wateringCan);
    foreach (['quiero un cuji', 'Prosopis juliflora'] as $message) {
        $unlisted = $ask($message, 'suculenta-echeveria');
        $assert($paths($unlisted) === [] && str_contains($unlisted['message'], 'cují yaque')
            && str_contains($unlisted['message'], 'no lo incluye'), 'reconoce el árbol sin atribuirle inventario', $unlisted);
    }
    $transport = $ask('que transporte debo tomar para llegar a mi casa en san francisco');
    $assert($paths($transport) === [] && str_contains($transport['message'], 'servicio de transporte'), 'distingue un trayecto de la entrega de un pedido', $transport);
    foreach ([
        ['¿Quiénes son?', 'empresa ficticia', '/nosotros'],
        ['¿Cuál es su visión?', 'rincón verde', '/nosotros'],
        ['¿Cómo hago un pedido?', 'añádela al carrito', '/tienda'],
        ['¿Y si quiero devolver mi compra?', 'ni reembolsos reales', '/preguntas-frecuentes'],
    ] as [$message, $expected, $link]) {
        $storeAnswer = $ask($message);
        $assert(str_contains($storeAnswer['message'], $expected) && ($storeAnswer['store_link'] ?? '') === $link,
            'responde con la información institucional y enlaza la página correspondiente', $storeAnswer);
    }

    [$denied] = $post(['message'=>'stock PLA-006'], false);
    $assert($denied === 419, 'mantiene la protección CSRF');
    [$status, $spoofed] = $post(['message'=>'stock PLA-006', 'role'=>'ADMIN', 'action'=>'update_stock', 'stock'=>99999]);
    $assert($status === 200 && str_contains($spoofed['message'], '14 unidades') && !str_contains($spoofed['message'], '99999'),
        'ignora solicitudes de mutación y consulta el stock de la base', $spoofed);
    curl_setopt_array($client, [CURLOPT_HTTPGET=>true, CURLOPT_HTTPHEADER=>[]]);
    $seedProducts = require dirname(__DIR__).'/app/data/catalog-seed.php';
    foreach ($seedProducts as $seedProduct) {
        curl_setopt($client, CURLOPT_URL, $base.'/producto/'.$seedProduct['slug']);
        $detail = curl_exec($client);
        $guideHeading = $seedProduct['sku'][0] === 'P' ? 'Guía de cuidados' : 'Detalles y uso';
        $assert(is_string($detail) && curl_getinfo($client, CURLINFO_RESPONSE_CODE) === 200 && str_contains($detail, $guideHeading),
            'muestra la guía en la ficha de '.$seedProduct['name']);
    }
    foreach (['/nosotros'=>'Nuestra visión', '/preguntas-frecuentes'=>'¿Hay devoluciones o reembolsos?'] as $path => $heading) {
        curl_setopt($client, CURLOPT_URL, $base.$path);
        $html = curl_exec($client);
        $assert(is_string($html) && curl_getinfo($client, CURLINFO_RESPONSE_CODE) === 200 && str_contains($html, $heading),
            'muestra contenido institucional en '.$path);
    }
    echo "Chat HTTP aprobado: {$checks} aserciones.\n";
} finally {
    curl_close($client);
}
