<?php
declare(strict_types=1);

final class AnalyticsRepository
{
    private const MAX_RANGE_DAYS = 730;
    private const VALID_PERIODS = ['day','week','month'];

    public function __construct(private PDO $pdo) {}

    public function analyze(?string $fromValue, ?string $toValue, ?string $periodValue): array
    {
        [$from,$to,$period] = $this->filters($fromValue,$toValue,$periodValue);
        $rangeDays = (int)$from->diff($to)->days + 1;
        $previousEnd = $from->modify('-1 day');
        $previousStart = $previousEnd->modify('-'.($rangeDays-1).' days');

        $dailyRows = $this->dailySales($from->format('Y-m-d 00:00:00'),$to->modify('+1 day')->format('Y-m-d 00:00:00'));
        $series = $this->buildSeries($dailyRows,$from,$to,$period);
        $currentMetrics = $this->metrics($dailyRows);
        $previousMetrics = $this->aggregate($previousStart->format('Y-m-d 00:00:00'),$from->format('Y-m-d 00:00:00'));

        $values = array_map(static fn(array $point): float => (float)$point['revenue_cents'],$series);
        // An empty interval has no observations to model: zero-filled chart buckets are not sales data.
        $linear = $currentMetrics['confirmed_orders'] > 0
            ? AnalyticsModels::linear($values,3)
            : AnalyticsModels::linear([],3);
        $exponential = $currentMetrics['confirmed_orders'] > 0
            ? AnalyticsModels::exponential($values,3)
            : AnalyticsModels::exponential([],3);
        $comparison = AnalyticsModels::compare($linear,$exponential);
        $currentMetrics['gross_profit_cents'] = $currentMetrics['revenue_cents'] - $currentMetrics['cogs_cents'];
        $previousMetrics['gross_profit_cents'] = $previousMetrics['revenue_cents'] - $previousMetrics['cogs_cents'];

        foreach (['revenue_cents','confirmed_orders','units','gross_profit_cents'] as $key) {
            $currentMetrics['growth'][$key] = self::growth($currentMetrics[$key],$previousMetrics[$key]);
        }

        $categories = $this->categorySales($from->format('Y-m-d 00:00:00'),$to->modify('+1 day')->format('Y-m-d 00:00:00'));
        foreach ($categories as &$category) {
            $category['cogs_cents'] = (int)$category['cogs_cents'];
            $category['gross_profit_cents'] = (int)$category['revenue_cents'] - $category['cogs_cents'];
            $category['share_percent'] = $currentMetrics['revenue_cents'] > 0
                ? round((int)$category['revenue_cents'] * 100 / $currentMetrics['revenue_cents'],1)
                : 0.0;
        }
        unset($category);

        return [
            'filters'=>['from'=>$from->format('Y-m-d'),'to'=>$to->format('Y-m-d'),'period'=>$period,'range_days'=>$rangeDays],
            'metrics'=>$currentMetrics,
            'previous'=>$previousMetrics,
            'series'=>$series,
            'products'=>$this->topProducts($from->format('Y-m-d 00:00:00'),$to->modify('+1 day')->format('Y-m-d 00:00:00')),
            'categories'=>$categories,
            'models'=>['linear'=>$linear,'exponential'=>$exponential,'comparison'=>$comparison],
            'definitions'=>[
                'revenue'=>'Suma de precios históricos por cantidad en pedidos pagados/entregados con pago aprobado; excluye envío (demo: $0).',
                'cogs'=>'Suma de costos unitarios históricos por cantidad en las mismas líneas confirmadas.',
                'gross_profit'=>'Ingreso de productos menos costo de mercancía vendida; estimación bruta, no descuenta gastos, impuestos ni operación.',
                'confirmation_date'=>'Los períodos usan confirmed_at; se requiere estado PAID/FULFILLED y al menos un pago APPROVED.',
            ],
        ];
    }

    private function filters(?string $fromValue, ?string $toValue, ?string $periodValue): array
    {
        $today = new DateTimeImmutable('today');
        $fromValue = $fromValue !== null && $fromValue !== '' ? $fromValue : $today->modify('-6 months')->format('Y-m-d');
        $toValue = $toValue !== null && $toValue !== '' ? $toValue : $today->format('Y-m-d');
        $period = in_array($periodValue,self::VALID_PERIODS,true) ? (string)$periodValue : 'month';
        $from = $this->strictDate($fromValue,'La fecha inicial no es válida.');
        $to = $this->strictDate($toValue,'La fecha final no es válida.');
        if ($from > $to) throw new InvalidArgumentException('La fecha inicial debe ser anterior o igual a la fecha final.');
        $days = (int)$from->diff($to)->days + 1;
        if ($days > self::MAX_RANGE_DAYS) throw new InvalidArgumentException('El intervalo máximo para esta consulta es de 730 días.');
        return [$from,$to,$period];
    }

    private function strictDate(string $value, string $message): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($message);
        }
        return $date;
    }

    private function dailySales(string $from, string $until): array
    {
        $query = $this->pdo->prepare("SELECT DATE(o.confirmed_at) AS sale_day,COUNT(DISTINCT o.id) AS confirmed_orders,
                COALESCE(SUM(oi.line_total_cents),0) AS revenue_cents,
                COALESCE(SUM(oi.unit_cost_cents * oi.quantity),0) AS cogs_cents,
                COALESCE(SUM(oi.quantity),0) AS units
            FROM orders o
            JOIN order_items oi ON oi.order_id=o.id
            WHERE o.status IN ('PAID','FULFILLED') AND o.confirmed_at>=? AND o.confirmed_at<?
                AND EXISTS (SELECT 1 FROM payments p WHERE p.order_id=o.id AND p.status='APPROVED')
            GROUP BY DATE(o.confirmed_at) ORDER BY sale_day");
        $query->execute([$from,$until]);
        return $query->fetchAll();
    }

    private function aggregate(string $from, string $until): array
    {
        $query = $this->pdo->prepare("SELECT COUNT(DISTINCT o.id) AS confirmed_orders,
                COALESCE(SUM(oi.line_total_cents),0) AS revenue_cents,
                COALESCE(SUM(oi.unit_cost_cents * oi.quantity),0) AS cogs_cents,
                COALESCE(SUM(oi.quantity),0) AS units
            FROM orders o JOIN order_items oi ON oi.order_id=o.id
            WHERE o.status IN ('PAID','FULFILLED') AND o.confirmed_at>=? AND o.confirmed_at<?
                AND EXISTS (SELECT 1 FROM payments p WHERE p.order_id=o.id AND p.status='APPROVED')");
        $query->execute([$from,$until]);
        return $this->normalizeMetrics($query->fetch() ?: []);
    }

    private function metrics(array $rows): array
    {
        $metrics = ['confirmed_orders'=>0,'revenue_cents'=>0,'cogs_cents'=>0,'units'=>0];
        foreach ($rows as $row) {
            foreach (['confirmed_orders','revenue_cents','cogs_cents','units'] as $key) $metrics[$key] += (int)$row[$key];
        }
        $metrics['gross_profit_cents'] = $metrics['revenue_cents'] - $metrics['cogs_cents'];
        return $metrics;
    }

    private function normalizeMetrics(array $row): array
    {
        return [
            'confirmed_orders'=>(int)($row['confirmed_orders']??0),
            'revenue_cents'=>(int)($row['revenue_cents']??0),
            'cogs_cents'=>(int)($row['cogs_cents']??0),
            'units'=>(int)($row['units']??0),
            'gross_profit_cents'=>(int)($row['revenue_cents']??0)-(int)($row['cogs_cents']??0),
        ];
    }

    private function buildSeries(array $dailyRows, DateTimeImmutable $from, DateTimeImmutable $to, string $period): array
    {
        $keys = [];
        $cursor = match($period) {
            'week'=>$from->modify('monday this week'),
            'month'=>$from->modify('first day of this month'),
            default=>$from,
        };
        $last = $period === 'week' ? $to->modify('monday this week') : ($period === 'month' ? $to->modify('first day of this month') : $to);
        while ($cursor <= $last) {
            [$key,$label] = $this->periodKey($cursor,$period);
            $keys[$key] = ['label'=>$label,'revenue_cents'=>0,'cogs_cents'=>0,'confirmed_orders'=>0,'units'=>0];
            $cursor = $period === 'week' ? $cursor->modify('+1 week') : ($period === 'month' ? $cursor->modify('+1 month') : $cursor->modify('+1 day'));
        }
        foreach ($dailyRows as $row) {
            $date = new DateTimeImmutable((string)$row['sale_day']);
            [$key] = $this->periodKey($date,$period);
            if (!isset($keys[$key])) continue;
            foreach (['revenue_cents','cogs_cents','confirmed_orders','units'] as $metric) $keys[$key][$metric] += (int)$row[$metric];
        }
        $result = [];
        foreach ($keys as $key=>$row) {
            $row['key'] = $key;
            $row['gross_profit_cents'] = $row['revenue_cents'] - $row['cogs_cents'];
            $result[] = $row;
        }
        return $result;
    }

    private function periodKey(DateTimeImmutable $date, string $period): array
    {
        if ($period === 'day') return [$date->format('Y-m-d'),$date->format('d/m')];
        if ($period === 'week') return [$date->format('o-W'),'Sem '.$date->format('W').' · '.$date->format('o')];
        $months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        return [$date->format('Y-m'),$months[(int)$date->format('n')-1].' '.$date->format('Y')];
    }

    private function topProducts(string $from, string $until): array
    {
        $query = $this->pdo->prepare("SELECT oi.sku_snapshot AS sku,oi.name_snapshot AS name,
                SUM(oi.quantity) AS units,SUM(oi.line_total_cents) AS revenue_cents,
                SUM(oi.unit_cost_cents * oi.quantity) AS cogs_cents
            FROM orders o JOIN order_items oi ON oi.order_id=o.id
            WHERE o.status IN ('PAID','FULFILLED') AND o.confirmed_at>=? AND o.confirmed_at<?
                AND EXISTS (SELECT 1 FROM payments p WHERE p.order_id=o.id AND p.status='APPROVED')
            GROUP BY oi.sku_snapshot,oi.name_snapshot ORDER BY units DESC,revenue_cents DESC,oi.name_snapshot ASC LIMIT 10");
        $query->execute([$from,$until]);
        return array_map(static function(array $row): array {
            $row['units']=(int)$row['units']; $row['revenue_cents']=(int)$row['revenue_cents']; $row['cogs_cents']=(int)$row['cogs_cents'];
            $row['gross_profit_cents']=$row['revenue_cents']-$row['cogs_cents']; return $row;
        },$query->fetchAll());
    }

    private function categorySales(string $from, string $until): array
    {
        $query = $this->pdo->prepare("SELECT COALESCE(NULLIF(oi.category_name_snapshot,''),c.name,'Sin categoría') AS category,
                SUM(oi.quantity) AS units,SUM(oi.line_total_cents) AS revenue_cents,
                SUM(oi.unit_cost_cents * oi.quantity) AS cogs_cents
            FROM orders o JOIN order_items oi ON oi.order_id=o.id
            LEFT JOIN products p ON p.id=oi.product_id LEFT JOIN categories c ON c.id=p.category_id
            WHERE o.status IN ('PAID','FULFILLED') AND o.confirmed_at>=? AND o.confirmed_at<?
                AND EXISTS (SELECT 1 FROM payments pmt WHERE pmt.order_id=o.id AND pmt.status='APPROVED')
            GROUP BY COALESCE(NULLIF(oi.category_name_snapshot,''),c.name,'Sin categoría')
            ORDER BY revenue_cents DESC,category ASC");
        $query->execute([$from,$until]);
        return array_map(static function(array $row): array {
            $row['units']=(int)$row['units']; $row['revenue_cents']=(int)$row['revenue_cents']; $row['cogs_cents']=(int)$row['cogs_cents'];
            $row['gross_profit_cents']=$row['revenue_cents']-$row['cogs_cents']; return $row;
        },$query->fetchAll());
    }

    private static function growth(int $current, int $previous): ?float
    {
        if ($previous === 0) return null;
        return round(($current-$previous)*100/abs($previous),1);
    }
}
