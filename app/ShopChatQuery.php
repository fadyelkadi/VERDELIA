<?php
declare(strict_types=1);

final class ShopChatQuery
{
    public const LAST_OPTION = -1;
    private const DIRECT_LIGHT = '(?:sol directo|luz (?:solar )?directa|luz directa del sol|sol(?: natural)?(?: de lleno)?)';
    private const LOW_LIGHT = '(?:poca luz|poco luz|poca iluminacion|poco luminosa|luz (?:baja|tenue|suave)|baja luminosidad|sombra|sombreado|casi no (?:entra|recibe|tiene) (?:luz|sol)|(?:cuarto|habitacion|espacio|sala|dormitorio) oscur[oa])';
    private const MEDIUM_LIGHT = '(?:luz indirecta|indirecta|luz media|luz moderada|media luminosidad)';
    private const BRIGHT_LIGHT = '(?:luz brillante|brillante|muy luminos[oa]|mucha luz|buena iluminacion|buena luz natural)';
    private const EXCLUSION = 'no (?:quiero|busco|me interesa|me recomiendes)|sin|evita(?:r)?|excepto';
    private const AMOUNT = '([0-9]{1,6}(?:[.,][0-9]{1,2})?)(?![0-9]|[.,][0-9])';
    private const USD = '(?:usd|dolares?(?: estadounidenses)?|\x24)';
    private const NO_BUDGET = '\b(?:sin presupuesto|no tengo presupuesto|presupuesto ilimitado|sin limite(?: de gasto| de presupuesto)?|(?:prefiero|quiero) no (?:decir|dar|compartir|indicar)(?:te)? (?:mi |el |un )?presupuesto|no (?:quiero|voy a) (?:decir|dar|compartir|indicar)(?:te)? (?:mi |el |un )?presupuesto|(?:da igual|no (?:me )?importa) (?:el )?precio|cualquier precio|sin filtro de precio)\b';

    public string $text;
    public string $productText;
    public string $excludedText;
    public bool $petSafety;
    public bool $petEmergency;
    public bool $childSafety;
    public bool $childEmergency;
    public bool $medicalClaim;
    public bool $unsupportedPlantEffect;
    public bool $unsupportedClimateRequirement;
    public ?string $botanicalOriginRegion;
    public bool $externalRetailer;
    public bool $transactionIntent;
    public bool $restrictedPlant;
    public bool $possibleRestrictedName;
    public bool $noLight;
    public bool $noDirect;
    public ?string $light;
    public bool $lightMentioned;
    public bool $lightPreferenceCleared;
    public bool $conflictingLight;
    public bool $easy;
    public bool $easyMentioned;
    public ?string $carePreference;
    public bool $care;
    public bool $watering;
    public bool $lightQuestion;
    public bool $compactSpace;
    public ?bool $unusualAppearance;
    public bool $romanticGift;
    public bool $compactSpaceMentioned;
    public bool $temperatureMentioned;
    public bool $skipPreferences;
    public bool $restartSearch;
    public bool $moreOptions;
    public bool $potPairing;
    public ?string $potCompanion = null;
    public bool $trend;
    public bool $unsupportedHorizon;
    public bool $nextCalendarMonth;
    public ?array $budget;
    public bool $budgetMentioned = false;
    public bool $invalidBudgetRange = false;
    public bool $conflictingBudget = false;
    public bool $unsupportedCurrency;
    public ?int $quantity;
    public bool $invalidQuantity;
    public ?int $sizeCm;
    public ?int $minSizeCm = null;
    public ?int $maxSizeCm;
    public ?int $containerSizeCm;
    public ?int $standaloneNumber;
    public bool $totalBudget;
    public ?bool $totalBudgetPreference;
    public bool $lowPrice;
    public ?int $selectedOption;
    public bool $catalogIntent;
    public bool $recommendation;
    public ?string $catalogCategory;
    public ?string $humidity;
    public bool $humidityMentioned;
    public bool $conflictingHumidity;
    public ?string $ventilation;
    public bool $ventilationMentioned;
    public bool $conflictingVentilation;
    public bool $recommendationContextActive = false;
    public array $rooms;

    public function __construct(string $message)
    {
        $folded = self::fold($message);
        $this->text = $text = self::normalize($message);
        $withoutProduct = '^(?:(?:mejor|pero|y)\s+)?sin(?!\s+(?:presupuesto|limite|luz|sol|ventilacion|costo|coste|filtro|mas preguntas)\b)';
        preg_match_all('/(?:\b(?:no (?:quiero|busco|me interesa|me recomiendes)|excepto)|'.$withoutProduct.')\s+([^,;.!?]+?)(?=\s+(?:sino|pero|dime|muestrame|quiero|busco|prefiero)\b|[,;.!?]|$)/u', $folded, $negations);
        $this->excludedText = self::normalize(implode(' ', $negations[1]));
        $this->productText = self::normalize(str_replace($negations[0], ' ', $folded));

        $this->petSafety = preg_match('/\b(mascotas?|gat[oa]s?|gatit[oa]s?|gatic[oa]s?|michis?|felin[oa]s?|perr[oa]s?|perrit[oa]s?|cachorr[oa]s?|canin[oa]s?|cats?|dogs?|animales?|conejos?|loros?|aves?|(?:pet|cat|dog)[ -]?(?:friendly|safe)|toxic\w*|venenos[oa]s?)\b/u', $text) === 1;
        $this->petEmergency = $this->petSafety && preg_match('/\b(se comio|comio|ingirio|se trago|trago|mordio|lamio|consumio|ate|chewed|swallowed)\b/u', $text) === 1;
        $this->childSafety = preg_match('/\b(bebes?|ninos?|infantes?|children|child|toddlers?)\b/u', $text) === 1;
        $this->childEmergency = $this->childSafety && preg_match('/\b(se comio|comio|ingirio|se trago|trago|mordio|lamio|consumio|ate|chewed|swallowed)\b/u', $text) === 1;
        $medicalCondition = preg_match('/\b(asma|alergi\w*|insomnio|ansiedad|depresion|diabetes|infeccion\w*|cancer|hipertension|presion arterial)\b/u', $text) === 1;
        $medicalAction = preg_match('/\b(cura\w*|curar|trata\w*|alivia\w*|previene\w*|remedio\w*|medicinal|ayuda con|ayuda a|sirve para|healing|treats?|cures?)\b/u', $text) === 1;
        $this->medicalClaim = $medicalCondition && ($medicalAction || preg_match('/\b(plantas?|remedios?)\b/u', $text) === 1);
        $this->unsupportedPlantEffect = preg_match('/\b(humedad|moho|mold|mould)\b/u', $text) === 1
            && preg_match('/\b(elimina\w*|absorbe\w*|quita\w*|reduce\w*|controla\w*|remueve\w*|remove\w*|absorb\w*)\b/u', $text) === 1;
        $this->temperatureMentioned = preg_match('/\b(?:grados|temperaturas?|calor|caluros\w*|calid[oa]s?|frio|fri[oa]s?|heladas?|clima tropical)\b/u', $text) === 1;
        $climateMention = $this->temperatureMentioned || preg_match('/\b(sequia|viento)\b/u', $text) === 1;
        $specificTemperature = preg_match('/(?<![\d.,])[-+]?\d{1,3}(?:[.,]\d+)?\s*(?:°\s*[cf]|grados(?:\s+[cf])?)(?![\w\d])/u', $folded) === 1;
        $asksAboutPlant = preg_match('/\b(plantas?|arbol(?:es)?|cactus|suculentas?)\b/u', $text) === 1;
        $this->unsupportedClimateRequirement = ($specificTemperature && $asksAboutPlant) || ($climateMention
            && preg_match('/\b(resist\w*|aguant\w*|sobreviv\w*|toler\w*|soport\w*|prosper\w*|crezca[n]?|crece[n]?|adaptad[oa]s?|para (?:un |una |ese |este )?clima|para (?:el )?(?:frio|calor)|para (?:zonas?|ambientes?) fri[oa]s?)\b/u', $text) === 1);
        $amazonRegion = preg_match('/\b(?:amazonas|amazonia|amazonic[oa]s?|amazonian[as]?)\b/u', $text) === 1;
        $botanicalReference = preg_match('/\b(plantas?|arbol(?:es)?|especies?|flora|vegetacion|cactus|suculentas?|palmas?|monstera|esas|estas|ellas|son|sean)\b/u', $text) === 1;
        $originWording = preg_match('/\b(?:de|del|desde|nativ[oa]s?|originari[oa]s?|proceden(?:cia)?|provienen?|vienen?|venga[n]?|son de|sean de|de donde)\b/u', $text) === 1;
        $this->botanicalOriginRegion = $amazonRegion && ($botanicalReference || $originWording) ? 'Amazonía' : null;
        $amazonRetailer = preg_match('/\bamazon\b/u', $text) === 1 && $this->botanicalOriginRegion === null;
        $this->externalRetailer = preg_match('/\b(ikea|sodimac|homecenter|epa)\b/u', $text) === 1 || $amazonRetailer;
        $this->transactionIntent = preg_match('/\b(apartame|apartar|reserva(?:me|r)?|comprame|comprar|comprarl[oa]s?|ordenar|ordenamelo|hazme (?:un )?pedido|realiza(?:r)? (?:un )?pedido)\b/u', $text) === 1;
        $this->restrictedPlant = preg_match('/\b(cannabis|marihuana|marijuana|canamo|weed|ganja)\b/u', $text) === 1;
        $this->possibleRestrictedName = preg_match('/\bjanabi\b/u', $this->productText) === 1;
        $this->noLight = $this->lacksNaturalLight($text);
        $easyNegative = preg_match('/\b(?:(?:me da igual|no (?:me )?importa)\s+(?:(?:el|su)\s+)?(?:nivel de )?cuidados?|(?:no (?:me importa|es necesario|hace falta|tiene que ser)|no importa si (?:es|sea))\s+(?:que sea\s+)?(?:facil|sencill[oa]))\b/u', $text) === 1;
        $easyPositive = preg_match('/\b(facil|faciles|sencilla|sencillo|principiantes?|poco cuidado|bajo mantenimiento)\b/u', $text) === 1;
        $advanced = preg_match('/\b(?:de cuidado|cuidados?|mantenimiento) (?:dificil|dificiles|avanzad[oa]s?)\b|\b(?:dificil|dificiles) de cuidar\b|^(?:avanzad[oa]|dificil)$/u', $text) === 1;
        $this->easyMentioned = $easyNegative || $easyPositive || $advanced;
        $this->carePreference = $easyNegative ? null : ($advanced ? 'advanced' : ($easyPositive ? 'easy' : null));
        $this->easy = $this->carePreference === 'easy';
        $this->watering = preg_match('/\b(riego|regar|riega|regarse|riegue|cuanta agua|frecuencia de riego)\b/u', $text) === 1;
        $this->care = $this->watering || preg_match('/\b(cuidados?|cuidar|cuido|cuida|cuidarla|cuidarlo|mantenimiento)\b/u', $text) === 1;
        $this->trend = preg_match('/\b(ventas?|demanda|tendencia|proyeccion|estimacion|estiman|estimado|prevision|predecir|pronostic\w*|vendera[n]?|venderia[n]?|vendieron)\b/u', $text) === 1;
        $this->unsupportedHorizon = preg_match(
            '/\b(?:\d+|varios?|varias?|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once|doce)\s+(?:meses?|semanas?|dias?|anos?|trimestres?)\b|\b(?:trimestre|semestre|anio|ano|proximos meses|meses que vienen|en (?:el )?20\d{2}|en (?:enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre)|vendieron|ventas historicas|ventas (?:de |del )?(?:hoy|ayer|este mes|mes pasado))\b/u',
            preg_replace('/\b(?:1|un)\s+mes\b/u', ' ', $text) ?? $text
        ) === 1;
        $this->nextCalendarMonth = preg_match('/\b(proximo mes|mes que viene|dentro de (?:un|1) mes|en (?:un|1) mes)\b/u', $text) === 1;
        $this->romanticGift = preg_match('/\b(?:romantic[oa]s?|novi[oa]s?|parejas?|aniversarios?)\b/u', $text) === 1;
        $this->recommendation = preg_match('/\b(recomienda\w*|recomiende\w*|recomendar\w*|que (?:me )?recomiendas?|sugiere\w*|sugerencias?|que plantas?|cuales plantas?|busco|(?:quiero|necesit[oa])\s+(?:(?:\d+|una?|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez)\s+)?(?:plantas?|macetas?|cactus|suculentas?|arbol(?:es)?|bonsais?|accesorios?)|(?:quiero|necesito|busco) algo verde)\b/u', $text) === 1 || $this->romanticGift;
        $this->catalogIntent = preg_match('/\b(precios?|cuest[ae]\w*|costaria\w*|a cuanto|cuanto|stock|hay|tienen?|queda[n]?|disponib\w*|venden?|vender|vale[n]?|comprar|necesito|quiero|busco|unidades?)\b/u', $text) === 1;
        $this->unsupportedCurrency = preg_match('/\b(euros?|eur|bolivares?|ves|bs|pesos?|cop|mxn|ars|brl|reales|btc|usdt|moneda local)\b|\b\d+\s+soles\b|€|£/u', $folded) === 1;
        $this->budgetMentioned = preg_match('/\b(?:presupuesto|limite de gasto|sin presupuesto|no tengo presupuesto|presupuesto ilimitado|sin limite(?: de gasto| de presupuesto)?|gratis|sin costo|sin coste|no puedo pagar|no tengo dinero)\b/u', $folded) === 1
            || preg_match('/(?:'.self::USD.'\s*'.self::AMOUNT.'|'.self::AMOUNT.'\s*'.self::USD.')/u', $folded) === 1;
        $unlimitedBudget = preg_match('/'.self::NO_BUDGET.'/u', $folded) === 1;
        $this->budgetMentioned = $this->budgetMentioned || $unlimitedBudget;
        $measureUnit = '(?:\s*(?:cm|centimetros)|\s+de diametro)\b';
        $rangePattern = '/\b(?:entre|de)\s+(\d{1,3})(?:'.$measureUnit.')?\s+(?:y|a)\s+(\d{1,3})'.$measureUnit.'/u';
        $maxMeasurePattern = '/\b(?:hasta|maximo(?: de)?|como mucho|no mas de)\s+(\d{1,3})'.$measureUnit.'/u';
        $this->maxSizeCm = null;
        $budgetText = $folded;
        if (preg_match($rangePattern, $folded, $measure)) {
            $this->minSizeCm = min((int)$measure[1], (int)$measure[2]);
            $this->maxSizeCm = max((int)$measure[1], (int)$measure[2]);
            $budgetText = preg_replace($rangePattern, ' ', $folded) ?? $folded;
        } elseif (preg_match($maxMeasurePattern, $folded, $measure)) {
            $this->maxSizeCm = (int)$measure[1];
            $budgetText = preg_replace($maxMeasurePattern, ' ', $folded) ?? $folded;
        }
        $explicitBudgetCap = preg_match('/\b(?:hasta|maximo(?: de)?|menos de|por debajo de|no (?:cueste(?:n)? )?mas de|tope(?: total)?(?: de)?|presupuesto(?: total)?(?: (?:es|de))?)\s+(?:'.self::USD.'\s*)?\d/u', $budgetText) === 1;
        $this->conflictingBudget = $unlimitedBudget && $explicitBudgetCap;
        $this->budget = $this->parseBudget($budgetText);
        $this->budgetMentioned = $this->budgetMentioned || $this->budget !== null;
        $this->standaloneNumber = preg_match('/^\s*[1-9]\d{0,2}\s*$/D', $folded) ? (int)$folded : null;
        $this->catalogCategory = $this->parseCatalogCategory($this->productText);
        $this->potPairing = $this->catalogCategory === 'macetas'
            && preg_match('/\b(?:macetas?|recipientes?)\b.*\b(?:para|con|le qued[ae]|le sirve|le va)\b/u', $this->productText) === 1;
        $invalidCount = '(?:-\s*\d+(?:[.,]\d+)?|\d+[.,]\d+)';
        $this->invalidQuantity = preg_match('/(?:\b(?:hay|tienen?|tienes|quedan?|necesito|quiero|busco|comprar)\s+'.$invalidCount.'(?=\s+[a-z])(?!\s+(?:cm|mm|m|usd|dolares?|euros?|bolivares?)\b)|(?<![\d.,-])'.$invalidCount.'\s+(?:unidades|ejemplares|piezas)\b)/u', $folded) === 1;
        $this->quantity = $this->parseQuantity($text);
        $this->sizeCm = $this->maxSizeCm === null && preg_match('/(?<![\d.,-])\b(\d{1,3})(?:\s*(?:cm|centimetros)\b|\s+de diametro\b)/u', $folded, $size) ? (int)$size[1] : null;
        $this->containerSizeCm = null;
        if ($this->catalogCategory === 'plantas'
            && preg_match('/\bpara\s+(?:(?:una|la|mi|esta)\s+)?maceta\s+de\s+(\d{1,3})\s*(?:cm|centimetros)\b/u', $text, $container)) {
            $this->containerSizeCm = (int)$container[1];
            if ($this->sizeCm === $this->containerSizeCm) $this->sizeCm = null;
        }
        $totalBudget = preg_match('/\b(?:en total|por todo|por ambas?|por los dos|por las dos|junt[oa]s?|entre los dos|(?:presupuesto|gasto|importe|costo|precio) total|total(?: de la compra| de todo)?)\b/u', $text) === 1;
        $perUnit = preg_match('/\b(?:por unidad|por cada (?:una?|planta|maceta|producto)|cada (?:una?|planta|maceta|producto)|precio unitario)\b/u', $text) === 1;
        $this->totalBudgetPreference = $totalBudget ? true : ($perUnit ? false : null);
        $this->totalBudget = $this->totalBudgetPreference === true;
        $this->lowPrice = preg_match('/\b(?:barat[oa]s?|economi[ct][oa]s?|mas economi[ct][oa]s?|menor precio|precio mas bajo)\b/u', $text) === 1;
        $this->selectedOption = $this->parseSelectedOption($text);
        [$this->light, $this->noDirect, $this->conflictingLight, $this->lightMentioned] = $this->parseLight($text);
        $this->lightPreferenceCleared = preg_match('/\b(?:me da igual|no (?:me )?importa|sin preferencia (?:por|de)|sin filtro de)\s+(?:(?:la|el tipo de)\s+)?(?:luz|iluminacion)\b/u', $text) === 1;
        if ($this->lightPreferenceCleared) {
            $this->light = null;
            $this->noDirect = false;
            $this->conflictingLight = false;
            $this->lightMentioned = true;
        }
        $this->lightMentioned = $this->lightMentioned || $this->noLight;
        $this->rooms = [];
        $mentionsPlant = preg_match('/\b(plantas?|cactus|suculentas?|arbol(?:es)?)\b/u', $this->productText) === 1;
        $roomOnly = preg_match('/^(?:(?:mejor|ahora)\s+)?(?:(?:para|en)\s+)?(?:(?:mi|el|la|un|una)\s+)?(?:cuarto|dormitorio|habitacion|recamara|sala|salon|living)(?: de estar)?$/u', $text) === 1;
        if ($mentionsPlant || $this->recommendation || $this->lightMentioned || $roomOnly) {
            $this->rooms = $this->parseRooms($text);
        }
        $this->lightQuestion = preg_match('/\b(?:que|cual)\s+(?:tipo de\s+)?luz(?:\s+(?:necesita|requiere|prefiere|recibe|le conviene))?\b|\b(?:luz|iluminacion)\s+(?:necesita|requiere|prefiere|recibe)\b/u', $text) === 1;
        $compact = '(?:compact[oa]s?|pequen(?:[oa]|it[oa])s?|mini)';
        $clearCompact = preg_match('/\b(?:no (?:hace falta|tiene que ser|es necesario)|no (?:me )?importa|me da igual)\s+(?:que sea\s+|si (?:es|sea)\s+)?(?:'.$compact.'|(?:el )?tamano)\b/u', $text) === 1;
        $wantCompact = preg_match('/\b(?:poco espacio|espacios? pequenos?|ocup[ea] poco|compact[oa]s?)\b|\b(?:mas|algo|planta|opcion|sea|y|tamano)\s+'.$compact.'\b|^'.$compact.'$/u', $text) === 1;
        $this->compactSpaceMentioned = $clearCompact || $wantCompact;
        $this->compactSpace = $wantCompact && !$clearCompact;
        $unusual = '(?:exotic[oa]s?|rar[oa]s?|poco comunes?|llamativ[oa]s?)';
        $this->unusualAppearance = preg_match('/\b(?:me da igual|no (?:me )?importa|no (?:tiene que ser|hace falta))\s+(?:(?:si |que )sea\s+)?'.$unusual.'\b/u', $text)
            ? false : (preg_match('/\b(?:plantas?|algo|opcion)\b.{0,24}\b'.$unusual.'\b|^'.$unusual.'$/u', $text) ? true : null);
        $this->skipPreferences = preg_match('/^(?:no (?:lo )?se(?: aun)?|me da igual|lo que (?:tengas|haya)|como sea|cualquiera|muestrame (?:tod[oa]s?|lo que (?:haya|tengas))|solo quiero ver opciones)$|\b(?:no (?:me )?hagas (?:mas )?preguntas|sin (?:mas )?preguntas)\b/u', $text) === 1;
        $this->restartSearch = preg_match('/\b(?:nueva (?:consulta|busqueda)|cambiemos de tema|empecemos de nuevo)\b/u', $text) === 1;
        $this->moreOptions = preg_match('/^(?:(?:y|tambien)\s+)?(?:(?:(?:hay|tienes|tienen|dame|muestrame|muestra|quiero|puedes mostrar(?:me)?|ver)\s+)?(?:mas opciones|otras opciones|otras alternativas|alguna (?:mas|alternativa|otra(?: opcion| alternativa)?)|algun otro(?: producto)?)|que mas (?:hay|tienes|tienen)|que otras opciones hay|y que mas)(?:\s+por favor)?$/u', $text) === 1;
        [$this->humidity, $this->humidityMentioned, $this->conflictingHumidity] = $this->parseHumidity($text);
        [$this->ventilation, $this->ventilationMentioned, $this->conflictingVentilation] = $this->parseVentilation($text);
    }

    public function hasRecommendationCriteria(): bool
    {
        return $this->light !== null || $this->noDirect || $this->carePreference !== null || $this->budget !== null
            || $this->unusualAppearance === true || $this->romanticGift;
    }

    public function hasEnvironmentCriteria(): bool
    {
        return $this->humidityMentioned || $this->ventilationMentioned;
    }

    public function hasRecommendationContext(): bool
    {
        return $this->hasRecommendationCriteria() || $this->hasEnvironmentCriteria()
            || $this->budgetMentioned || $this->totalBudgetPreference !== null || ($this->quantity ?? 0) > 1 || $this->sizeCm !== null
            || $this->maxSizeCm !== null || $this->containerSizeCm !== null
            || $this->easyMentioned || $this->lightMentioned || $this->compactSpaceMentioned || $this->lowPrice || $this->skipPreferences
            || $this->temperatureMentioned || $this->petSafety || $this->childSafety || $this->standaloneNumber !== null
            || $this->moreOptions || $this->rooms !== [] || $this->unusualAppearance !== null
            || $this->botanicalOriginRegion !== null || $this->romanticGift;
    }

    public function mergeRecommendationPreferencesFrom(self $other): void
    {
        if ($other->lightMentioned) {
            $this->light = $other->light;
            $this->noDirect = $other->noDirect;
            $this->noLight = $other->noLight || ($other->lightPreferenceCleared && $this->noLight);
            $this->conflictingLight = $other->conflictingLight;
            $this->lightMentioned = true;
            $this->lightPreferenceCleared = $other->lightPreferenceCleared;
        }
        $this->rooms = $other->updateRoomConditions($this->rooms);
        if ($other->easyMentioned) {
            $this->easy = $other->easy;
            $this->carePreference = $other->carePreference;
            $this->easyMentioned = true;
        }
        if ($other->budgetMentioned && !$other->unsupportedCurrency
            && !$other->conflictingBudget && $other->budget !== []) {
            $this->budget = $other->budget;
            $this->budgetMentioned = true;
        }
        if ($other->totalBudgetPreference !== null) {
            $this->totalBudgetPreference = $other->totalBudgetPreference;
            $this->totalBudget = $other->totalBudget;
        }
        $this->catalogCategory = $other->catalogCategory ?? $this->catalogCategory;
        $this->quantity = $other->quantity ?? $this->quantity;
        if ($other->sizeCm !== null || $other->maxSizeCm !== null) {
            $this->sizeCm = $other->sizeCm;
            $this->minSizeCm = $other->minSizeCm;
            $this->maxSizeCm = $other->maxSizeCm;
        }
        $this->containerSizeCm = $other->containerSizeCm ?? $this->containerSizeCm;
        $this->potCompanion = $other->potCompanion ?? $this->potCompanion;
        $this->unusualAppearance = $other->unusualAppearance ?? $this->unusualAppearance;
        $this->excludedText = trim($this->excludedText.' '.$other->excludedText);
        if ($other->compactSpaceMentioned) {
            $this->compactSpace = $other->compactSpace;
            $this->compactSpaceMentioned = true;
        }
        $this->lowPrice = $this->lowPrice || $other->lowPrice;
        $this->temperatureMentioned = $this->temperatureMentioned || $other->temperatureMentioned;
        $this->unsupportedClimateRequirement = $this->unsupportedClimateRequirement || $other->unsupportedClimateRequirement;
        $this->botanicalOriginRegion = $other->botanicalOriginRegion ?? $this->botanicalOriginRegion;
        $this->petSafety = $this->petSafety || $other->petSafety;
        $this->childSafety = $this->childSafety || $other->childSafety;
        if ($other->humidityMentioned) {
            $this->humidity = $other->humidity;
            $this->humidityMentioned = true;
            $this->conflictingHumidity = $other->conflictingHumidity;
        }
        if ($other->ventilationMentioned) {
            $this->ventilation = $other->ventilation;
            $this->ventilationMentioned = true;
            $this->conflictingVentilation = $other->conflictingVentilation;
        }
    }

    public function inheritRecommendationPreferences(self $context): void
    {
        $this->rooms = $this->updateRoomConditions($context->rooms);
        if (!$this->lightMentioned && $context->lightMentioned) {
            $this->light = $context->light;
            $this->noDirect = $context->noDirect;
            $this->noLight = $context->noLight;
            $this->conflictingLight = $context->conflictingLight;
            $this->lightMentioned = true;
            $this->lightPreferenceCleared = $context->lightPreferenceCleared;
        }
        if ($this->lightPreferenceCleared && $context->noLight) $this->noLight = true;
        if (!$this->easyMentioned && $context->easyMentioned) {
            $this->easy = $context->easy;
            $this->carePreference = $context->carePreference;
            $this->easyMentioned = true;
        }
        if (!$this->budgetMentioned && $context->budgetMentioned) {
            $this->budget = $context->budget;
            $this->budgetMentioned = true;
        }
        if ($this->totalBudgetPreference === null) {
            $this->totalBudgetPreference = $context->totalBudgetPreference;
            $this->totalBudget = $context->totalBudget;
        }
        $this->catalogCategory ??= $context->catalogCategory;
        $this->quantity ??= $context->quantity;
        if ($this->sizeCm === null && $this->maxSizeCm === null) {
            $this->sizeCm = $context->sizeCm;
            $this->minSizeCm = $context->minSizeCm;
            $this->maxSizeCm = $context->maxSizeCm;
        }
        $this->containerSizeCm ??= $context->containerSizeCm;
        $this->potCompanion ??= $context->potCompanion;
        $this->unusualAppearance ??= $context->unusualAppearance;
        $this->excludedText = trim($context->excludedText.' '.$this->excludedText);
        if (!$this->compactSpaceMentioned && $context->compactSpaceMentioned) {
            $this->compactSpace = $context->compactSpace;
            $this->compactSpaceMentioned = true;
        }
        $this->lowPrice = $this->lowPrice || $context->lowPrice;
        $this->temperatureMentioned = $this->temperatureMentioned || $context->temperatureMentioned;
        $this->unsupportedClimateRequirement = $this->unsupportedClimateRequirement || $context->unsupportedClimateRequirement;
        $this->botanicalOriginRegion ??= $context->botanicalOriginRegion;
        $this->petSafety = $this->petSafety || $context->petSafety;
        $this->childSafety = $this->childSafety || $context->childSafety;
        if (!$this->humidityMentioned && $context->humidityMentioned) {
            $this->humidity = $context->humidity;
            $this->humidityMentioned = true;
            $this->conflictingHumidity = $context->conflictingHumidity;
        }
        if (!$this->ventilationMentioned && $context->ventilationMentioned) {
            $this->ventilation = $context->ventilation;
            $this->ventilationMentioned = true;
            $this->conflictingVentilation = $context->conflictingVentilation;
        }
        $this->recommendationContextActive = true;
    }

    public function followsKnownProduct(): bool
    {
        return preg_match(
            '/^(?:y\s+)?(?:(?:cuant[oa]s?\s+)?(?:cuesta|cuestan|costaria|costarian|vale|valen|queda|quedan|hay|tienen|tienes)|cuant[oa]s?\s+me\s+(?:cuesta|cuestan|costaria|costarian)|cuant[oa]s?|(?:como\s+)?(?:la cuido|lo cuido|cuidarla|cuidarlo|la riego|lo riego|regarla|regarlo)|(?:su |el )?(?:precio|stock|disponibilidad)|(?:tendencia|demanda|ventas)|como (?:llegaron|calcularon) a ese numero)(?:\s+(?:de esa(?: planta)?|de ese(?: producto)?|de ella|de el|disponibles?|en stock|en existencia|en total|ahora|por favor|de stock|de luz|de demanda|por unidad|[0-9]+|unidades?|estimada|mensual|en usd))*$/u',
            $this->text
        ) === 1;
    }

    public static function normalize(string $value): string
    {
        $value = preg_replace('/[^a-z0-9\-\s]/u', ' ', self::fold($value)) ?? '';
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private static function fold(string $value): string
    {
        $value = strtr($value, [
            'Á'=>'á','É'=>'é','Í'=>'í','Ó'=>'ó','Ú'=>'ú','Ü'=>'ü','Ñ'=>'ñ',
            '‐'=>'-','‑'=>'-','‒'=>'-','–'=>'-','—'=>'-',
            "\u{00A0}"=>' ',"\u{202F}"=>' ',
        ]);
        $value = strtr(lowercase($value), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        $value = preg_replace('/\b(?:i need|need|i want|want|looking for|find me)\s+(?:a|an)\s+(?:low light)\s+plant\b/u', 'quiero una planta para poca luz', $value) ?? $value;
        $value = preg_replace('/\b(?:i need|need|i want|want|looking for|find me)\s+(?:a|an)\s+plant\s+for\s+low light\b/u', 'quiero una planta para poca luz', $value) ?? $value;
        $value = strtr($value, [
            'meceta'=>'maceta', 'mecetas'=>'macetas',
            'cannavis'=>'cannabis', 'canabis'=>'cannabis', 'mariguana'=>'marihuana',
            'imrrompible'=>'irrompible', 'inrrompible'=>'irrompible',
            'ilumoinacion'=>'iluminacion', 'copstaria'=>'costaria',
            'kiero'=>'quiero', 'poko'=>'poco', 'plantita'=>'planta',
            'baratita'=>'barata', 'baratito'=>'barato',
            'low light'=>'poca luz', 'under'=>'menos de', 'dollars'=>'usd', 'dollar'=>'usd',
            'humidity'=>'humedad', 'moisture'=>'humedad', 'mold'=>'moho', 'mould'=>'moho',
        ]);
        $value = preg_replace('/\bplant\b/u', 'planta', $value) ?? $value;
        $value = preg_replace('/\basthma\b/u', 'asma', $value) ?? $value;
        $value = preg_replace('/\bcures?\b/u', 'cura', $value) ?? $value;
        $value = preg_replace('/\bpropera\b/u', 'prospera', $value) ?? $value;
        $value = preg_replace('/\bene l\b/u', 'en el', $value) ?? $value;
        $value = preg_replace('/\bpa\b/u', 'para', $value) ?? $value;
        return preg_replace('/\p{Mn}+/u', '', $value) ?? $value;
    }

    private function lacksNaturalLight(string $text): bool
    {
        return preg_match('/\b(?:sin ventanas?|no tiene ventanas?|sin (?:nada de )?luz(?! (?:(?:natural|solar) )?(?:directa|indirecta))|(?<!casi )no (?:entra|tiene|recibe) (?:nada de )?luz(?! (?:(?:natural|solar) )?(?:directa|indirecta)))\b/u', $text) === 1;
    }

    private function parseRooms(string $text): array
    {
        $roomNames = '\b(cuarto|dormitorio|habitacion|recamara|sala|salon|living)\b(?!\s+(?:producto|opcion|alternativa|articulo|puesto)\b)';
        $rooms = [];
        $parts = preg_split('/\s+(?:y|mientras que)\s+(?=.{0,55}'.$roomNames.')/u', $text) ?: [$text];
        foreach ($parts as $part) {
            preg_match_all('/'.$roomNames.'/u', $part, $matches, PREG_OFFSET_CAPTURE);
            $rooms = array_replace($rooms, $this->readRoomConditions($part, $matches[1]));
        }
        return $rooms;
    }

    private function readRoomConditions(string $text, array $matches): array
    {
        $rooms = [];
        foreach ($matches as $index => [$name, $offset]) {
            $room = in_array($name, ['sala', 'salon', 'living'], true) ? 'sala' : 'cuarto';
            $end = $matches[$index + 1][1] ?? strlen($text);
            $scope = count($matches) === 1 ? $text : substr($text, $offset, $end - $offset);
            [$light, $noDirect, $conflict, $mentioned] = $this->parseLight($scope);
            $noLight = $this->lacksNaturalLight($scope);
            $rooms[$room] = [
                'light'=>$light, 'noDirect'=>$noDirect, 'conflict'=>$conflict,
                'mentioned'=>$mentioned || $noLight, 'noLight'=>$noLight,
            ];
        }
        return $rooms;
    }

    private function updateRoomConditions(array $previous): array
    {
        $current = $this->rooms;
        if ($this->lightPreferenceCleared) {
            foreach ($previous as &$conditions) {
                if ($conditions['noLight']) continue;
                $conditions = ['light'=>null, 'noDirect'=>false, 'conflict'=>false, 'mentioned'=>false, 'noLight'=>false];
            }
            unset($conditions);
            return $current !== [] ? $current : $previous;
        }
        if ($current === []) {
            $appliesToAll = count($previous) === 1 || preg_match('/\b(ambos|ambas|los dos|las dos)\b/u', $this->text) === 1;
            if ($this->lightMentioned && $appliesToAll) {
                return array_fill_keys(array_keys($previous), [
                    'light'=>$this->light, 'noDirect'=>$this->noDirect, 'conflict'=>$this->conflictingLight,
                    'mentioned'=>true, 'noLight'=>$this->noLight,
                ]);
            }
            return $previous;
        }
        foreach ($current as $room => $conditions) {
            if (!$conditions['mentioned'] && isset($previous[$room])) $current[$room] = $previous[$room];
        }
        if (!$this->recommendation && count($previous) > 1 && array_diff_key($current, $previous) === []) {
            return array_replace($previous, $current);
        }
        return $current;
    }

    private function parseLight(string $text): array
    {
        $negativePrefix = '(?:'.self::EXCLUSION.'|no (?:recib[ea]|tien[ea]|toler[ae]|aguant[ea]|soport[ea]|necesit[ea]|requier[ea]|(?:est[ae]|estara) expuest[oa]|(?:debe|puede|pueda) recibir|le (?:de|da))|evitando|protegida de|lejos de)';
        $modifiers = '(?:(?:una|un|ninguna|la|el|que|le|de|del|a|acceso|para|con|reciba|tenga|necesite|requiera|aguante|soporte|plantas?|espacio|cuarto|habitacion)\s+){0,8}';
        $negativeLead = '\b'.$negativePrefix.'\s+'.$modifiers;
        $noDirectPattern = '/(?:'.$negativeLead.self::DIRECT_LIGHT.'\b|\b'.self::DIRECT_LIGHT.'\s+no\b)/u';
        $noDirect = preg_match($noDirectPattern, $text) === 1;
        $positive = preg_replace($noDirectPattern, ' ', $text) ?? $text;
        $negated = [];
        foreach (['low'=>self::LOW_LIGHT, 'medium'=>self::MEDIUM_LIGHT, 'bright'=>self::BRIGHT_LIGHT] as $mode => $pattern) {
            $positive = preg_replace('/'.$negativeLead.$pattern.'\b/u', ' ', $positive, -1, $count) ?? $positive;
            if ($count > 0) $negated[] = $mode;
        }

        $low = preg_match('/\b'.self::LOW_LIGHT.'\b/u', $positive) === 1;
        $medium = preg_match('/\b'.self::MEDIUM_LIGHT.'\b/u', $positive) === 1;
        $direct = preg_match('/\b(sol directo|luz (?:solar )?directa|pleno sol|sol (?:todo el dia|de lleno)|le da el sol|me da el sol)\b/u', $positive) === 1;
        $bright = !$medium && !$direct && preg_match('/\b'.self::BRIGHT_LIGHT.'\b/u', $positive) === 1;
        $modes = array_keys(array_filter(['low'=>$low, 'medium'=>$medium, 'direct'=>$direct, 'bright'=>$bright && !$direct]));
        $locationSuggestsBright = preg_match('/\b(patio|jardin|jardines|terraza|balcon)\b/u', $text) === 1
            && preg_match('/\b(?:cubiert[oa]s?|techad[oa]s?)\b/u', $text) !== 1;
        if ($modes === [] && $locationSuggestsBright) $modes = ['bright'];
        $mixed = count($modes) > 1 || ($noDirect && $direct)
            || ($negated !== [] && ($modes === [] || array_intersect($modes, $negated) !== []));
        $mentioned = $noDirect || $locationSuggestsBright
            || preg_match('/\b'.self::DIRECT_LIGHT.'\b|\b'.self::LOW_LIGHT.'\b|\b'.self::MEDIUM_LIGHT.'\b|\b'.self::BRIGHT_LIGHT.'\b/u', $text) === 1;
        return [$modes[0] ?? null, $noDirect, $mixed, $mentioned];
    }

    private function parseHumidity(string $text): array
    {
        $high = preg_match('/\b(?:mucha|alta|elevada|bastante)\s+humed(?:ad|a|o)\b|\b(?:ambiente|sala|habitacion|cuarto|lugar|espacio|casa)\s+(?:\w+\s+y\s+)?humed[oa]\b/u', $text) === 1;
        $low = preg_match('/\b(?:poca|baja|escasa)\s+humedad\b|\bambiente\s+sec[oa]s?\b/u', $text) === 1;
        $mentioned = $high || $low;
        return [$high === $low ? null : ($high ? 'high' : 'low'), $mentioned, $high && $low];
    }

    private function parseCatalogCategory(string $text): ?string
    {
        $fruitTree = preg_match('/\barbol(?:es)?\s+frutal(?:es)?\b/u', $text) === 1;
        if (preg_match('/\barbol(?:es)?\b.*?\bpara\s+(?:(?:una|la|mi|esta)\s+)?macetas?\b/u', $text) === 1) return $fruitTree ? 'arboles-frutales' : 'arboles';
        if (preg_match('/\bplantas?\b.*?\bpara\s+(?:(?:una|la|mi|esta)\s+)?macetas?\b/u', $text) === 1) return 'plantas';
        if (preg_match('/\b(macetas?|recipientes?)\b/u', $text) === 1) return 'macetas';
        if (preg_match('/\b(regaderas?|accesorios?)\b/u', $text) === 1) return 'accesorios';
        if ($fruitTree) return 'arboles-frutales';
        if (preg_match('/\bbonsais?\b/u', $text) === 1) return 'bonsais';
        if (preg_match('/\barbol(?:es)?\b/u', $text) === 1) return 'arboles';
        if (preg_match('/\bplantas?\b.{0,45}\b(?:con flores?|florales?|floridas?)\b/u', $text) === 1) return 'plantas-con-flores';
        if (preg_match('/\bflor(?:es)?\b/u', $text) === 1) return 'flores';
        if (preg_match('/\b(cactus|cact[uo]s)\b/u', $text) === 1) return 'cactus';
        if (preg_match('/\b(suculentas?)\b/u', $text) === 1) return 'suculentas';
        if (preg_match('/\b(plantas?|plantita|flores?)\b/u', $text) === 1) return 'plantas';
        return null;
    }

    private function parseVentilation(string $text): array
    {
        $poor = preg_match('/\b(?:poca|mala|escasa|sin)\s+ventilacion\b|\b(?:mal|poco)\s+ventilad[oa]s?\b|\b(?:casa|cuarto|habitacion|ambiente)\s+cerrad[oa]s?\b/u', $text) === 1;
        $good = preg_match('/\b(?:buena|mucha)\s+ventilacion\b|\bbien\s+ventilad[oa]s?\b|\b(?:buen|buena)\s+(?:flujo|circulacion)\s+de\s+aire\b/u', $text) === 1;
        $mentioned = $poor || $good;
        return [$poor === $good ? null : ($poor ? 'poor' : 'good'), $mentioned, $poor && $good];
    }

    /** null: no budget. Empty array: a monetary condition could not be read. */
    private function parseBudget(string $message): ?array
    {
        if (preg_match('/'.self::NO_BUDGET.'/u', $message) === 1) return null;
        if (preg_match('/\b(?:gratis|sin costo|sin coste|no puedo pagar|no tengo dinero)\b/u', $message) === 1) return ['min'=>0, 'max'=>0];
        if (preg_match('/^[\s¿¡]*(?:'.self::USD.'\s*'.self::AMOUNT.'|'.self::AMOUNT.'\s*'.self::USD.')\s*[.!?]?\s*$/uD', $message, $amount)) {
            return ['max'=>$this->cents($amount[1] !== '' ? $amount[1] : $amount[2])];
        }
        $money = '(?:'.self::USD.'\s*)?'.self::AMOUNT.'(?:\s*'.self::USD.')?';
        if (preg_match('/\bentre\s+'.$money.'\s+(?:y|a)\s+'.$money.'/u', $message, $match)) {
            $min = $this->cents($match[1]);
            $max = $this->cents($match[2]);
            if ($min > $max) {
                $this->invalidBudgetRange = true;
                return [];
            }
            return ['min'=>$min, 'max'=>$max];
        }
        $atMost = '(?:hasta|maximo(?: de)?|no (?:cueste(?:n)? )?mas de|no (?:pase|pasen) de|como mucho|presupuesto(?: total)?(?: (?:es|de))?|tope(?: total)?(?: de)?)';
        $less = '(?:menos de|por debajo de|menor(?:es)? (?:de|a))';
        $more = '(?:mas de|mayor(?:es)? (?:de|a)|por encima de)';
        $atLeast = '(?:por lo menos|al menos|minimo(?: de)?|a partir de)';
        // A quantity constraint without a currency belongs to the stock question.
        $quantityOnly = preg_match('/\b(?:hay|tienen?|tienes|quedan?|necesito|quiero comprar)\s+(?:por lo menos|al menos|(?:mas|menos) de)\s+\d+/u', $message)
            && preg_match('/'.self::USD.'/u', $message) !== 1;
        if ($quantityOnly) return null;
        $unreadableExclusion = '/\bno (?:quiero|busco|recomiendes)[^,;.!?]{0,65}\b(?:cuest[ae]n?|pasen?|superen?)\s+(?:mas|menos) de\b/u';
        if (preg_match($unreadableExclusion, $message)) return [];
        $operators = [
            ['pattern'=>'\b'.$atMost.'\s+|<=\s*|≤\s*', 'bound'=>'max', 'offset'=>0],
            ['pattern'=>'\b'.$less.'\s+|<\s*', 'bound'=>'max', 'offset'=>-1],
            ['pattern'=>'\b'.$atLeast.'\s+|>=\s*|≥\s*', 'bound'=>'min', 'offset'=>0],
            ['pattern'=>'(?<!no )(?<!no cueste )(?<!no cuesten )\b'.$more.'\s+|>\s*', 'bound'=>'min', 'offset'=>1],
            ['pattern'=>'\b(?:tengo|puedo gastar|puedo pagar)\s+(?='.self::USD.'\s*|\d+(?:[.,]\d{1,2})?\s*'.self::USD.')', 'bound'=>'max', 'offset'=>0],
        ];
        $bounds = [];
        foreach ($operators as $operator) {
            preg_match_all('/(?:'.$operator['pattern'].')'.$money.'/u', $message, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $bound = $this->cents($match[1]) + $operator['offset'];
                $key = $operator['bound'];
                $bounds[$key] = !isset($bounds[$key]) ? $bound : ($key === 'max' ? min($bound, $bounds[$key]) : max($bound, $bounds[$key]));
            }
        }
        if ($bounds !== []) return ($bounds['min'] ?? 0) <= ($bounds['max'] ?? PHP_INT_MAX) ? $bounds : [];
        if (preg_match('/\b(presupuesto|'. $atMost .'|'. $less .'|'. $more .')\b/u', $message)
            || (preg_match('/(?:'.self::USD.'\s*'.self::AMOUNT.'|'.self::AMOUNT.'\s*'.self::USD.')/u', $message))) {
            return [];
        }
        return null;
    }

    private function cents(string $amount): int
    {
        $parts = explode('.', str_replace(',', '.', $amount), 2);
        return (int)$parts[0] * 100 + (int)str_pad($parts[1] ?? '', 2, '0');
    }

    private function parseQuantity(string $text): ?int
    {
        if (preg_match('/\b(?:hay|tienen?|tienes|quedan?|necesito|quiero|busco|comprar)\s+(?:(al menos|por lo menos|mas de)\s+)?(\d{1,8}|una?|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez)(?!\d|\s+(?:cm|mm|m|usd|dolares?|euros?|bolivares?)\b)/u', $text, $match)) {
            $numberWords = ['uno'=>1, 'un'=>1, 'una'=>1, 'dos'=>2, 'tres'=>3, 'cuatro'=>4, 'cinco'=>5, 'seis'=>6, 'siete'=>7, 'ocho'=>8, 'nueve'=>9, 'diez'=>10];
            $number = ctype_digit($match[2]) ? (int)$match[2] : ($numberWords[$match[2]] ?? 1);
            return $number + ($match[1] === 'mas de' ? 1 : 0);
        }
        if (preg_match('/\b(\d{1,8})\s+(?:unidades|ejemplares|piezas)\b/u', $text, $match)) {
            return (int)$match[1];
        }
        return null;
    }

    private function parseSelectedOption(string $text): ?int
    {
        $numbers = [
            'primera'=>1, 'primero'=>1, 'primer'=>1, 'segunda'=>2, 'segundo'=>2,
            'tercera'=>3, 'tercero'=>3, 'cuarta'=>4, 'cuarto'=>4, 'quinta'=>5, 'quinto'=>5,
            'ultima'=>self::LAST_OPTION, 'ultimo'=>self::LAST_OPTION,
        ];
        if (preg_match('/\b(?:opcion|alternativa|producto)\s+(\d+)\b/u', $text, $match)) return (int)$match[1];
        $ordinal = '('.implode('|', array_keys($numbers)).')';
        if (preg_match('/\b'.$ordinal.'\s+(?:opcion|alternativa|producto|articulo)\b/u', $text, $match)
            || preg_match('/^(?:(?:y|elijo|prefiero|quiero|me quedo con|cuanto cuesta|(?:dime )?el precio de)\s+)*(?:la\s+|el\s+)?'.$ordinal.'(?:$|\s+(?:por favor|cuanto|que|tiene|cuesta)\b)/u', $text, $match)) {
            return $numbers[$match[1]];
        }
        return null;
    }
}
