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
        try {
            $products=$this->catalog->chatProducts();
            $answer=$this->answer($message,$products);
            $this->respond(200, ['ok'=>true] + $answer);
        } catch (Throwable $exception) {
            verdelia_log_error($exception,'shop-chat');
            $this->respond(503, ['ok'=>false,'message'=>'El asistente no está disponible temporalmente. Inténtalo nuevamente.']);
        }
    }

    private function answer(string $message, array $products): array
    {
        $query=$this->normalize($message);
        $isTrend=preg_match('/\b(ventas?|demanda|tendencia|proyeccion|estimacion|estimado|prevision)\b/u',$query)===1;
        $light=$this->requestedLight($query);
        $easy=preg_match('/\b(facil|faciles|sencilla|sencillo|principiante|principiantes|poco cuidado)\b/u',$query)===1;
        $matches=($light!==null || $easy) && !$isTrend ? [] : $this->matchProducts($query,$products);
        if ($isTrend) {
            if (count($matches)!==1) {
                return ['message'=>count($matches)>1 ? '¿De cuál producto quieres ver la tendencia? Escribe su nombre o SKU.' : 'Para consultar una tendencia de demanda, dime el nombre o SKU de un producto.'];
            }
            return $this->demandAnswer($matches[0]);
        }
        if ($matches!==[]) {
            if (count($matches)>1) {
                return ['message'=>'Encontré varias coincidencias. ¿Cuál buscas: '.implode(', ',array_map(static fn(array $p):string=>$p['name'].' ('.$p['sku'].')',$matches)).'?'];
            }
            $product=$matches[0];
            $stock=(int)$product['stock'];
            $message=$product['name'].' ('.$product['sku'].') cuesta '.$this->money((int)$product['price_cents']).' USD. '.($stock>0?'Hay '.$stock.' unidades disponibles.':'Por ahora está agotado.');
            return ['message'=>$message,'products'=>[$this->publicProduct($product)]];
        }

        if ($light!==null || $easy) return $this->recommend($products,$light,$easy);
        return ['message'=>'Puedo ayudarte con precio y disponibilidad por nombre o SKU, recomendar plantas por luz (poca, indirecta/media o brillante/directa), sugerir opciones fáciles o mostrar una tendencia de demanda. Por ejemplo: «¿Hay Monstera?», «una planta para poca luz» o «ventas de PLA-006».'];
    }

    private function matchProducts(string $query, array $products): array
    {
        $nameQuery = $this->productWords($query);
        $skuMatches = [];
        $exactNames = [];
        $partialNames = [];
        foreach ($products as $product) {
            $sku = $this->normalize((string)$product['sku']);
            if ($sku !== '' && str_contains(' '.$query.' ', ' '.$sku.' ')) {
                $skuMatches[] = $product;
            }

            $fullName = $this->normalize((string)$product['name']);
            $name = $this->productWords($fullName);
            if ($query === $fullName || ($name !== '' && $nameQuery === $name)) {
                $exactNames[] = $product;
            } elseif (strlen($nameQuery) >= 3 && $name !== ''
                && (str_contains(' '.$name.' ', ' '.$nameQuery.' ') || str_contains(' '.$nameQuery.' ', ' '.$name.' '))) {
                $partialNames[] = $product;
            }
        }

        return $skuMatches ?: ($exactNames ?: $partialNames);
    }

    private function productWords(string $text): string
    {
        $words = preg_replace('/\b(cuanto cuesta|cuanto|cual|cuales|es|la|los|las|que|plantas?|son|cuidar|facil|faciles|precio|disponibilidad|disponible|stock|hay|tienen|tiene|consulta|consultar|busco|quiero|por favor|ventas?|demanda|tendencia|proyeccion|estimacion|estimado|prevision|sku|de|del|el|una|un|producto|unidades)\b/u', ' ', $text) ?? $text;
        return trim(preg_replace('/\s+/u', ' ', $words) ?? $words);
    }

    private function recommend(array $products, ?string $light, bool $easy): array
    {
        $ranked=[];
        foreach ($products as $product) {
            $category=$this->normalize((string)$product['category'].' '.(string)$product['category_name']);
            if ((int)$product['stock']<1 || preg_match('/\b(accesorio|accesorios|maceta|macetas)\b/u',$category)===1) continue;
            if ($easy && $this->normalize((string)$product['care_level'])!=='facil') continue;
            $lightText=$this->normalize((string)$product['light']);
            $score=$light===null ? 1.0 : $this->lightMembership($lightText,$light);
            if ($score<=0.0) continue;
            $ranked[]=['score'=>$score,'product'=>$product];
        }
        usort($ranked,static fn(array $a,array $b):int=>($b['score']<=>$a['score']) ?: strcmp((string)$a['product']['name'],(string)$b['product']['name']));
        $ranked=array_slice($ranked,0,3);
        if ($ranked===[]) return ['message'=>'No encontré plantas activas y disponibles con esos criterios. Puedes probar otra condición de luz o cuidado.'];
        $names=[]; $results=[];
        foreach ($ranked as $item) {
            $product=$item['product'];
            $detail=(string)$product['name'].' ('.$product['sku'].'): la ficha indica «'.((string)$product['light'] ?: 'luz no especificada').'»';
            if ($easy) $detail.=' y cuidado '.strtolower((string)$product['care_level']);
            $names[]=$detail.'.'; $results[]=$this->publicProduct($product);
        }
        $target=$light===null?'con cuidado fácil':$this->lightLabel($light).($easy?' y cuidado fácil':'');
        return ['message'=>'Para '.$target.' encontré estas opciones disponibles: '.implode(' ',$names),'products'=>$results];
    }

    private function lightMembership(string $value, string $requested): float
    {
        $direct=preg_match('/\bdirecta\b/u',$value)===1;
        $indirect=preg_match('/\bindirecta\b/u',$value)===1;
        $bright=preg_match('/\bbrillante\b/u',$value)===1;
        $low=preg_match('/\b(baja|poca)\b/u',$value)===1;
        $medium=preg_match('/\bmedia\b/u',$value)===1;
        if ($direct) return $requested==='bright' ? 1.0 : 0.0;
        if ($low && $medium) return $requested==='low' ? 0.9 : ($requested==='medium' ? 0.8 : 0.0);
        if ($low) return $requested==='low' ? 1.0 : ($requested==='medium' ? 0.25 : 0.0);
        if ($indirect && $bright) return $requested==='medium' ? 0.9 : ($requested==='bright' ? 0.65 : 0.0);
        if ($indirect) return $requested==='medium' ? 1.0 : ($requested==='low' ? 0.2 : 0.0);
        if ($bright) return $requested==='bright' ? 0.9 : ($requested==='medium' ? 0.3 : 0.0);
        if ($medium) return $requested==='medium' ? 0.9 : ($requested==='low' ? 0.25 : 0.0);
        return 0.0;
    }

    private function requestedLight(string $query): ?string
    {
        if (preg_match('/\b(poca luz|poco luminosa|luz baja|baja luminosidad)\b/u',$query)===1) return 'low';
        if (preg_match('/\b(luz indirecta|indirecta|luz media|luz moderada|media luminosidad)\b/u',$query)===1) return 'medium';
        if (preg_match('/\b(luz brillante|brillante|luz directa|directa|sol directo|muy luminosa)\b/u',$query)===1) return 'bright';
        return null;
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
            'message'=>'Tendencia de demanda de '.$product['name'].' ('.$product['sku'].'): '.$direction.' entre '.$labels[0].' y '.end($labels).'. La regresión lineal estima '.number_format($forecast,0,',','.').' unidades para el mes siguiente ('.$now->format('m/Y').'). Es una estimación de demostración basada en ventas confirmadas; no es una garantía. Motor: '.$engine.'.',
            'engine'=>strtolower($engine),
        ];
    }

    private function publicProduct(array $product): array
    {
        return ['name'=>(string)$product['name'],'url'=>'/producto/'.rawurlencode((string)$product['slug'])];
    }

    private function normalize(string $value): string
    {
        $value=lowercase($value);
        $value=strtr($value,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        $value=preg_replace('/[^a-z0-9\-\s]/u',' ',$value) ?? $value;
        return trim(preg_replace('/\s+/u',' ',$value) ?? $value);
    }

    private function lightLabel(string $light): string
    {
        return ['low'=>'poca luz','medium'=>'luz indirecta o media','bright'=>'luz brillante o directa'][$light]??'esa luz';
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
