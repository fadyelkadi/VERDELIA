<?php
declare(strict_types=1);

final class ShopChat
{
    private const MAX_BODY_BYTES = 16384;
    private const MAX_MESSAGE_BYTES = 2000;

    public function __construct(private CatalogRepository $catalog) {}

    public function handle(string $method): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        if ($method !== 'POST') {
            header('Allow: POST');
            $this->respond(405, ['ok'=>false,'message'=>'Método no permitido.']);
        }
        $body=file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES+1);
        if ($body === false || strlen($body) > self::MAX_BODY_BYTES) {
            $this->respond(400, ['ok'=>false,'message'=>'La solicitud supera el tamaño permitido.']);
        }
        $payload=json_decode($body, true);
        if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
            $this->respond(400, ['ok'=>false,'message'=>'La solicitud no contiene JSON válido.']);
        }
        $token=$_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!verdelia_csrf_valid($token)) {
            $this->respond(419, ['ok'=>false,'message'=>'La sesión expiró. Recarga la página e inténtalo nuevamente.']);
        }
        if (!isset($payload['message']) || !is_string($payload['message'])) {
            $this->respond(400, ['ok'=>false,'message'=>'Escribe una consulta para continuar.']);
        }
        $message=trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',(string)$payload['message']) ?? '');
        if ($message === '' || strlen($message) > self::MAX_MESSAGE_BYTES || preg_match('//u',$message)!==1) {
            $this->respond(400, ['ok'=>false,'message'=>'La consulta debe tener entre 1 y 2000 bytes y usar texto válido.']);
        }
        $history = $payload['history'] ?? [];
        if (!is_array($history) || count($history) > 8) {
            $this->respond(400, ['ok'=>false,'message'=>'El historial de conversación no es válido.']);
        }
        $cleanHistory = [];
        foreach ($history as $entry) {
            if (!is_string($entry)) $this->respond(400, ['ok'=>false,'message'=>'El historial de conversación no es válido.']);
            $entry = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',$entry) ?? '');
            if ($entry === '' || strlen($entry) > 1200 || preg_match('//u',$entry)!==1) {
                $this->respond(400, ['ok'=>false,'message'=>'Un mensaje anterior del historial no es válido.']);
            }
            $cleanHistory[] = $entry;
        }
        $reference = $payload['product_context'] ?? null;
        if (!is_string($reference) || !preg_match('/^[a-z0-9-]{1,120}$/D', $reference)) {
            $reference = null;
        }
        try {
            $products = $this->catalog->chatProducts();
            $query = new ShopChatQuery($message);
            $this->inheritRecommendationHistory($query, $cleanHistory, $products);
            $answer = $this->answer($query, $products, $reference);
            $this->respond(200, ['ok'=>true] + $answer + ['product_context'=>null]);
        } catch (Throwable $exception) {
            verdelia_log_error($exception,'shop-chat');
            $this->respond(503, ['ok'=>false,'message'=>'El asistente no está disponible temporalmente. Inténtalo nuevamente.']);
        }
    }

    private function answer(ShopChatQuery $query, array $products, ?string $reference): array
    {
        if ($query->petSafety) {
            return ['message'=>'El catálogo no tiene información verificada sobre la seguridad de las plantas para mascotas. No puedo recomendarte una planta como segura. Verifica la especie con tu veterinario antes de elegir.'];
        }
        if ($query->noLight) {
            return ['message'=>'El catálogo no permite confirmar qué plantas toleran un espacio sin ventanas o sin luz natural. Si usas iluminación de cultivo, dime qué condiciones tienes para revisar qué indica el catálogo.'];
        }
        if ($query->conflictingLight) {
            return ['message'=>'Mencionaste varias condiciones de luz. ¿Cuál tiene el lugar donde pondrás la planta: poca, indirecta, brillante o sol directo?'];
        }
        if ($query->unsupportedCurrency) {
            return ['message'=>'El catálogo muestra los precios en dólares estadounidenses (USD). Indícame el importe en USD.'];
        }
        if ($query->conflictingHumidity) {
            return ['message'=>'Mencionaste humedad alta y baja. ¿El ambiente suele ser húmedo o seco?'];
        }
        if ($query->conflictingVentilation) {
            return ['message'=>'Mencionaste ventilación buena y escasa. ¿El espacio está bien ventilado o es cerrado?'];
        }
        if ($query->budget === []) {
            return ['message'=>'¿Quieres decir que no tienes un límite definido o que buscas algo gratis? Si tienes un rango, puedes escribir «entre $10 y $20».'];
        }
        if (preg_match('/\b(envios?|enviar|despachos?|delivery|flete)\b/u', $query->text)) {
            return ['message'=>'El asistente no tiene información confirmada sobre tarifas o destinos de envío. Puedo consultar el precio y la disponibilidad de los productos.'];
        }
        if ($query->trend && $query->hasRecommendationCriteria()) {
            return ['message'=>'Puedo estimar la demanda de un producto o recomendar plantas por sus condiciones. ¿Qué quieres consultar primero?'];
        }
        if ($query->recommendation && !$query->hasRecommendationCriteria() && !$query->recommendationContextActive) {
            return ['message'=>$this->recommendationClarifier($query)];
        }
        if ($query->recommendationContextActive && !$query->hasRecommendationCriteria()) {
            return ['message'=>$this->recommendationClarifier($query)];
        }

        $excluded = array_column($this->matchProducts($query->excludedText, $products), 'id');
        $products = array_values(array_filter($products, static fn(array $p): bool => !in_array($p['id'], $excluded, true)));
        $matches = $this->matchProducts($query->productText, $products);
        if ($matches === [] && $reference !== null && $query->followsKnownProduct()) {
            foreach ($products as $product) {
                if ($product['slug'] === $reference) {
                    $matches = [$product];
                    break;
                }
            }
        }
        if (count($matches) === 1) {
            $product = $matches[0];
            return $this->answerAboutProduct($query, $product) + ['product_context'=>(string)$product['slug']];
        }
        if (count($matches) > 1) {
            if ($query->catalogIntent && !$query->trend && !$query->care && !$query->hasRecommendationCriteria()
                && count($matches) <= 3 && preg_match('/\b(?:y|amb[oa]s|cada un[oa])\b/u', $query->productText)) {
                return [
                    'message'=>implode("\n", array_map(fn(array $p): string => $this->productSummary($p, $query->quantity), $matches)),
                    'products'=>array_map([$this, 'publicProduct'], $matches),
                ];
            }
            $names = array_map([$this, 'productLabel'], array_slice($matches, 0, 5));
            return ['message'=>'Encontré varios productos. ¿Cuál quieres consultar? '.implode(', ', $names).'.'];
        }
        if ($query->trend) {
            return $query->unsupportedHorizon ? $this->unsupportedForecast()
                : ['message'=>'¿De qué producto quieres estimar la demanda? Dime su nombre o SKU.'];
        }
        if ($query->care && !$query->hasRecommendationCriteria()) return ['message'=>'¿Qué planta quieres cuidar? Dime el nombre o SKU para revisar qué indica su ficha.'];
        if ($query->followsKnownProduct()) return ['message'=>'¿De qué producto? Dime su nombre o SKU para revisar el catálogo.'];
        if ($query->hasRecommendationCriteria() || $query->recommendationContextActive) {
            if ($query->catalogIntent && !$query->recommendation && $query->light === null && !$query->easy && !$query->noDirect) {
                return ['message'=>'¿De qué producto quieres consultar el precio? Dime su nombre o SKU. Si quieres que busque plantas dentro del presupuesto, pídeme una recomendación.'];
            }
            return $this->recommend($products, $query);
        }
        if ($query->catalogIntent) {
            $suggestion = $this->suggestProduct($query->productText, $products);
            if ($suggestion !== null) {
                return ['message'=>'¿Te refieres a '.$this->productLabel($suggestion).'? Escribe su nombre o SKU para confirmarlo.'];
            }
            return ['message'=>'No identifiqué el producto. Dime su nombre tal como aparece en el catálogo o su SKU.'];
        }
        if ($query->recommendation) return ['message'=>'¿Cómo es la luz donde pondrás la planta? También puedes indicar tu presupuesto o si buscas una de cuidado fácil.'];
        return ['message'=>'Puedo consultar precios, existencias y fichas del catálogo, recomendar plantas según la luz y el presupuesto, o estimar su demanda. Por ejemplo: «¿Hay Monstera?», «una planta para poca luz» o «ventas de PLA-006».'];
    }

    private function inheritRecommendationHistory(ShopChatQuery $query, array $history, array $products): void
    {
        if ($history === [] || $query->recommendation || $query->trend || !$query->hasRecommendationContext()
            || $this->matchProducts($query->productText, $products) !== []) {
            return;
        }

        $active = false;
        $segment = [];
        foreach ($history as $message) {
            $previous = new ShopChatQuery($message);
            if ($previous->recommendation) {
                $active = true;
                $segment = [$previous];
                continue;
            }
            if (!$active) continue;
            if ($previous->trend || $this->matchProducts($previous->productText, $products) !== [] || !$previous->hasRecommendationContext()) {
                $active = false;
                $segment = [];
                continue;
            }
            $segment[] = $previous;
        }
        if (!$active || $segment === []) return;

        $context = new ShopChatQuery('');
        foreach ($segment as $previous) $context->mergeRecommendationPreferencesFrom($previous);
        $query->inheritRecommendationPreferences($context);
    }

    private function recommendationClarifier(ShopChatQuery $query): string
    {
        $message = '¿Qué luz recibe el espacio: poca luz natural, luz indirecta o sol directo? También puedes decirme si prefieres una planta fácil de cuidar y tu rango de precio.';
        if ($query->hasEnvironmentCriteria()) {
            $conditions = [];
            if ($query->humidity === 'high') $conditions[] = 'mucha humedad';
            elseif ($query->humidity === 'low') $conditions[] = 'ambiente seco';
            if ($query->ventilation === 'poor') $conditions[] = 'poca ventilación';
            elseif ($query->ventilation === 'good') $conditions[] = 'buena ventilación';
            $message = 'Tendré en cuenta que el espacio tiene '.implode(' y ', $conditions).'. El catálogo no contiene datos para confirmar tolerancia a esas condiciones. '.$message;
        }
        return $message;
    }

    private function matchProducts(string $query, array $products): array
    {
        if ($query === '') return [];
        $specific = [];
        $aliases = [];
        $confirmedAliases = [];
        foreach ($products as $product) {
            $sku = ShopChatQuery::normalize((string)$product['sku']);
            $name = ShopChatQuery::normalize((string)$product['name']);
            $alias = explode(' ', $name, 2)[0];
            $scientific = ShopChatQuery::normalize((string)($product['scientific_name'] ?? ''));
            if (($sku !== '' && str_contains(' '.$query.' ', ' '.$sku.' ')) || $this->mentions($query, $name)
                || ($scientific !== '' && $this->mentions($query, $scientific))) {
                $specific[] = $product;
                $confirmedAliases[$alias] = true;
            } elseif (strlen($alias) >= 4 && !in_array($alias, ['planta', 'producto', 'articulo'], true) && $this->mentions($query, $alias)) {
                $aliases[$alias][] = $product;
            }
        }
        foreach (array_diff_key($aliases, $confirmedAliases) as $group) {
            array_push($specific, ...$group);
        }
        return $specific;
    }

    private function mentions(string $query, string $name): bool
    {
        if ($name === '') return false;
        $pattern = array_map(static function (string $word): string {
            $plural = strlen($word) >= 4 && ctype_alpha($word) && !in_array(substr($word, -1), ['s', 'x'], true);
            return preg_quote($word, '/') . ($plural ? '(?:s|es)?' : '');
        }, explode(' ', $name));
        return preg_match('/(?:^|\s)'.implode('\s+', $pattern).'(?:\s|$)/u', $query) === 1;
    }

    private function suggestProduct(string $query, array $products): ?array
    {
        $words = array_unique(explode(' ', $query));
        $best = PHP_INT_MAX;
        $candidates = [];
        foreach ($products as $product) {
            $alias = explode(' ', ShopChatQuery::normalize((string)$product['name']), 2)[0];
            if (strlen($alias) < 5) continue;
            foreach ($words as $word) {
                if (strlen($word) < 5) continue;
                $distance = levenshtein($word, $alias);
                if ($distance > $best || $distance > (strlen($alias) >= 8 ? 2 : 1)) continue;
                if ($distance < $best) $candidates = [];
                $best = $distance;
                $candidates[(int)$product['id']] = $product;
            }
        }
        return count($candidates) === 1 ? reset($candidates) : null;
    }

    private function answerAboutProduct(ShopChatQuery $query, array $product): array
    {
        if ($query->trend) {
            if ($query->unsupportedHorizon) return $this->unsupportedForecast();
            $answer = $this->demandAnswer($product);
            if ($query->nextCalendarMonth) {
                $answer['message'] = 'La estimación disponible corresponde al mes en curso, que es el primero posterior al historial cerrado. No tengo una proyección para el mes que viene. '.$answer['message'];
            }
            return $answer;
        }
        if (preg_match('/\bcomo (?:llegaron|calcularon) a ese numero\b/u', $query->text)) {
            return ['message'=>'Si te refieres al pronóstico, la regresión ajusta una línea a las ventas confirmadas de los últimos seis meses completos y estima el primer mes posterior. Requiere ventas en al menos tres de esos meses. El precio y las existencias vienen del catálogo.'];
        }

        $facts = [];
        if ($query->care || $query->light !== null || $query->noDirect || $query->easy) {
            $facts[] = $this->productLabel($product).'.';
            $light = (string)$product['light'];
            $care = (string)$product['care_level'];
            if ($light !== '') $facts[] = 'La ficha indica «'.$light.'».';
            if ($care !== '') $facts[] = 'Nivel de cuidado: '.$care.'.';
            if (($query->light !== null || $query->noDirect) && $this->lightScore($product, $query) <= 0) {
                $facts[] = 'La ficha no confirma que sea adecuada para la condición de luz que pediste.';
            }
            if ($query->easy && ShopChatQuery::normalize($care) !== 'facil') {
                $facts[] = 'La ficha no la clasifica como una opción de cuidado fácil.';
            }
            if ($query->watering) {
                $facts[] = 'La ficha no especifica la frecuencia de riego.';
            } elseif ($light === '' && $care === '') {
                $facts[] = 'La ficha no incluye indicaciones de cuidado.';
            }
        }
        if ($query->budget !== null) {
            $relation = $this->fitsBudget($product, $query->budget) ? 'Está dentro de tu presupuesto.' : 'Queda fuera de tu presupuesto.';
            $facts[] = $this->productSummary($product, $query->quantity).' '.$relation;
        } elseif ($query->catalogIntent || $facts === []) {
            $facts[] = $this->productSummary($product, $query->quantity);
        }
        return ['message'=>implode(' ', $facts), 'products'=>[$this->publicProduct($product)]];
    }

    private function unsupportedForecast(): array
    {
        return ['message'=>'Solo puedo estimar un mes: el primero después de los seis meses completos que usa el modelo. La proyección no cubre el período que pediste.'];
    }

    private function productLabel(array $product): string
    {
        return $product['name'].' ('.$product['sku'].')';
    }

    private function productSummary(array $product, ?int $quantity): string
    {
        $stock = (int)$product['stock'];
        $availability = match (true) {
            $stock <= 0 => 'Por ahora está agotado.',
            $stock === 1 => 'Hay 1 unidad disponible.',
            default => 'Hay '.$stock.' unidades disponibles.',
        };
        if ($quantity !== null && $quantity > 0) {
            $units = $quantity === 1 ? '1 unidad' : $quantity.' unidades';
            $availability .= $stock >= $quantity ? ' Sí hay '.$units.' para tu consulta.' : ' No hay '.$units.' disponibles.';
        }
        return $this->productLabel($product).' cuesta '.$this->money((int)$product['price_cents']).' USD. '.$availability;
    }

    private function fitsBudget(array $product, array $budget): bool
    {
        $price = (int)$product['price_cents'];
        return $price >= ($budget['min'] ?? 0) && $price <= ($budget['max'] ?? PHP_INT_MAX);
    }

    private function recommend(array $products, ShopChatQuery $query): array
    {
        $ranked = [];
        foreach ($products as $product) {
            $category = ShopChatQuery::normalize($product['category'].' '.$product['category_name']);
            if ((int)$product['stock'] < max(1, $query->quantity ?? 0) || preg_match('/\b(accesorios?|macetas?)\b/u', $category)) continue;
            if ($query->easy && ShopChatQuery::normalize((string)$product['care_level']) !== 'facil') continue;
            if ($query->budget !== null && !$this->fitsBudget($product, $query->budget)) continue;
            $score = $this->lightScore($product, $query);
            if ($score > 0) $ranked[] = ['score'=>$score, 'product'=>$product];
        }
        usort($ranked, static fn(array $a, array $b): int => ($b['score'] <=> $a['score']) ?: strcmp($a['product']['name'], $b['product']['name']));
        $ranked = array_slice($ranked, 0, 3);
        if ($ranked === []) return $this->noRecommendationMatch($products, $query);
        $names = [];
        $results = [];
        foreach ($ranked as $item) {
            $product = $item['product'];
            $detail = $this->productLabel($product).'. La ficha indica «'.($product['light'] ?: 'luz no especificada').'».';
            if ($query->easy) $detail .= ' Cuidado fácil.';
            if ($query->budget !== null) $detail .= ' Precio: '.$this->money((int)$product['price_cents']).' USD.';
            $names[] = $detail;
            $results[] = $this->publicProduct($product);
        }
        $criteria = [];
        if ($query->light !== null) $criteria[] = ['low'=>'poca luz', 'medium'=>'luz indirecta o media', 'bright'=>'luz brillante', 'direct'=>'sol directo'][$query->light];
        if ($query->noDirect) $criteria[] = 'sin sol directo';
        if ($query->easy) $criteria[] = 'de cuidado fácil';
        if ($query->budget !== null) $criteria[] = 'dentro de tu presupuesto';
        if ($query->quantity !== null && $query->quantity > 1) $criteria[] = 'con al menos '.$query->quantity.' unidades disponibles';
        $message = 'Encontré estas opciones que coinciden con los datos del catálogo ('.implode(', ', $criteria)."):\n".implode("\n", $names);
        $message .= $this->environmentCaveat($query);
        return ['message'=>$message, 'products'=>$results];
    }

    private function noRecommendationMatch(array $products, ShopChatQuery $query): array
    {
        $alternatives = [];
        foreach ($products as $product) {
            $category = ShopChatQuery::normalize($product['category'].' '.$product['category_name']);
            if ((int)$product['stock'] < max(1, $query->quantity ?? 0) || preg_match('/\b(accesorios?|macetas?)\b/u', $category)) continue;
            if ($query->easy && ShopChatQuery::normalize((string)$product['care_level']) !== 'facil') continue;
            if ($this->lightScore($product, $query) <= 0) continue;
            if ($query->budget !== null) {
                if ($this->fitsBudget($product, $query->budget)) continue;
                $price = (int)$product['price_cents'];
                $distance = $price < ($query->budget['min'] ?? 0)
                    ? ($query->budget['min'] - $price)
                    : ($price - ($query->budget['max'] ?? PHP_INT_MAX));
                if ($distance > 1000) continue;
            }
            $alternatives[] = ['score'=>$this->lightScore($product, $query), 'product'=>$product];
        }
        usort($alternatives, static fn(array $a, array $b): int => ($b['score'] <=> $a['score']) ?: strcmp($a['product']['name'], $b['product']['name']));

        $message = 'No encontré plantas disponibles que cumplan a la vez la luz, el cuidado y el precio que indicaste.';
        $productsForLinks = [];
        if ($alternatives !== []) {
            $alternative = $alternatives[0]['product'];
            $message .= ' La opción más cercana por luz y cuidado es '.$this->productLabel($alternative).', pero cuesta '.$this->money((int)$alternative['price_cents']).' USD y queda fuera del rango.';
            $productsForLinks[] = $this->publicProduct($alternative);
        }
        $message .= $this->environmentCaveat($query);
        $message .= ' Puedes ampliar el presupuesto o relajar algún criterio para ver otras opciones.';
        return ['message'=>$message, 'products'=>$productsForLinks];
    }

    private function environmentCaveat(ShopChatQuery $query): string
    {
        if (!$query->hasEnvironmentCriteria()) return '';
        return ' Las fichas no registran tolerancia a humedad ni ventilación; esa compatibilidad no está confirmada.';
    }

    private function lightScore(array $product, ShopChatQuery $query): float
    {
        $value = ShopChatQuery::normalize((string)$product['light']);
        $exclusion = '/\b(?:sin|evitar|evita|no tolera)\s+(?:la\s+|el\s+)?(?:luz directa|sol(?: directo)?)\b/u';
        $withoutDirect = preg_match($exclusion, $value) === 1;
        $positive = preg_replace($exclusion, ' ', $value) ?? $value;
        $direct = preg_match('/\b(directa|sol directo|pleno sol)\b/u', $positive) === 1;
        $indirect = preg_match('/\bindirecta\b/u', $positive) === 1;
        $bright = preg_match('/\b(brillante|luminosa)\b/u', $positive) === 1;
        $low = preg_match('/\b(baja|poca)\b/u', $positive) === 1;
        $medium = preg_match('/\b(media|moderada)\b/u', $positive) === 1;
        if ($query->noDirect && ($direct || !($withoutDirect || $indirect || $low || $medium))) return 0.0;
        return match ($query->light) {
            'direct' => $direct && !$withoutDirect ? 1.0 : 0.0,
            'low' => $low && !$direct ? ($medium ? 0.9 : 1.0) : 0.0,
            'medium' => $direct ? 0.0 : ($indirect ? ($bright ? 0.9 : 1.0) : ($medium ? 0.8 : 0.0)),
            'bright' => $bright ? ($indirect ? 0.9 : 1.0) : 0.0,
            default => $query->noDirect && $indirect ? 1.1 : 1.0,
        };
    }

    private function demandAnswer(array $product): array
    {
        $now=new DateTimeImmutable('first day of this month');
        $start=$now->modify('-6 months');
        $until=$now->format('Y-m-d');
        $from=$start->format('Y-m-d');
        $observed=$this->catalog->chatMonthlyUnits((int)$product['id'],$from,$until);
        $series=[]; $labels=[]; $positive=0;
        for ($month=$start;$month<$now;$month=$month->modify('+1 month')) {
            $key=$month->format('Y-m');
            $value=max(0,(int)($observed[$key]??0));
            $series[]=$value; $labels[]=$month->format('m/Y');
            if ($value>0) $positive++;
        }
        if ($positive<3) return ['message'=>'Aún no hay suficientes meses con ventas confirmadas de '.$product['name'].' para estimar una tendencia. Se requieren al menos 3 meses con ventas en los últimos 6 meses completos.'];
        $model=ShopChatRegression::linear($series);
        if (!($model['available']??false) || !isset($model['forecast'][0]) || !is_numeric($model['forecast'][0])) {
            return ['message'=>'No hay suficiente historia válida para estimar la tendencia de '.$product['name'].' en los últimos 6 meses completos.'];
        }
        $forecast=max(0,(int)round((float)$model['forecast'][0]));
        $slope=(float)($model['slope']??0.0);
        $direction=abs($slope)<0.05?'se mantuvo aproximadamente estable':($slope>0?'mostró una tendencia ascendente':'mostró una tendencia descendente');
        $engine=($model['engine']??'php')==='python'?'Python':'PHP';
        return [
            'message'=>'Tendencia de demanda de '.$product['name'].' ('.$product['sku'].'): '.$direction.' entre '.$labels[0].' y '.end($labels).'. La regresión lineal estima '.number_format($forecast,0,',','.').' unidades para el primer mes posterior al historial ('.$now->format('m/Y').'). Es una estimación basada en el historial de pedidos con pago simulado aprobado. Motor: '.$engine.'.',
            'engine'=>strtolower($engine),
        ];
    }

    private function publicProduct(array $product): array
    {
        return ['name'=>(string)$product['name'],'url'=>'/producto/'.rawurlencode((string)$product['slug'])];
    }

    private function money(int $cents): string
    {
        return '$'.number_format($cents/100,2,'.',',');
    }

    private function respond(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE);
        exit;
    }
}
