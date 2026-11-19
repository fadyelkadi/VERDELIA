<?php
declare(strict_types=1);

final class ShopChat
{
    private const MAX_BODY_BYTES = 16384;
    private const MAX_MESSAGE_BYTES = 2000;
    private const MAX_RECOMMENDATIONS = 12;
    private const PLANT_CATEGORIES = ['plantas', 'cactus', 'suculentas', 'arboles', 'arboles-frutales', 'bonsais', 'plantas-con-flores', 'flores'];
    private const CATEGORY_LABELS = [
        'plantas'=>'plantas', 'macetas'=>'macetas', 'accesorios'=>'accesorios', 'cactus'=>'cactus',
        'suculentas'=>'suculentas', 'arboles'=>'árboles', 'arboles-frutales'=>'árboles frutales',
        'bonsais'=>'bonsáis', 'plantas-con-flores'=>'plantas con flores', 'flores'=>'flores',
    ];
    private const PICKUP_PATTERN = '/\b(retirar|retiro|recoger|recogida|pasar por|paso por|pickup)\b/u';
    private const PICKUP_NOTICE = 'El stock del catálogo no confirma retiro en tienda, horario ni disponibilidad para hoy.';

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
        $optionReferences = $payload['product_options'] ?? [];
        if (!is_array($optionReferences) || count($optionReferences) > self::MAX_RECOMMENDATIONS) $optionReferences = [];
        $optionReferences = array_values(array_filter($optionReferences, static fn($slug): bool => is_string($slug) && preg_match('/^[a-z0-9-]{1,120}$/D', $slug) === 1));
        try {
            $products = $this->catalog->chatProducts();
            $query = new ShopChatQuery($message);
            $this->inheritRecommendationHistory($query, $cleanHistory, $products);
            $answer = $this->answer($query, $products, $reference, $optionReferences);
            $this->respond(200, ['ok'=>true] + $answer + ['product_context'=>null]);
        } catch (Throwable $exception) {
            verdelia_log_error($exception,'shop-chat');
            $this->respond(503, ['ok'=>false,'message'=>'El asistente no está disponible temporalmente. Inténtalo nuevamente.']);
        }
    }

    private function answer(ShopChatQuery $query, array $products, ?string $reference, array $optionReferences = []): array
    {
        $this->identifyPotCompanion($query, $products);
        if ($query->petEmergency) {
            if ($query->childEmergency) {
                return ['message'=>'Si una mascota o un niño pudo ingerir una planta, contacta ahora con un veterinario para la mascota y con un centro de toxicología o los servicios médicos de urgencia locales para el niño. No esperes síntomas ni provoques el vómito; si puedes, identifica la planta y explica cuánto y cuándo se ingirió. No puedo evaluar una intoxicación.'];
            }
            return ['message'=>'Si tu mascota ingirió una planta, contacta ahora con un veterinario o una clínica veterinaria de urgencias. Si puedes, identifica la planta y explica cuánto pudo ingerir y cuándo. No puedo evaluar una intoxicación ni sustituir atención veterinaria.'];
        }
        if ($query->childEmergency) {
            return ['message'=>'Si un niño pudo ingerir una planta, contacta ahora con un centro de toxicología o con los servicios médicos de urgencia locales. No esperes síntomas ni provoques el vómito; si puedes, identifica la planta y explica cuánto y cuándo se ingirió. No puedo evaluar una intoxicación.'];
        }
        if ($query->medicalClaim) {
            $restriction = $query->restrictedPlant ? 'VERDELIA no comercializa cannabis. ' : '';
            return ['message'=>$restriction.'El catálogo no respalda plantas como cura, prevención o tratamiento de asma u otras enfermedades. Consulta a un profesional de salud; puedo ayudarte con usos ornamentales y datos del catálogo.'];
        }
        if ($query->conflictingBudget) {
            $cap = preg_match('/\b(?:hasta|maximo(?: de)?|menos de|por debajo de|no (?:cueste(?:n)? )?mas de|tope(?: total)?(?: de)?|presupuesto(?: total)?(?: (?:es|de))?)\s+(?:usd\s*)?(\d+(?:[.,]\d{1,2})?)/u', $query->text, $amount)
                ? ' $'.str_replace(',', '.', $amount[1]).' USD'
                : '';
            return ['message'=>'Mencionaste que no tienes límite y también fijaste un máximo'.$cap.'. ¿Quieres que aplique ese máximo o que ignore el presupuesto?'];
        }
        if ($query->unsupportedClimateRequirement) {
            $conditions = match (true) {
                preg_match('/\b(frio|fri[oa]s?|heladas?)\b/u', $query->text) === 1 => 'el frío que mencionas',
                preg_match('/\b(calor|calid[oa]s?|caluros\w*)\b/u', $query->text) === 1 => 'el calor que mencionas',
                default => 'esas condiciones climáticas',
            };
            return ['message'=>'El catálogo no registra tolerancia a temperaturas específicas ni a condiciones climáticas extremas. No puedo confirmar que alguna de nuestras plantas prospere con '.$conditions.'.'];
        }
        if ($query->unsupportedPlantEffect) {
            return ['message'=>'El catálogo no documenta que una planta elimine humedad o moho, así que no puedo prometer ese efecto. Puedo recomendar una planta ornamental según la luz del espacio y el stock disponible.'];
        }
        if ($query->restrictedPlant) {
            $policy = 'VERDELIA no comercializa cannabis. ';
            if ($query->petSafety) {
                return ['message'=>$policy.'Además, el catálogo no confirma seguridad para mascotas, así que no puedo sugerir una planta como segura.'];
            }
            if ($query->childSafety) {
                return ['message'=>$policy.'Además, el catálogo no confirma seguridad de las plantas para bebés o niños; no puedo recomendar una como segura.'];
            }
            if ($query->noLight) {
                return ['message'=>$policy.'Tampoco puedo confirmar qué plantas ornamentales toleran un espacio sin luz natural.'];
            }
            if ($query->conflictingLight) {
                return ['message'=>$policy.'Mencionaste condiciones de luz distintas. ¿Recibe poca luz, luz indirecta, luz brillante o sol directo?'];
            }
            if ($query->hasRecommendationCriteria() || $query->lightMentioned) {
                $alternatives = $this->recommend($products, $query);
                $alternatives['message'] = $policy.$alternatives['message'];
                if (isset($alternatives['intro'])) $alternatives['intro'] = $policy.$alternatives['intro'];
                return $alternatives;
            }
            return ['message'=>$policy.'En la tienda encontrarás plantas ornamentales, macetas y accesorios.', 'store_link'=>'/tienda'];
        }
        if ($query->possibleRestrictedName && ($query->catalogIntent || $query->text === 'janabi')
            && $this->namedProducts($query, $products) === []) {
            return ['message'=>'¿Te refieres a cannabis? VERDELIA no lo comercializa. Si querías decir otra planta, puedes escribir su nombre o describirla.'];
        }
        if ($query->petSafety) {
            return ['message'=>'El catálogo no tiene información verificada sobre la seguridad de las plantas para mascotas. No puedo recomendarte una planta como segura. Verifica la especie con tu veterinario antes de elegir.'];
        }
        if ($query->childSafety) {
            return ['message'=>'El catálogo no tiene información verificada sobre toxicidad o seguridad de las plantas para bebés o niños. No puedo recomendar una como segura; verifica la especie con un profesional de salud o una fuente local de toxicología antes de elegir.'];
        }
        if ($query->invalidQuantity) {
            return ['message'=>'Indica una cantidad entera mayor que cero para consultar existencias y subtotales.'];
        }
        if ($query->botanicalOriginRegion !== null) {
            return [
                'message'=>'Entiendo que buscas una planta de origen amazónico. Las fichas actuales de VERDELIA no registran procedencia geográfica, así que no puedo confirmar si alguna planta proviene de la Amazonía. Tampoco sería correcto mostrarte las opciones generales como si cumplieran ese requisito.',
                'store_link'=>'/tienda',
            ];
        }
        if (self::isCourtesy($query->text)) {
            return ['message'=>'¡Con gusto! Puedes continuar la consulta cuando quieras.', 'keep_product_options'=>true];
        }
        $storeInformation = $this->storeInformation($query, $products);
        if ($storeInformation !== null) return $storeInformation + ['keep_product_options'=>true];
        if ($query->moreOptions && !$query->recommendationContextActive) {
            return ['message'=>'¿De qué quieres ver más opciones: plantas, macetas o accesorios?'];
        }
        if ($query->conflictingHumidity) {
            return ['message'=>'Mencionaste humedad alta y baja. ¿El ambiente suele ser húmedo o seco?'];
        }
        if ($query->conflictingVentilation) {
            return ['message'=>'Mencionaste ventilación buena y escasa. ¿El espacio está bien ventilado o es cerrado?'];
        }
        if ($query->invalidBudgetRange) {
            return ['message'=>'El rango de precio parece estar invertido. Escribe primero el monto menor, por ejemplo «entre $10 y $30».'];
        }
        if ($query->budget === [] && !$query->unsupportedCurrency) {
            return ['message'=>'¿Quieres decir que no tienes un límite definido o que buscas algo gratis? Si tienes un rango, puedes escribir «entre $10 y $20».'];
        }
        if (in_array($query->catalogCategory, ['arboles', 'arboles-frutales', 'bonsais', 'plantas-con-flores'], true)
            && !$query->trend && !$query->externalRetailer
            && $this->namedProducts($query, $products) === []
            && !array_filter($products, fn(array $product): bool => $this->productMatchesCategory($product, $query->catalogCategory))) {
            if ($query->catalogCategory === 'arboles' || ($query->catalogCategory === 'arboles-frutales'
                && !array_filter($products, fn(array $product): bool => $this->productMatchesCategory($product, 'arboles')))) {
                return $this->unlistedTreeReply($query, $products);
            }
            return [
                'message'=>'En el catálogo actual de VERDELIA no hay '.self::CATEGORY_LABELS[$query->catalogCategory].' publicados.',
                'store_link'=>'/tienda',
            ];
        }
        if ($query->rooms !== [] && !$query->trend && !$query->unsupportedCurrency && !$query->externalRetailer
            && ($query->catalogCategory === null || in_array($query->catalogCategory, self::PLANT_CATEGORIES, true))
            && $this->namedProducts($query, $products) === []) {
            $excluded = array_column($this->matchProducts($query->excludedText, $products), 'id');
            $available = array_values(array_filter($products, static fn(array $product): bool => !in_array($product['id'], $excluded, true)));
            return $this->roomRecommendations($query, $available, $optionReferences);
        }
        if ($query->noLight) {
            return ['message'=>'El catálogo no permite confirmar qué plantas toleran un espacio sin ventanas o sin luz natural. Si usas iluminación de cultivo, dime qué condiciones tienes para revisar qué indica el catálogo.'];
        }
        if ($query->conflictingLight) {
            return ['message'=>'Mencionaste varias condiciones de luz. ¿Cuál tiene el lugar donde pondrás la planta: poca, indirecta, brillante o sol directo?'];
        }
        if ($query->trend && $query->hasRecommendationCriteria()) {
            return ['message'=>'Puedo estimar la demanda de un producto o recomendar plantas por sus condiciones. ¿Qué quieres consultar primero?'];
        }
        $excluded = array_column($this->matchProducts($query->excludedText, $products), 'id');
        $products = array_values(array_filter($products, static fn(array $p): bool => !in_array($p['id'], $excluded, true)));
        $matches = $this->namedProducts($query, $products);
        if ($matches === [] && $query->selectedOption !== null) {
            if ($optionReferences === []) {
                return ['message'=>'No tengo una lista de opciones numeradas en esta consulta. Pídeme una recomendación o dime el nombre o SKU del producto.'];
            }
            $optionIndex = $query->selectedOption === ShopChatQuery::LAST_OPTION
                ? count($optionReferences) - 1 : $query->selectedOption - 1;
            if (!isset($optionReferences[$optionIndex])) {
                $count = count($optionReferences);
                $available = $count === 1 ? 'Solo hay una opción en la lista anterior.' : 'Solo hay '.$count.' opciones en la lista anterior.';
                return ['message'=>$available.' Elige un número de esa lista o dime el nombre del producto.', 'keep_product_options'=>true];
            }
            $selectedSlug = $optionReferences[$optionIndex];
            $matches = array_values(array_filter($products, static fn(array $product): bool => $product['slug'] === $selectedSlug));
            if ($matches === []) {
                return ['message'=>'Esa opción ya no aparece en el catálogo. Pídeme una lista actualizada o dime el nombre del producto.'];
            }
        } elseif ($matches === [] && $optionReferences !== []
            && preg_match('/\b(?:cual(?:es)? (?:es|son|requiere|necesita|cuesta|sale)|compara\w*|ordena\w*)\b/u', $query->text) === 1
            && ($query->lowPrice || $query->care || $query->easy)) {
            $matches = array_values(array_filter($products, static fn(array $product): bool => in_array($product['slug'], $optionReferences, true)));
        }
        if ($matches === [] && $reference !== null && $query->followsKnownProduct()) {
            foreach ($products as $product) {
                if ($product['slug'] === $reference) {
                    $matches = [$product];
                    break;
                }
            }
        }
        if ($query->externalRetailer && $matches === []) {
            return ['message'=>'Solo puedo confirmar existencias y precios del catálogo de VERDELIA; no puedo consultar la disponibilidad de otras tiendas.'];
        }
        if ($query->transactionIntent && $matches === []) {
            return ['message'=>'No puedo completar ni apartar pedidos desde el chat. Dime el nombre o SKU para revisar el producto; para comprar, usa el carrito y el checkout del sitio.'];
        }
        if ($query->unsupportedCurrency) {
            if ($matches === []) {
                return ['message'=>'El catálogo muestra los precios en dólares estadounidenses (USD). No tengo un tipo de cambio verificado para convertir o comparar el importe que indicaste.'];
            }
            // A foreign-currency amount cannot be compared to the catalog's USD prices.
            $query->budget = null;
            $query->budgetMentioned = false;
        }
        $isPlantCategory = in_array($query->catalogCategory, self::PLANT_CATEGORIES, true);
        $showPlantCategory = !$isPlantCategory || (!$query->compactSpace && $query->unusualAppearance !== true && $query->containerSizeCm === null && (
            (!$query->recommendation && !$query->hasRecommendationCriteria())
            || ($query->catalogCategory === 'plantas'
                && ($query->lowPrice || $query->budget !== null || ($query->quantity !== null && $query->quantity > 1) || $query->sizeCm !== null)
                && $query->light === null && !$query->noDirect && $query->carePreference === null)));
        if ($query->catalogCategory !== null
            && $showPlantCategory
            && $matches === []) {
            return $this->appendShippingCaveat($this->categoryAnswer($query->catalogCategory, $products, $query, $optionReferences), $query);
        }
        if ($query->lowPrice && $query->catalogCategory === null && !$query->recommendationContextActive && $matches === []) {
            return ['message'=>'¿Buscas una planta, una maceta o un accesorio? Puedo ordenar las opciones disponibles de menor a mayor precio.'];
        }
        if ($matches === [] && $optionReferences !== [] && preg_match('/\b(?:la|el)\s+(?:otra|otro)\b/u', $query->text) === 1) {
            return ['message'=>'¿A cuál opción te refieres? Dime el nombre del producto o especifica cuál de las opciones anteriores.', 'keep_product_options'=>true];
        }
        if (count($matches) === 1) {
            $product = $matches[0];
            $reply = $this->answerAboutProduct($query, $product) + ['product_context'=>(string)$product['slug']];
            if ($query->potCompanion !== null && $this->productMatchesCategory($product, 'macetas')) {
                $reply['message'] .= ' '.$this->potCompatibilityNote($query);
            }
            if ($this->productMatchesCategory($product, 'macetas') && $this->mentionsDrainage($query->text)) {
                $reply['message'] .= ' La ficha no confirma el drenaje de esta maceta.';
            }
            return $this->appendRetailerCaveat($this->appendCurrencyCaveat($this->appendShippingCaveat($this->appendTransactionCaveat($this->acknowledgeBudget($reply, $query), $query), $query), $query), $query);
        }
        if (count($matches) > 1) {
            return $this->appendRetailerCaveat($this->appendCurrencyCaveat($this->appendTransactionCaveat($this->acknowledgeBudget($this->compareProducts($matches, $query), $query), $query), $query), $query);
        }
        if (preg_match('/\b(envios?|enviar|entregas?|despachos?|delivery|flete)\b/u', $query->text)) {
            return $this->appendShippingCaveat(['message'=>'Puedo ayudarte con productos, precios y existencias del catálogo.'], $query);
        }
        if (preg_match(self::PICKUP_PATTERN, $query->text)) {
            return ['message'=>self::PICKUP_NOTICE];
        }
        if ($query->trend) {
            return $query->unsupportedHorizon ? $this->unsupportedForecast()
                : ['message'=>'¿De qué producto quieres estimar la demanda? Dime su nombre o SKU.'];
        }
        if ($query->care && !$query->hasRecommendationCriteria() && !$query->recommendationContextActive) return ['message'=>'¿Qué planta quieres cuidar? Dime el nombre o SKU para revisar qué indica su ficha.'];
        if ($query->followsKnownProduct()) return ['message'=>'¿De qué producto? Dime su nombre o SKU para revisar el catálogo.'];
        if ($query->hasRecommendationCriteria() || $query->hasEnvironmentCriteria() || $query->recommendationContextActive
            || ($query->recommendation && ($query->catalogCategory !== null || $query->compactSpace || str_contains($query->text, 'algo verde')))) {
            if ($query->catalogIntent && !$query->recommendation && !$query->recommendationContextActive
                && $query->light === null && $query->carePreference === null && !$query->noDirect
                && !$query->hasEnvironmentCriteria()) {
                return ['message'=>'¿De qué producto quieres consultar el precio? Dime su nombre o SKU. Si quieres que busque plantas dentro del presupuesto, pídeme una recomendación.'];
            }
            return $this->acknowledgeBudget($this->recommend($products, $query, $optionReferences), $query);
        }
        if ($query->catalogIntent) {
            $suggestion = $this->suggestProduct($query->productText, $products);
            if ($suggestion !== null) {
                return ['message'=>'¿Te refieres a '.$this->productLabel($suggestion).'? Escribe su nombre o SKU para confirmarlo.'];
            }
            if (preg_match('/\b(escritorio|mesa de trabajo|repisa|estante|espacio pequeno|algo verde)\b/u', $query->text) === 1) {
                return ['message'=>'¿Buscas una planta para ese espacio? Dime si recibe poca luz, luz indirecta o sol directo y revisaré opciones disponibles del catálogo.'];
            }
            return ['message'=>'No identifiqué el producto. Dime su nombre tal como aparece en el catálogo o su SKU.'];
        }
        if ($query->recommendation) return ['message'=>'¿Buscas una planta, una maceta o un accesorio?'];
        return ['message'=>'Solo puedo ayudar con VERDELIA: catálogo, precios, stock, cuidados que aparecen en las fichas, recomendaciones por luz o presupuesto y tendencias de demanda. Dime el producto o qué necesitas saber.'];
    }

    private function inheritRecommendationHistory(ShopChatQuery $query, array $history, array $products): void
    {
        $this->identifyPotCompanion($query, $products);
        if ($history === [] || $query->invalidQuantity || $query->restartSearch || $query->trend
            || (!$query->hasRecommendationContext() && !$query->restrictedPlant && !$query->recommendation
                && $query->catalogCategory === null && $this->matchProducts($query->excludedText, $products) === [])
            || $this->namedProducts($query, $products) !== []) {
            return;
        }

        $context = null;
        foreach ($history as $message) {
            $previous = new ShopChatQuery($message);
            $this->identifyPotCompanion($previous, $products);
            if (self::isCourtesy($previous->text) || $previous->invalidQuantity) continue;
            if ($previous->trend || $this->namedProducts($previous, $products) !== []
                || (!$previous->recommendation && $previous->catalogCategory === null
                    && !$previous->hasRecommendationContext() && !$previous->restrictedPlant
                    && $this->matchProducts($previous->excludedText, $products) === [])) {
                $context = null;
                continue;
            }
            if ($context === null || $this->startsAnotherSearch($previous, $context)) {
                if (!$previous->recommendation && $previous->catalogCategory === null && $previous->rooms === []
                    && !$previous->hasEnvironmentCriteria() && !$previous->temperatureMentioned) continue;
                $context = new ShopChatQuery('');
            }
            $this->interpretMeasureReply($previous, $context);
            $context->mergeRecommendationPreferencesFrom($previous);
        }
        if ($context !== null && !$this->startsAnotherSearch($query, $context)) {
            $this->interpretMeasureReply($query, $context);
            $query->inheritRecommendationPreferences($context);
        }
    }

    private function interpretMeasureReply(ShopChatQuery $query, ShopChatQuery $context): void
    {
        if ($query->standaloneNumber !== null && $context->catalogCategory === 'macetas'
            && $context->sizeCm === null && $context->maxSizeCm === null && $context->budgetMentioned) {
            $query->sizeCm = $query->standaloneNumber;
        }
    }

    private function startsAnotherSearch(ShopChatQuery $query, ShopChatQuery $context): bool
    {
        if ($query->restartSearch) return true;
        if ($query->catalogCategory !== null && $context->catalogCategory !== null
            && $query->catalogCategory !== $context->catalogCategory
            && !(in_array($query->catalogCategory, self::PLANT_CATEGORIES, true)
                && in_array($context->catalogCategory, self::PLANT_CATEGORIES, true))) return true;
        return $query->recommendation && $query->light !== null && $context->light !== null
            && $query->light !== $context->light && $query->budget !== null;
    }

    private static function isCourtesy(string $text): bool
    {
        return preg_match('/^(?:(?:muchas |mil )?gracias|ok|okay|okey|entendido|perfecto|de acuerdo)$/u', $text) === 1;
    }

    private function recommendationFollowUp(ShopChatQuery $query, string $category): ?string
    {
        if ($query->recommendationContextActive || $query->skipPreferences || $query->restrictedPlant) return null;
        if ($query->romanticGift) return '¿Prefieres una opción compacta o una planta de mayor presencia visual?';
        if ($category === 'macetas') {
            if ($query->sizeCm !== null || $query->maxSizeCm !== null) return null;
            if ($query->potCompanion !== null) return '¿Qué diámetro buscas para la maceta?';
            if (!$query->budgetMentioned) return '¿Qué tamaño buscas y qué presupuesto quieres usar, si tienes alguno?';
            return '¿Qué diámetro buscas?';
        }
        if ($category === 'accesorios' || $query->lightMentioned || $query->easyMentioned || $query->unusualAppearance === true) return null;
        if ($query->compactSpace || $query->hasEnvironmentCriteria() || $query->temperatureMentioned) {
            return '¿El lugar recibe poca luz, luz indirecta o sol directo?';
        }
        return '¿Prefieres una planta de cuidado fácil o de nivel avanzado?';
    }

    private function categoryAnswer(string $category, array $products, ShopChatQuery $query, array $optionReferences): array
    {
        $label = self::CATEGORY_LABELS[$category] ?? 'plantas';
        $matches = array_values(array_filter($products, function (array $product) use ($category, $query): bool {
            if (!$this->productMatchesCategory($product, $category)) return false;
            if (!$this->fitsSizePreference($product, $query)) return false;
            return (int)$product['stock'] >= max(1, $query->quantity ?? 0)
                && $this->fitsQueryBudget($product, $query);
        }));
        if ($matches === []) {
            if ($query->moreOptions && $optionReferences !== []) return $this->noMoreOptions($query);
            if ($category === 'flores' && !$query->hasRecommendationContext()) {
                return [
                    'message'=>'El catálogo actual de VERDELIA no tiene flores disponibles. Puedes revisar plantas ornamentales en la tienda.',
                    'store_link'=>'/tienda',
                ];
            }
            $reason = $query->budget !== null ? ' los criterios indicados, incluido el presupuesto'
                : match (true) {
                    $query->sizeCm !== null => ' el tamaño de '.$query->sizeCm.' cm',
                    $query->minSizeCm !== null => ' el rango de '.$query->minSizeCm.' a '.$query->maxSizeCm.' cm',
                    $query->maxSizeCm !== null => ' el tamaño máximo de '.$query->maxSizeCm.' cm',
                    ($query->quantity ?? 0) > 1 => ' la cantidad solicitada',
                    default => ' los criterios indicados',
                };
            return ['message'=>'No encontré '.$label.' disponibles que cumplan con'.$reason.'. Puedes ajustar los criterios para ver más opciones de esta categoría.'];
        }
        usort($matches, static fn(array $a, array $b): int =>
            ($query->lowPrice ? ((int)$a['price_cents'] <=> (int)$b['price_cents']) : 0)
            ?: strcmp($a['name'], $b['name']));
        $matches = $this->remainingOptions($matches, $query, $optionReferences);
        if ($matches === []) return $this->noMoreOptions($query);
        $shown = array_slice($matches, 0, self::MAX_RECOMMENDATIONS);
        $lines = array_map(fn(array $product): string => $this->productSummary($product, $query->quantity), $shown);
        $intro = $query->lowPrice
            ? 'Estas opciones de '.$label.' disponibles están ordenadas de menor a mayor precio.'
            : 'Estas opciones de '.$label.' están disponibles en el catálogo.';
        if ($query->potCompanion !== null && $category === 'macetas') {
            $intro = 'Puedes comparar estas macetas para '.$query->potCompanion.'. Las fichas indican el diámetro de cada maceta. Para confirmar el ajuste, falta la medida de la planta o de su recipiente actual.';
        }
        if ($category === 'macetas' && preg_match('/\b(irrompible|indestructible|resistente|golpe[se]?)\b/u', $query->text) === 1) {
            $intro .= ' Las fichas no indican resistencia a golpes, así que no puedo asegurar que alguna sea irrompible.';
        }
        return $this->recommendationReply($query, $category, $intro, $shown, $lines, count($matches));
    }

    private function productMatchesCategory(array $product, string $category): bool
    {
        $productCategory = ShopChatQuery::normalize((string)$product['category'].' '.(string)$product['category_name']);
        return match ($category) {
            'plantas' => preg_match('/\b(macetas?|accesorios?|regaderas?)\b/u', $productCategory) !== 1,
            'cactus' => preg_match('/\b(cactus|cact[uo]s)\b/u', ShopChatQuery::normalize((string)$product['name'])) === 1,
            'suculentas' => preg_match('/\bsuculentas?\b/u', $productCategory) === 1,
            'arboles' => preg_match('/\barbol(?:es)?\b/u', $productCategory) === 1,
            'arboles-frutales' => preg_match('/\barbol(?:es)?[\s-]+frutal(?:es)?\b/u', $productCategory) === 1,
            'bonsais' => preg_match('/\bbonsais?\b/u', $productCategory) === 1
                || ($this->productMatchesCategory($product, 'plantas') && preg_match('/\bbonsais?\b/u', ShopChatQuery::normalize((string)$product['name'])) === 1),
            'plantas-con-flores' => preg_match('/\bplantas?[\s-]+(?:con[\s-]+flores?|florales?|floridas?)\b/u', $productCategory) === 1,
            'flores' => preg_match('/\b(?:flor(?:es)?|florales?)\b/u', $productCategory.' '.ShopChatQuery::normalize((string)$product['name'])) === 1,
            default => str_contains($productCategory, $category),
        };
    }

    private function namedProducts(ShopChatQuery $query, array $products): array
    {
        // A category word alone is broader than a product whose name starts with it.
        $categoryAlias = !$query->trend && in_array($query->catalogCategory, ['cactus', 'suculentas'], true)
            ? $query->catalogCategory : null;
        if (!$query->trend && in_array($query->catalogCategory, ['arboles', 'arboles-frutales', 'bonsais'], true)) {
            $categoryAlias = $query->catalogCategory === 'bonsais' ? 'bonsai' : 'arbol';
        }
        $matches = $this->matchProducts($query->productText, $products, $categoryAlias);
        if ($query->potCompanion !== null) {
            return array_values(array_filter($matches, fn(array $product): bool => $this->productMatchesCategory($product, 'macetas')));
        }
        return $matches;
    }

    private function identifyPotCompanion(ShopChatQuery $query, array $products): void
    {
        if (!$query->potPairing || $query->potCompanion !== null) return;
        $plants = array_values(array_filter(
            $this->matchProducts($query->productText, $products),
            fn(array $product): bool => $this->productMatchesCategory($product, 'plantas')
        ));
        if (count($plants) === 1) $query->potCompanion = (string)$plants[0]['name'];
        elseif (count($plants) > 1) $query->potCompanion = 'las plantas que mencionaste';
    }

    private function potCompatibilityNote(ShopChatQuery $query): string
    {
        return 'Las fichas indican el diámetro de las macetas, pero carecen de medidas para confirmar el ajuste con '.$query->potCompanion.'.';
    }

    private function matchProducts(string $query, array $products, ?string $categoryAlias = null): array
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
            } elseif (strlen($alias) >= 4 && rtrim($alias, 's') !== rtrim($categoryAlias ?? '', 's')
                && !in_array($alias, ['planta', 'producto', 'articulo', 'maceta', 'regadera', 'accesorio'], true) && $this->mentions($query, $alias)) {
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
        if ($query->quantity === 0) {
            $message = $this->productSummary($product, null).' Mencionaste 0 unidades; dime cuántas necesitas para comprobar si hay suficientes.';
            return ['message'=>$message, 'products'=>[$this->publicProduct($product)]];
        }
        if ($query->watering && !$query->lightQuestion && $query->light === null && !$query->noDirect && !$query->easy) {
            $message = $this->productLabel($product).'. La ficha no especifica cada cuánto regarla.';
            if (preg_match('/\b(precio|cuesta|costaria|stock|existencias?|disponible|hay)\b/u', $query->text) === 1) {
                $message .= ' '.$this->productSummary($product, $query->quantity);
            }
            return ['message'=>$message, 'products'=>[$this->publicProduct($product)]];
        }
        if (preg_match('/\bcomo (?:llegaron|calcularon) a ese numero\b/u', $query->text)) {
            return ['message'=>'Si te refieres al pronóstico, la regresión ajusta una línea a las ventas confirmadas de los últimos seis meses completos y estima el primer mes posterior. Requiere ventas en al menos tres de esos meses. El precio y las existencias vienen del catálogo.'];
        }

        $facts = [];
        if ($query->care || $query->light !== null || $query->noDirect || $query->easy || $query->lightQuestion) {
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
            $relation = $this->fitsQueryBudget($product, $query) ? 'Está dentro de tu presupuesto.' : 'Queda fuera de tu presupuesto.';
            $facts[] = $this->productSummary($product, $query->quantity).' '.$relation;
        } elseif ($query->catalogIntent || $facts === []) {
            $facts[] = $this->productSummary($product, $query->quantity);
        }
        $message = implode(' ', $facts);
        $productCategory = ShopChatQuery::normalize((string)$product['category'].' '.(string)$product['category_name']);
        if (preg_match('/\b(macetas?|recipientes?)\b/u', $productCategory) === 1
            && preg_match('/\b(irrompible|indestructible|resistente|antigolpes?|no se rompe|inquebrantable)\b/u', $query->text) === 1) {
            $message .= ' La ficha no confirma resistencia a golpes o roturas; no puedo asegurar que sea irrompible.';
        }
        return ['message'=>$message, 'products'=>[$this->publicProduct($product)]];
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
        if ($quantity !== null && $quantity > 1) {
            $units = $quantity === 1 ? '1 unidad' : $quantity.' unidades';
            $availability .= $stock >= $quantity ? ' Sí hay '.$units.' para tu consulta.' : ' No hay '.$units.' disponibles.';
            if ($quantity > 1 && $stock >= $quantity) {
                $availability .= ' El subtotal de '.$units.' es '.$this->money((int)$product['price_cents'] * $quantity).' USD.';
            }
        }
        return $this->productLabel($product).' cuesta '.$this->money((int)$product['price_cents']).' USD. '.$availability;
    }

    private function productMatchesSize(array $product, int $sizeCm): bool
    {
        $text = ShopChatQuery::normalize((string)$product['name'].' '.(string)($product['description'] ?? ''));
        return preg_match('/\b'.preg_quote((string)$sizeCm, '/').'\s*cm\b/u', $text) === 1;
    }

    private function fitsSizePreference(array $product, ShopChatQuery $query): bool
    {
        if ($query->sizeCm !== null) return $this->productMatchesSize($product, $query->sizeCm);
        if ($query->maxSizeCm === null) return true;
        $name = ShopChatQuery::normalize((string)$product['name']);
        return preg_match('/\b(\d{1,3})\s*(?:cm|centimetros)\b/u', $name, $size) === 1
            && (int)$size[1] <= $query->maxSizeCm
            && ($query->minSizeCm === null || (int)$size[1] >= $query->minSizeCm);
    }

    private function fitsQueryBudget(array $product, ShopChatQuery $query): bool
    {
        if ($query->budget === null) return true;
        $price = (int)$product['price_cents'] * ($query->totalBudget ? max(1, $query->quantity ?? 1) : 1);
        return $price >= ($query->budget['min'] ?? 0) && $price <= ($query->budget['max'] ?? PHP_INT_MAX);
    }

    private function appendShippingCaveat(array $reply, ShopChatQuery $query): array
    {
        $notice = '';
        if (preg_match('/\b(envios?|enviar|entregas?|despachos?|delivery|flete)\b/u', $query->text)) {
            $notice .= ' No tengo tarifas ni destinos de envío confirmados.';
        }
        if (preg_match(self::PICKUP_PATTERN, $query->text)) {
            $notice .= ' '.self::PICKUP_NOTICE;
        }
        $reply['message'] .= $notice;
        if (isset($reply['intro'])) $reply['intro'] .= $notice;
        return $reply;
    }

    private function appendCurrencyCaveat(array $reply, ShopChatQuery $query): array
    {
        if ($query->unsupportedCurrency) {
            $reply['message'] = rtrim((string)($reply['message'] ?? '')).' El precio del catálogo está en USD; no hago conversión a otra moneda sin un tipo de cambio verificado.';
        }
        return $reply;
    }

    private function appendRetailerCaveat(array $reply, ShopChatQuery $query): array
    {
        if ($query->externalRetailer) {
            $reply['message'] = rtrim((string)($reply['message'] ?? '')).' Esta ficha corresponde al catálogo de VERDELIA y no confirma disponibilidad ni precio en otra tienda.';
        }
        return $reply;
    }

    private function appendTransactionCaveat(array $reply, ShopChatQuery $query): array
    {
        if ($query->transactionIntent) {
            $reply['message'] = rtrim((string)($reply['message'] ?? '')).' No puedo apartar ni confirmar pedidos desde el chat. Para completar la compra, usa el carrito y el checkout del sitio.';
        }
        return $reply;
    }

    private function acknowledgeBudget(array $reply, ShopChatQuery $query): array
    {
        if ($query->budgetMentioned && $query->budget === null) {
            $notice = 'Entendido: tomo que no tienes un límite de gasto. ';
            $reply['message'] = $notice.$reply['message'];
            if (isset($reply['intro'])) $reply['intro'] = $notice.$reply['intro'];
        }
        return $reply;
    }

    private function compareProducts(array $products, ShopChatQuery $query): array
    {
        $careRank = static fn(array $product): int => match (ShopChatQuery::normalize((string)$product['care_level'])) {
            'facil' => 0,
            'moderado' => 1,
            'dificil' => 2,
            default => 3,
        };
        if ($query->lowPrice) {
            usort($products, static fn(array $a, array $b): int => ((int)$a['price_cents'] <=> (int)$b['price_cents']));
        } elseif ($query->care || $query->easy) {
            usort($products, static fn(array $a, array $b): int => $careRank($a) <=> $careRank($b));
        }
        $lines = [];
        foreach (array_slice($products, 0, 5) as $product) {
            $facts = [$this->productSummary($product, $query->quantity)];
            if ((string)$product['light'] !== '') $facts[] = 'Luz: '.$product['light'].'.';
            if ((string)$product['care_level'] !== '') $facts[] = 'Cuidado: '.$product['care_level'].'.';
            $lines[] = implode(' ', $facts);
        }
        $message = $query->lowPrice ? 'Estas opciones están ordenadas de menor a mayor precio:' : 'Comparación según los datos del catálogo:';
        if (($query->care || $query->easy) && $products !== []) {
            $careProducts = array_values(array_filter($products, static fn(array $product): bool => trim((string)$product['care_level']) !== ''));
            if ($careProducts !== []) {
                $bestRank = min(array_map($careRank, $careProducts));
                $easiest = array_values(array_filter($careProducts, static fn(array $product): bool => $careRank($product) === $bestRank));
                $levels = array_unique(array_map(static fn(array $product): string => (string)$product['care_level'], $easiest));
                if (count($easiest) === 1 && $bestRank < 3) {
                    $message .= ' Según las fichas, la opción más fácil de cuidar es '.$easiest[0]['name'].' ('.$easiest[0]['care_level'].').';
                } elseif (count($levels) === 1 && $bestRank < 3) {
                    $message .= ' Las fichas clasifican a las opciones con el mismo nivel de cuidado: '.$easiest[0]['care_level'].'.';
                } else {
                    $message .= ' Las fichas no permiten distinguir una única opción con menor cuidado.';
                }
            }
        }
        $hasComparisonCriteria = $query->light !== null || $query->noDirect || $query->easy
            || $query->budget !== null || $query->quantity !== null;
        if ($hasComparisonCriteria) {
            $qualifying = array_values(array_filter($products, function (array $product) use ($query): bool {
                $inStock = (int)$product['stock'] >= max(1, $query->quantity ?? 0);
                $lightMatches = ($query->light === null && !$query->noDirect) || $this->lightScore($product, $query) > 0;
                $careMatches = !$query->easy || ShopChatQuery::normalize((string)$product['care_level']) === 'facil';
                return $inStock && $lightMatches && $careMatches && $this->fitsQueryBudget($product, $query);
            }));
            if (count($qualifying) === 1) {
                $message .= ' Según los criterios indicados y las fichas, la opción que coincide es '.$qualifying[0]['name'].'.';
            } elseif (count($qualifying) > 1) {
                $message .= ' Estas opciones coinciden con los criterios indicados según las fichas y el stock.';
            } elseif (($query->light !== null || $query->noDirect)
                && !array_filter($products, fn(array $product): bool => $this->lightScore($product, $query) > 0)) {
                $lightLabel = $query->noDirect && $query->light === null
                    ? 'sin sol directo'
                    : match ($query->light) {'low'=>'poca luz', 'medium'=>'luz indirecta o media', 'bright'=>'luz brillante', 'direct'=>'sol directo', default=>'la luz indicada'};
                $message .= ' Ninguna de las opciones comparadas queda confirmada para '.$lightLabel.' según sus fichas.';
            } else {
                $message .= ' Ninguna de las opciones comparadas cumple todos los criterios a la vez.';
            }
        }
        return $this->appendShippingCaveat([
            'message'=>$message."\n".implode("\n", $lines),
            'products'=>array_map([$this, 'publicProduct'], array_slice($products, 0, 5)),
        ], $query);
    }

    private function rankPlantOptions(array $products, ShopChatQuery $query): array
    {
        $ranked = [];
        foreach ($products as $product) {
            if ((int)$product['stock'] < max(1, $query->quantity ?? 0) || !$this->productMatchesCategory($product, $query->catalogCategory ?? 'plantas')) continue;
            if (!$this->fitsCarePreference($product, $query)) continue;
            if (!$this->fitsSizePreference($product, $query)) continue;
            if (!$this->fitsQueryBudget($product, $query)) continue;
            if ($query->unusualAppearance === true && !$this->hasDistinctiveDescription($product)) continue;
            if ($query->romanticGift && !$this->hasDistinctiveDescription($product)
                && !$this->productMatchesCategory($product, 'flores')) continue;
            $score = $this->lightScore($product, $query);
            if ($score > 0) $ranked[] = ['score'=>$score, 'product'=>$product];
        }
        usort($ranked, static fn(array $a, array $b): int => ($query->lowPrice ? ((int)$a['product']['price_cents'] <=> (int)$b['product']['price_cents']) : ($b['score'] <=> $a['score'])) ?: strcmp($a['product']['name'], $b['product']['name']));
        return array_column($ranked, 'product');
    }

    private function roomRecommendations(ShopChatQuery $query, array $products, array $optionReferences): array
    {
        $cards = [];
        $notes = [];
        $lines = [];
        $unknown = [];
        $shown = $query->moreOptions ? array_fill_keys($optionReferences, true) : [];
        $used = [];
        $hasRoomSpecificLight = in_array(true, array_column($query->rooms, 'mentioned'), true);
        $sharedLight = !$hasRoomSpecificLight || preg_match('/\b(ambos|ambas|los dos|las dos)\b/u', $query->text) === 1;
        $lightLabels = ['low'=>'poca luz', 'medium'=>'luz indirecta', 'bright'=>'luz brillante', 'direct'=>'sol directo'];

        foreach ($query->rooms as $room => $conditions) {
            $place = $room === 'cuarto' ? 'tu cuarto' : 'tu sala';
            $title = ucfirst($place);
            $selection = clone $query;
            // A room gets its own light conditions and stock check for one plant.
            if (count($query->rooms) > 1) $selection->quantity = 1;
            $useSharedLight = !$conditions['mentioned'] && $sharedLight && $query->lightMentioned && !$query->lightPreferenceCleared;
            $selection->light = $useSharedLight ? $query->light : $conditions['light'];
            $selection->noDirect = $useSharedLight ? $query->noDirect : $conditions['noDirect'];
            if ($conditions['noLight'] || ($useSharedLight && $query->noLight)) {
                $notes[] = ucfirst($place).' quedó sin recomendación porque indicaste que no tiene luz natural o está sin ventanas.';
                continue;
            }
            $knownLight = $conditions['mentioned'] || $useSharedLight;
            if ($conditions['conflict'] || ($useSharedLight && $query->conflictingLight)) {
                $notes[] = 'Las condiciones de luz para '.$place.' quedaron sin aclarar.';
                $unknown[] = $place;
                continue;
            }
            if (!$knownLight) {
                $unknown[] = $place;
                $selection->light = $room === 'cuarto' ? 'low' : 'medium';
            }
            $ranked = $this->rankPlantOptions($products, $selection);
            if ($ranked === [] && !$knownLight) {
                $selection->light = $selection->light === 'low' ? 'medium' : 'low';
                $ranked = $this->rankPlantOptions($products, $selection);
            }
            if ($query->compactSpace) {
                $compact = array_values(array_filter($ranked, [$this, 'hasCompactPlantDescription']));
                if ($compact !== []) $ranked = $compact;
            }
            $ranked = array_values(array_filter($ranked, static fn(array $product): bool => !isset($shown[$product['slug']])));
            $distinct = array_values(array_filter($ranked, static fn(array $product): bool => !isset($used[$product['slug']])));
            $reusable = $distinct === [] ? array_values(array_filter($ranked, static fn(array $product): bool => (int)$product['stock'] >= 2)) : [];
            $options = array_slice($distinct ?: $reusable, 0, 2);
            if ($options === []) {
                $notes[] = 'No encontré '.($query->moreOptions ? 'más ' : '').'opciones para '.$place.' con esos criterios.';
                continue;
            }
            $light = $lightLabels[$selection->light] ?? 'sin sol directo';
            $group = $title.($knownLight ? ' · '.$light : ' · si recibe '.$light);
            $lines[] = $group;
            foreach ($options as $product) {
                $cards[] = $this->publicProduct($product, $selection->quantity) + ['placement'=>$group];
                $used[$product['slug']] = true;
                $lines[] = $this->productSummary($product, $selection->quantity);
            }
        }
        if ($query->moreOptions && $cards === [] && $optionReferences !== []) return $this->noMoreOptions($query);
        $intro = $cards === [] ? implode(' ', $notes) : 'Estas son opciones disponibles según la luz de cada espacio.';
        if ($cards !== [] && $notes !== []) $intro .= ' '.implode(' ', $notes);
        if ($query->totalBudget && count($query->rooms) > 1 && $query->budget !== null) {
            $intro .= ' Los precios son por unidad. Para respetar el presupuesto total, comprueba la suma de las dos plantas que elijas.';
        }
        $intro .= $this->environmentCaveat($query);
        $reply = ['intro'=>$intro, 'message'=>trim($intro."\n".implode("\n", $lines)), 'products'=>$cards];
        if ($unknown !== [] && !$query->recommendationContextActive && !$query->skipPreferences) {
            $reply['follow_up'] = '¿Qué luz natural recibe '.implode(' y ', $unknown).'?';
        }
        return $reply;
    }

    private function unlistedTreeReply(ShopChatQuery $query, array $products): array
    {
        $message = 'En el catálogo actual de VERDELIA no hay productos publicados como árboles.';
        $reply = ['message'=>$message, 'store_link'=>'/tienda'];
        if ($query->moreOptions) return $reply + ['keep_product_options'=>true];
        if ($query->noLight || $query->conflictingLight || $query->unsupportedCurrency
            || preg_match('/\b(frutal(?:es)?|exterior|patio|jardin|terraza|bonsai|navidad|artificial|dar sombra)\b/u', $query->text)) {
            return $reply;
        }

        $alternativeQuery = clone $query;
        $alternativeQuery->catalogCategory = 'plantas';
        $excluded = array_column($this->matchProducts($query->excludedText, $products), 'id');
        $alternatives = array_filter($products, function (array $product) use ($excluded): bool {
            $description = ShopChatQuery::normalize((string)($product['description'] ?? ''));
            return $product['category'] === 'interior' && !in_array($product['id'], $excluded, true)
                && preg_match('/\bdar altura\b/u', $description) === 1;
        });
        $option = $this->rankPlantOptions($alternatives, $alternativeQuery)[0] ?? null;
        if ($option === null) return $reply;

        $intro = $message.' Si buscas dar altura a un espacio interior, puedes considerar '.$option['name'].'.';
        return [
            'intro'=>$intro,
            'message'=>$intro.' '.$this->productSummary($option, $query->quantity),
            'products'=>[$this->publicProduct($option, $query->quantity)],
            'store_link'=>'/tienda',
        ];
    }

    private function storeInformation(ShopChatQuery $query, array $products): ?array
    {
        $text = $query->text;
        $supplies = [
            ['perlita', 'perlita'],
            ['fibra de coco', 'fibra de coco'],
            ['tierra o sustrato', 'tierras?|sustratos?|mezclas? para macetas?'],
            ['abono o fertilizante', 'abonos?|fertilizantes?|compost'],
            ['semillas', 'semillas?'],
        ];
        $buying = preg_match('/\b(venden?|vender|tienen?|hay|stock|existencias?|disponib\w*|busco|comprar|precio|cuanto cuesta)\b/u', $text) === 1
            || preg_match('/^(?:(?:hola|buenas)\s+)?(?:necesito|quiero|quisiera|me gustaria)\b/u', $text) === 1;
        $supplyNotes = [];
        $supplyProducts = [];
        foreach ($supplies as [$label, $terms]) {
            $mentionsSupply = '/\b(?:'.$terms.')\b/u';
            if (!preg_match($mentionsSupply, $text)) continue;
            if (!$buying && preg_match('/\b(uso|usar|poner|pongo|necesit\w*|sirve|recomiend\w*|elegir|que tipo|cual|como|trasplantar)\b/u', $text)) {
                if ($label === 'tierra o sustrato') {
                    return ['message'=>'Para una planta de interior, busca un sustrato formulado para macetas y fíjate en que drene bien. Las fichas de VERDELIA no especifican una mezcla exacta para cada planta.'];
                }
                if ($label === 'abono o fertilizante') {
                    return ['message'=>'Las fichas de VERDELIA no indican el tipo ni la dosis de abono. Consulta la etiqueta del fertilizante y las indicaciones para tu planta antes de usarlo.'];
                }
            }
            if (!$buying && !preg_match('/^(?:'.$terms.')(?: para (?:macetas?|interior|plantas?|suculentas?|cactus))?$/u', $text)) continue;
            $available = [];
            $listed = false;
            foreach ($products as $product) {
                if (!preg_match($mentionsSupply, ShopChatQuery::normalize((string)$product['name']))) continue;
                $listed = true;
                if ((int)$product['stock'] > 0) $available[] = $product;
            }
            if ($available !== []) {
                $supplyNotes[] = 'Hay existencias de '.$label.'.';
                foreach ($available as $product) $supplyProducts[$product['id']] = $product;
            } else {
                $supplyNotes[] = $listed
                    ? 'El catálogo incluye '.$label.', pero está agotado en este momento.'
                    : 'El catálogo actual de VERDELIA no incluye '.$label.' para la venta.';
            }
        }
        if ($supplyNotes !== []) {
            $intro = implode(' ', $supplyNotes);
            if ($supplyProducts === []) return ['message'=>$intro, 'store_link'=>'/tienda'];
            return $this->categorySupplyReply(array_values($supplyProducts), $intro);
        }
        $named = $this->namedProducts($query, $products);
        if ($this->mentionsDrainage($text) && count($named) === 1 && $this->productMatchesCategory($named[0], 'macetas')) {
            return null;
        }
        if ($this->mentionsDrainage($text) || preg_match('/\btrasplant\w*\b/u', $text)) {
            return ['message'=>'Para plantar directamente, usa un recipiente con orificios de drenaje para que salga el exceso de agua. Las fichas de VERDELIA no indican cuáles macetas tienen esos orificios.'];
        }
        if ($named !== []) return null;
        if (preg_match('/\b(?:duen[oa]s?|propietari[oa]s?|a quien pertenece|de quien es)\b/u', $text)
            && preg_match('/\b(?:tienda|verdelia|negocio|sitio)\b/u', $text)) {
            return ['message'=>'El sitio no publica quién es el propietario de VERDELIA. La página Contacto muestra el equipo de diseño y desarrollo.', 'store_link'=>'/contacto'];
        }
        if ($query->catalogIntent && preg_match('/\b(?:toyota|automoviles?|vehiculos?|camionetas?|coche(?:s)?|carro(?:s)?(?! de compras))\b/u', $text)) {
            return ['message'=>'VERDELIA no vende vehículos. El catálogo reúne plantas, macetas y accesorios de jardinería.', 'store_link'=>'/tienda'];
        }
        if (preg_match('/\b(horarios?|ubicacion|tienda fisica|local fisico|donde (?:queda|quedan|estan|se ubican)|abren|cierran)\b/u', $text)) {
            return ['message'=>'VERDELIA funciona como tienda en línea. El sitio no publica una dirección ni un horario de atención presencial.', 'store_link'=>'/contacto'];
        }
        if (preg_match('/\b(contacto|contactar(?:los)?|correo(?: de la tienda)?|whatsapp|atencion al cliente)\b/u', $text)) {
            return ['message'=>'En la página Contacto del sitio puedes consultar los datos publicados de VERDELIA.', 'store_link'=>'/contacto'];
        }
        if (preg_match('/\b(pagar|pagos?|paypal|con tarjeta|aceptan tarjetas?|tarjeta de (?:credito|debito)|compra real|comprar de verdad)\b/u', $text)) {
            return ['message'=>'Al finalizar el pedido puedes usar el simulador de pago de VERDELIA. La simulación no cobra dinero ni solicita los datos de una tarjeta o credenciales de PayPal.', 'store_link'=>'/preguntas-frecuentes'];
        }
        if (preg_match('/\b(mis pedidos|mi pedido|ver mi compra|seguimiento (?:del|de mi) pedido|mis recibos?)\b/u', $text)) {
            return ['message'=>'Inicia sesión y abre Mis pedidos para consultar el estado y el recibo de cada pedido de tu cuenta.', 'store_link'=>'/cuenta/pedidos'];
        }
        if (preg_match('/\b(cuenta|registr(?:ar|arme|arme en|o)|iniciar sesion)\b/u', $text)
            && preg_match('/\b(como|puedo|necesito|crear|registrarme|registro)\b/u', $text)) {
            return ['message'=>'Puedes crear tu cuenta desde Registrarse. Con la sesión iniciada podrás revisar tus pedidos y conservar el carrito entre visitas.', 'store_link'=>'/registro'];
        }
        if (preg_match('/\b(envios?|entregas?|despachos?|delivery|domicilio|flete)\b/u', $text)
            && !preg_match(self::PICKUP_PATTERN, $text) && !$query->unsupportedCurrency) {
            return ['message'=>'El proceso de compra de VERDELIA es simulado. Los pedidos en el sitio no generan envíos ni entregas.', 'store_link'=>'/preguntas-frecuentes'];
        }
        return null;
    }

    private function mentionsDrainage(string $text): bool
    {
        return preg_match('/\b(drenaje|agujeros? (?:de drenaje|en la base)|orificios? en la base)\b/u', $text) === 1;
    }

    private function categorySupplyReply(array $available, string $intro): array
    {
        $shown = array_slice($available, 0, self::MAX_RECOMMENDATIONS);
        return [
            'intro'=>$intro,
            'message'=>$intro."\n".implode("\n", array_map(fn(array $product): string => $this->productSummary($product, null), $shown)),
            'products'=>array_map(fn(array $product): array => $this->publicProduct($product), $shown),
            'more_results'=>count($available) > count($shown),
        ];
    }

    private function recommend(array $products, ShopChatQuery $query, array $optionReferences = []): array
    {
        $ranked = $this->rankPlantOptions($products, $query);
        if ($ranked === []) {
            if ($query->moreOptions && $optionReferences !== []) return $this->noMoreOptions($query);
            return $this->noRecommendationMatch($products, $query);
        }
        $compact = $query->compactSpace
            ? array_values(array_filter($ranked, [$this, 'hasCompactPlantDescription']))
            : [];
        if ($compact !== []) $ranked = $compact;
        $candidates = $this->remainingOptions($ranked, $query, $optionReferences);
        if ($candidates === []) return $this->noMoreOptions($query);
        $shown = array_slice($candidates, 0, self::MAX_RECOMMENDATIONS);
        $names = [];
        foreach ($shown as $product) {
            $detail = $this->productLabel($product).'. La ficha indica «'.($product['light'] ?: 'luz no especificada').'».';
            $detail .= ' Precio: '.$this->money((int)$product['price_cents']).' USD por unidad. Hay '.(int)$product['stock'].' unidades disponibles.';
            if (($query->quantity ?? 0) > 1) {
                $detail .= ' El subtotal por '.$query->quantity.' unidades es '.$this->money((int)$product['price_cents'] * $query->quantity).' USD.';
            }
            if ($query->easy) $detail .= ' Cuidado fácil.';
            $names[] = $detail;
        }
        $criteria = [];
        if ($query->light !== null) $criteria[] = ['low'=>'poca luz', 'medium'=>'luz indirecta o media', 'bright'=>'luz brillante', 'direct'=>'sol directo'][$query->light];
        if ($query->noDirect) $criteria[] = 'sin sol directo';
        if ($query->easy) $criteria[] = 'de cuidado fácil';
        if ($query->carePreference === 'advanced') $criteria[] = 'de cuidado avanzado';
        if ($query->unusualAppearance === true) $criteria[] = 'con hojas o formas llamativas según sus fichas';
        if ($query->budget !== null) $criteria[] = 'dentro de tu presupuesto';
        elseif ($query->budgetMentioned) $criteria[] = 'sin un límite de precio indicado';
        if ($query->quantity !== null && $query->quantity > 1) $criteria[] = 'con al menos '.$query->quantity.' unidades disponibles';
        $hasFlowersInStock = (bool)array_filter($products, fn(array $product): bool =>
            $this->productMatchesCategory($product, 'flores') && (int)$product['stock'] > 0);
        $intro = $query->romanticGift
            ? (($hasFlowersInStock ? '' : 'No hay flores con existencias disponibles en el catálogo. ')
                .'Las fichas no asignan un significado romántico a las plantas, así que no puedo señalar una como «la más romántica». Estas opciones ornamentales destacan por rasgos visuales descritos en el catálogo.')
            : ($criteria === [] ? ($query->catalogCategory === 'arboles'
                ? 'Estos árboles están disponibles. Consulta sus fichas para ver sus características.'
                : 'Estas plantas están disponibles. Sus fichas indican qué luz y cuidados necesita cada una.')
                : 'Encontré estas opciones que coinciden con los datos del catálogo ('.implode(', ', $criteria).').');
        if ($compact !== []) {
            $intro .= ' Sus fichas las describen como pequeñas o compactas.';
        } elseif ($query->compactSpace) {
            $intro .= ' Entre las que cumplen tus otros criterios, te muestro opciones sin confirmar su tamaño.';
        }
        if ($query->lowPrice) $intro .= ' Están ordenadas de menor a mayor precio.';
        $intro .= $this->environmentCaveat($query);
        return $this->recommendationReply($query, $query->catalogCategory ?? 'plantas', $intro, $shown, $names, count($candidates));
    }

    private function remainingOptions(array $products, ShopChatQuery $query, array $optionReferences): array
    {
        if (!$query->moreOptions || $optionReferences === []) return $products;
        $offset = 0;
        foreach ($products as $index => $product) {
            if (in_array($product['slug'], $optionReferences, true)) $offset = $index + 1;
        }
        return array_slice($products, $offset);
    }

    private function noMoreOptions(ShopChatQuery $query): array
    {
        $message = 'Ya viste todas las opciones disponibles que coinciden con esos criterios.';
        if ($query->compactSpace) $message .= ' Si quieres que amplíe la búsqueda, puedes decir «me da igual el tamaño».';
        return ['message'=>$message, 'keep_product_options'=>true];
    }

    private function recommendationReply(ShopChatQuery $query, string $category, string $intro, array $products, array $lines, int $total): array
    {
        $reply = [
            'intro'=>$intro,
            'message'=>$intro."\n".implode("\n", $lines),
            'products'=>array_map(fn(array $product): array => $this->publicProduct($product, $query->quantity), $products),
        ];
        if ($total > count($products)) {
            $reply['intro'] .= ' Te muestro '.count($products).' de '.$total.' coincidencias.';
            $reply['message'] .= "\n".$total.' coincidencias en el catálogo.';
            $reply['more_results'] = true;
        }
        $followUp = $this->recommendationFollowUp($query, $category);
        if ($followUp !== null) $reply['follow_up'] = $followUp;
        return $reply;
    }

    private function fitsCarePreference(array $product, ShopChatQuery $query): bool
    {
        $care = ShopChatQuery::normalize((string)($product['care_level'] ?? ''));
        return match ($query->carePreference) {
            'easy' => $care === 'facil',
            'advanced' => in_array($care, ['avanzada', 'avanzado', 'dificil'], true),
            default => true,
        };
    }

    private function hasCompactPlantDescription(array $product): bool
    {
        $name = ShopChatQuery::normalize((string)$product['name']);
        if (preg_match('/\b(?:mini|compact[oa]|pequen[oa])\b/u', $name) === 1) return true;

        $description = ShopChatQuery::normalize((string)($product['description'] ?? ''));
        return preg_match('/\b(?:porte|roseta|silueta|planta|cactus|suculenta)\s+(?:compact[oa]|pequen[oa])\b|\b(?:pequen[oa]|compact[oa])\s+(?:planta|cactus|suculenta)\b/u', $description) === 1;
    }

    private function hasDistinctiveDescription(array $product): bool
    {
        $description = ShopChatQuery::normalize((string)($product['description'] ?? ''));
        return preg_match('/\b(?:rosetas?|forma de violin|porte escultural|hojas (?:\w+ ){0,3}recortadas)\b/u', $description) === 1;
    }

    private function noRecommendationMatch(array $products, ShopChatQuery $query): array
    {
        $alternatives = [];
        foreach ($products as $product) {
            if ((int)$product['stock'] < max(1, $query->quantity ?? 0) || !$this->productMatchesCategory($product, $query->catalogCategory ?? 'plantas')) continue;
            if (!$this->fitsCarePreference($product, $query)) continue;
            if ($this->lightScore($product, $query) <= 0) continue;
            if (!$this->fitsSizePreference($product, $query)) continue;
            if ($query->unusualAppearance === true && !$this->hasDistinctiveDescription($product)) continue;
            if ($query->romanticGift && !$this->hasDistinctiveDescription($product)
                && !$this->productMatchesCategory($product, 'flores')) continue;
            if ($query->budget !== null) {
                if ($this->fitsQueryBudget($product, $query)) continue;
                $price = (int)$product['price_cents'] * ($query->totalBudget ? max(1, $query->quantity ?? 1) : 1);
                $distance = $price < ($query->budget['min'] ?? 0)
                    ? ($query->budget['min'] - $price)
                    : ($price - ($query->budget['max'] ?? PHP_INT_MAX));
                if ($distance > 1000) continue;
            }
            $alternatives[] = ['score'=>$this->lightScore($product, $query), 'product'=>$product];
        }
        usort($alternatives, static fn(array $a, array $b): int => ($b['score'] <=> $a['score']) ?: strcmp($a['product']['name'], $b['product']['name']));

        $requested = [];
        if ($query->noLight) $requested[] = 'luz natural';
        elseif ($query->noDirect && $query->light === null) $requested[] = 'sin sol directo';
        elseif ($query->light !== null) $requested[] = match ($query->light) {
            'low'=>'poca luz', 'medium'=>'luz indirecta o media', 'bright'=>'luz brillante', 'direct'=>'sol directo', default=>'la luz indicada',
        };
        if ($query->easy) $requested[] = 'cuidado fácil';
        if ($query->carePreference === 'advanced') $requested[] = 'cuidado avanzado';
        if ($query->unusualAppearance === true) $requested[] = 'formas llamativas descritas en la ficha';
        if ($query->romanticGift) $requested[] = 'rasgos visuales documentados para un regalo';
        if ($query->budget !== null) $requested[] = 'precio dentro del presupuesto';
        if (($query->quantity ?? 0) > 1) $requested[] = 'al menos '.$query->quantity.' unidades';
        if ($requested === []) $requested[] = 'los criterios indicados';
        $category = self::CATEGORY_LABELS[$query->catalogCategory] ?? 'plantas';
        $message = 'No encontré '.$category.' disponibles que cumplan '.(count($requested) > 1 ? 'todos estos criterios: ' : 'este criterio: ').implode(' y ', $requested).'.';
        $productsForLinks = [];
        if ($alternatives !== []) {
            $alternative = $alternatives[0]['product'];
            $alternativePrice = (int)$alternative['price_cents'] * ($query->totalBudget ? max(1, $query->quantity ?? 1) : 1);
            $priceLabel = $query->totalBudget && ($query->quantity ?? 0) > 1
                ? 'el subtotal por '.$query->quantity.' unidades sería '.$this->money($alternativePrice)
                : 'cuesta '.$this->money($alternativePrice);
            $basis = [];
            if ($query->light !== null || $query->noDirect || $query->noLight) $basis[] = 'luz';
            if ($query->carePreference !== null) $basis[] = 'cuidado';
            if ($query->budget !== null) $basis[] = 'precio';
            $basisText = $basis === [] ? 'los criterios indicados' : implode(' y ', $basis);
            $message .= ' La alternativa más cercana por '.$basisText.' es '.$this->productLabel($alternative).', '.$priceLabel.' USD'.($query->budget !== null ? ' y queda fuera del rango' : '').'.';
            $productsForLinks[] = $this->publicProduct($alternative);
        }
        $message .= $this->environmentCaveat($query);
        $message .= ' Puedes ampliar el presupuesto o relajar algún criterio para ver otras opciones.';
        return ['message'=>$message, 'products'=>$productsForLinks];
    }

    private function environmentCaveat(ShopChatQuery $query): string
    {
        $caveats = [];
        if ($query->hasEnvironmentCriteria()) {
            $conditions = [];
            if ($query->humidity === 'high') $conditions[] = 'mucha humedad';
            if ($query->humidity === 'low') $conditions[] = 'ambiente seco';
            if ($query->ventilation === 'poor') $conditions[] = 'poca ventilación';
            if ($query->ventilation === 'good') $conditions[] = 'buena ventilación';
            if ($conditions !== []) $caveats[] = 'Me comentas que el espacio tiene '.implode(' y ', $conditions).'.';
            $caveats[] = 'Las fichas no registran tolerancia a humedad ni ventilación; esa compatibilidad no está confirmada.';
        }
        if ($query->temperatureMentioned) {
            $caveats[] = 'Las fichas tampoco confirman para qué temperaturas es adecuada cada planta.';
        }
        if ($query->compactSpace) {
            $caveats[] = 'El catálogo no especifica dimensiones, así que no puedo confirmar cuánto espacio ocupa cada planta.';
        }
        if ($query->unusualAppearance === true) {
            $caveats[] = 'El catálogo no documenta su rareza ni las clasifica como exóticas.';
        }
        if ($query->containerSizeCm !== null) {
            $caveats[] = 'Las fichas no confirman qué plantas se adaptan a una maceta de '.$query->containerSizeCm.' cm.';
        }
        return $caveats === [] ? '' : ' '.implode(' ', $caveats);
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

    private function publicProduct(array $product, ?int $quantity = null): array
    {
        $image = (string)($product['image'] ?? '');
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*\.(?:png|webp|jpe?g|svg)$/iD', $image)) $image = 'product-placeholder.svg';
        $details = [
            'name'=>(string)$product['name'],
            'url'=>'/producto/'.rawurlencode((string)$product['slug']),
            'image'=>'/assets/images/products/'.$image,
            'image_alt'=>$image === 'product-placeholder.svg' ? '' : (string)($product['image_alt'] ?? ''),
            'description'=>trim((string)($product['description'] ?? '')),
            'price'=>$this->money((int)$product['price_cents']).' USD',
            'stock'=>max(0, (int)$product['stock']),
            'light'=>(string)($product['light'] ?? ''),
            'care'=>(string)($product['care_level'] ?? ''),
        ];
        if ($this->productMatchesCategory($product, 'macetas')
            && preg_match('/\b(\d{1,3})\s*cm\b/i', (string)$product['name'], $size)) {
            $details['size'] = $size[1].' cm';
        }
        if (($quantity ?? 0) > 1 && (int)$product['stock'] >= $quantity) {
            $details['subtotal'] = $this->money((int)$product['price_cents'] * $quantity).' USD por '.$quantity.' unidades';
        }
        return $details;
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
