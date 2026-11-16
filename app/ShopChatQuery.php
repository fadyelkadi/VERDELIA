<?php
declare(strict_types=1);

final class ShopChatQuery
{
    private const DIRECT_LIGHT = '(?:sol directo|luz (?:solar )?directa|luz directa del sol|sol(?: natural)?(?: de lleno)?)';
    private const LOW_LIGHT = '(?:poca luz|poca iluminacion|poco luminosa|luz (?:baja|tenue|suave)|baja luminosidad|casi no (?:entra|recibe|tiene) (?:luz|sol)|(?:cuarto|habitacion|espacio) oscur[oa])';
    private const MEDIUM_LIGHT = '(?:luz indirecta|indirecta|luz media|luz moderada|media luminosidad)';
    private const BRIGHT_LIGHT = '(?:luz brillante|brillante|muy luminos[oa]|mucha luz)';
    private const EXCLUSION = 'no (?:quiero|busco|me interesa|me recomiendes)|sin|evita(?:r)?|excepto';
    private const AMOUNT = '([0-9]{1,6}(?:[.,][0-9]{1,2})?)(?![0-9]|[.,][0-9])';
    private const USD = '(?:usd|dolares?(?: estadounidenses)?|\x24)';

    public string $text;
    public string $productText;
    public string $excludedText;
    public bool $petSafety;
    public bool $noLight;
    public bool $noDirect;
    public ?string $light;
    public bool $lightMentioned;
    public bool $conflictingLight;
    public bool $easy;
    public bool $easyMentioned;
    public bool $care;
    public bool $watering;
    public bool $trend;
    public bool $unsupportedHorizon;
    public bool $nextCalendarMonth;
    public ?array $budget;
    public bool $unsupportedCurrency;
    public ?int $quantity;
    public bool $catalogIntent;
    public bool $recommendation;
    public ?string $humidity;
    public bool $humidityMentioned;
    public bool $conflictingHumidity;
    public ?string $ventilation;
    public bool $ventilationMentioned;
    public bool $conflictingVentilation;
    public bool $recommendationContextActive = false;

    public function __construct(string $message)
    {
        $folded = self::fold($message);
        $this->text = $text = self::normalize($message);
        preg_match_all('/\b(?:no (?:quiero|busco|me interesa|me recomiendes)|excepto)\s+([^,;.!?]+?)(?=\s+(?:sino|pero|dime|muestrame)\b|[,;.!?]|$)/u', $folded, $negations);
        $this->excludedText = self::normalize(implode(' ', $negations[1]));
        $this->productText = self::normalize(str_replace($negations[0], ' ', $folded));

        $this->petSafety = preg_match('/\b(mascotas?|gat[oa]s?|gatit[oa]s?|gatic[oa]s?|michis?|felin[oa]s?|perr[oa]s?|perrit[oa]s?|cachorr[oa]s?|canin[oa]s?|animales?|conejos?|loros?|aves?|(?:pet|cat|dog)[ -]?(?:friendly|safe)|toxic\w*|venenos[oa]s?)\b/u', $text) === 1;
        $this->noLight = preg_match('/\b(?:sin ventanas?|sin (?:nada de )?luz(?! (?:(?:natural|solar) )?(?:directa|indirecta))|(?<!casi )no (?:entra|tiene|recibe) (?:nada de )?luz(?! (?:(?:natural|solar) )?(?:directa|indirecta)))\b/u', $text) === 1;
        $easyNegative = preg_match('/\b(?:no (?:me importa|es necesario|hace falta|tiene que ser)|no importa si (?:es|sea))\s+(?:que sea\s+)?(?:facil|sencill[oa])\b/u', $text) === 1;
        $easyPositive = preg_match('/\b(facil|faciles|sencilla|sencillo|principiantes?|poco cuidado|bajo mantenimiento)\b/u', $text) === 1;
        $this->easyMentioned = $easyNegative || $easyPositive;
        $this->easy = $easyPositive && !$easyNegative;
        $this->watering = preg_match('/\b(riego|regar|riega|regarse|riegue|cuanta agua|frecuencia de riego)\b/u', $text) === 1;
        $this->care = $this->watering || preg_match('/\b(cuidados?|cuidar|cuido|cuida|cuidarla|cuidarlo|mantenimiento)\b/u', $text) === 1;
        $this->trend = preg_match('/\b(ventas?|demanda|tendencia|proyeccion|estimacion|estiman|estimado|prevision|predecir|pronostic\w*|vendera[n]?|venderia[n]?|vendieron)\b/u', $text) === 1;
        $this->unsupportedHorizon = preg_match(
            '/\b(?:\d+|varios?|varias?|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once|doce)\s+(?:meses?|semanas?|dias?|anos?|trimestres?)\b|\b(?:trimestre|semestre|anio|ano|proximos meses|meses que vienen|en (?:el )?20\d{2}|en (?:enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre)|vendieron|ventas historicas|ventas (?:de |del )?(?:hoy|ayer|este mes|mes pasado))\b/u',
            preg_replace('/\b(?:1|un)\s+mes\b/u', ' ', $text) ?? $text
        ) === 1;
        $this->nextCalendarMonth = preg_match('/\b(proximo mes|mes que viene|dentro de (?:un|1) mes|en (?:un|1) mes)\b/u', $text) === 1;
        $this->recommendation = preg_match('/\b(recomienda\w*|recomiende\w*|recomendar\w*|que (?:me )?recomiendas?|sugiere\w*|sugerencias?|que plantas?|cuales plantas?|busco|quiero una planta)\b/u', $text) === 1;
        $this->catalogIntent = preg_match('/\b(precios?|cuest[ae]\w*|a cuanto|stock|hay|tienen?|queda[n]?|disponib\w*|venden?|vale[n]?|comprar|necesito|unidades?)\b/u', $text) === 1;
        $this->unsupportedCurrency = preg_match('/\b(euros?|eur|bolivares?|ves|bs|pesos?|cop|mxn|ars|brl|reales|btc|usdt|moneda local)\b|\b\d+\s+soles\b|€|£/u', $folded) === 1;
        $this->budget = $this->parseBudget($folded);
        $this->quantity = $this->parseQuantity($text);
        [$this->light, $this->noDirect, $this->conflictingLight, $this->lightMentioned] = $this->parseLight($text);
        $this->lightMentioned = $this->lightMentioned || $this->noLight;
        [$this->humidity, $this->humidityMentioned, $this->conflictingHumidity] = $this->parseHumidity($text);
        [$this->ventilation, $this->ventilationMentioned, $this->conflictingVentilation] = $this->parseVentilation($text);
    }

    public function hasRecommendationCriteria(): bool
    {
        return $this->light !== null || $this->noDirect || $this->easy || $this->budget !== null;
    }

    public function hasEnvironmentCriteria(): bool
    {
        return $this->humidityMentioned || $this->ventilationMentioned;
    }

    public function hasRecommendationContext(): bool
    {
        return $this->hasRecommendationCriteria() || $this->hasEnvironmentCriteria();
    }

    public function mergeRecommendationPreferencesFrom(self $other): void
    {
        if ($other->lightMentioned) {
            $this->light = $other->light;
            $this->noDirect = $other->noDirect;
            $this->noLight = $other->noLight;
            $this->conflictingLight = $other->conflictingLight;
            $this->lightMentioned = true;
        }
        if ($other->easyMentioned) {
            $this->easy = $other->easy;
            $this->easyMentioned = true;
        }
        if ($other->budget !== null) $this->budget = $other->budget;
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
        if (!$this->lightMentioned && $context->lightMentioned) {
            $this->light = $context->light;
            $this->noDirect = $context->noDirect;
            $this->noLight = $context->noLight;
            $this->conflictingLight = $context->conflictingLight;
            $this->lightMentioned = true;
        }
        if (!$this->easyMentioned && $context->easyMentioned) {
            $this->easy = $context->easy;
            $this->easyMentioned = true;
        }
        if ($this->budget === null) $this->budget = $context->budget;
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
            '/^(?:y\s+)?(?:(?:cuant[oa]s?\s+)?(?:cuesta|cuestan|vale|valen|queda|quedan|hay|tienen|tienes)|cuant[oa]s?|(?:como\s+)?(?:la cuido|lo cuido|cuidarla|cuidarlo|la riego|lo riego|regarla|regarlo)|(?:su |el )?(?:precio|stock|disponibilidad)|(?:tendencia|demanda|ventas)|como (?:llegaron|calcularon) a ese numero)(?:\s+(?:de esa(?: planta)?|de ese(?: producto)?|de ella|de el|disponibles?|en stock|en existencia|en total|ahora|por favor|de stock|de luz|de demanda|por unidad|[0-9]+|unidades?|estimada|mensual|en usd))*$/u',
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
        return preg_replace('/\p{Mn}+/u', '', $value) ?? $value;
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
        $bright = !$medium && preg_match('/\b'.self::BRIGHT_LIGHT.'\b/u', $positive) === 1;
        $modes = array_keys(array_filter(['low'=>$low, 'medium'=>$medium, 'direct'=>$direct, 'bright'=>$bright && !$direct]));
        $mixed = count($modes) > 1 || ($noDirect && $direct)
            || ($negated !== [] && ($modes === [] || array_intersect($modes, $negated) !== []));
        $mentioned = $noDirect || preg_match('/\b'.self::DIRECT_LIGHT.'\b|\b'.self::LOW_LIGHT.'\b|\b'.self::MEDIUM_LIGHT.'\b|\b'.self::BRIGHT_LIGHT.'\b/u', $text) === 1;
        return [$modes[0] ?? null, $noDirect, $mixed, $mentioned];
    }

    private function parseHumidity(string $text): array
    {
        $high = preg_match('/\b(?:mucha|alta|elevada|bastante)\s+humed(?:ad|a|o)\b|\bambiente\s+humedo\b/u', $text) === 1;
        $low = preg_match('/\b(?:poca|baja|escasa)\s+humedad\b|\bambiente\s+sec[oa]s?\b/u', $text) === 1;
        $mentioned = $high || $low;
        return [$high === $low ? null : ($high ? 'high' : 'low'), $mentioned, $high && $low];
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
        $money = '(?:'.self::USD.'\s*)?'.self::AMOUNT.'(?:\s*'.self::USD.')?';
        if (preg_match('/\bentre\s+'.$money.'\s+(?:y|a)\s+'.$money.'/u', $message, $match)) {
            $min = $this->cents($match[1]);
            $max = $this->cents($match[2]);
            return $min <= $max ? ['min'=>$min, 'max'=>$max] : [];
        }
        $atMost = '(?:hasta|maximo(?: de)?|no (?:cueste(?:n)? )?mas de|no (?:pase|pasen) de|como mucho|presupuesto(?: (?:es|de))?|tope(?: de)?)';
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
        if (preg_match('/\b(presupuesto|barat[oa]s?|economic[oa]s?|asequibles?|'. $atMost .'|'. $less .'|'. $more .')\b/u', $message)
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
        if (preg_match('/\b(?:hay|tienen?|tienes|quedan?|necesito|quiero|busco|comprar)\s+(?:(al menos|por lo menos|mas de)\s+)?(\d{1,8})(?!\d|\s+(?:cm|mm|m|usd|dolares?|euros?|bolivares?)\b)/u', $text, $match)) {
            return (int)$match[2] + ($match[1] === 'mas de' ? 1 : 0);
        }
        if (preg_match('/\b(\d{1,8})\s+(?:unidades|ejemplares|piezas)\b/u', $text, $match)) {
            return (int)$match[1];
        }
        return null;
    }
}
