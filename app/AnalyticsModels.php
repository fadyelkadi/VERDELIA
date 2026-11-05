<?php
declare(strict_types=1);

/** Modelos descriptivos de ventas; no representan una garantía de resultados futuros. */
final class AnalyticsModels
{
    /** @return array{available:bool,reason:string,n:int,slope:?float,intercept:?float,r_squared:?float,rmse:?float,fitted:array,forecast:array} */
    public static function linear(array $values, int $forecastPeriods = 3): array
    {
        $y = self::numericValues($values);
        $n = count($y);
        if ($n < 2) return self::unavailable($n, 'Se necesitan al menos dos períodos para ajustar una tendencia lineal.');

        $sumX = 0.0; $sumY = 0.0; $sumXX = 0.0; $sumXY = 0.0;
        foreach ($y as $index => $value) {
            $x = (float)$index;
            $sumX += $x; $sumY += $value; $sumXX += $x * $x; $sumXY += $x * $value;
        }
        $denominator = $n * $sumXX - $sumX * $sumX;
        if (!is_finite($denominator) || abs($denominator) < 1.0e-12) return self::unavailable($n, 'Los períodos no permiten calcular una pendiente estable.');
        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $intercept = ($sumY - $slope * $sumX) / $n;
        if (!is_finite($slope) || !is_finite($intercept)) return self::unavailable($n, 'La escala de los datos no permite un ajuste lineal finito.');

        $fitted = [];
        for ($x = 0; $x < $n; $x++) $fitted[] = $intercept + $slope * $x;
        $forecast = [];
        for ($step = 0; $step < max(0, min(12, $forecastPeriods)); $step++) $forecast[] = $intercept + $slope * ($n + $step);
        $metrics = self::metrics($y, $fitted);
        if ($metrics === null) return self::unavailable($n, 'El ajuste produjo valores no finitos.');
        return ['available'=>true,'reason'=>'','n'=>$n,'slope'=>$slope,'intercept'=>$intercept,'r_squared'=>$metrics['r_squared'],'rmse'=>$metrics['rmse'],'fitted'=>$fitted,'forecast'=>$forecast];
    }

    /** @return array{available:bool,reason:string,n:int,a:?float,b:?float,r_squared:?float,rmse:?float,fitted:array,forecast:array} */
    public static function exponential(array $values, int $forecastPeriods = 3): array
    {
        $y = self::numericValues($values);
        $n = count($y);
        if ($n < 3) return self::unavailableExponential($n, 'Se necesitan al menos tres períodos para evaluar una curva exponencial.');
        foreach ($y as $value) {
            if ($value <= 0.0) return self::unavailableExponential($n, 'El ajuste exponencial requiere ventas estrictamente positivas en todos los períodos del intervalo.');
        }

        $logs = array_map('log', $y);
        $linear = self::linear($logs, 0);
        if (!$linear['available'] || !is_float($linear['slope']) || !is_float($linear['intercept'])) {
            return self::unavailableExponential($n, 'No se pudo ajustar una recta estable sobre los logaritmos de las ventas.');
        }
        $a = exp($linear['intercept']);
        $b = $linear['slope'];
        if (!is_finite($a) || $a <= 0.0 || !is_finite($b)) return self::unavailableExponential($n, 'Los parámetros exponenciales quedaron fuera del rango numérico.');

        $fitted = [];
        for ($x = 0; $x < $n; $x++) {
            $prediction = self::safeExp($linear['intercept'] + $b * $x);
            if ($prediction === null) return self::unavailableExponential($n, 'La curva calculada excede el rango numérico seguro.');
            $fitted[] = $prediction;
        }
        $forecast = [];
        for ($step = 0; $step < max(0, min(12, $forecastPeriods)); $step++) {
            $prediction = self::safeExp($linear['intercept'] + $b * ($n + $step));
            if ($prediction === null) return self::unavailableExponential($n, 'La proyección excede el rango numérico seguro.');
            $forecast[] = $prediction;
        }
        $metrics = self::metrics($y, $fitted);
        if ($metrics === null) return self::unavailableExponential($n, 'El ajuste produjo valores no finitos.');
        return ['available'=>true,'reason'=>'','n'=>$n,'a'=>$a,'b'=>$b,'r_squared'=>$metrics['r_squared'],'rmse'=>$metrics['rmse'],'fitted'=>$fitted,'forecast'=>$forecast];
    }

    /** @return array{available:bool,reason:string,model:?string} */
    public static function compare(array $linear, array $exponential): array
    {
        $hasLinear = ($linear['available'] ?? false) && is_float($linear['rmse'] ?? null);
        $hasExponential = ($exponential['available'] ?? false) && is_float($exponential['rmse'] ?? null);
        if (!$hasLinear && !$hasExponential) return ['available'=>false,'reason'=>'No hay dos modelos válidos para comparar en este intervalo.','model'=>null];
        if (!$hasLinear) return ['available'=>true,'reason'=>'Solo la curva exponencial cumple los requisitos de datos.','model'=>'exponential'];
        if (!$hasExponential) return ['available'=>true,'reason'=>'Solo la tendencia lineal es válida; el ajuste exponencial no cumple sus requisitos.','model'=>'linear'];
        $scale = max(1.0, abs((float)$linear['rmse']), abs((float)$exponential['rmse']));
        if (abs((float)$linear['rmse'] - (float)$exponential['rmse']) <= 1.0e-9 * $scale) {
            return ['available'=>true,'reason'=>'Los errores RMSE son prácticamente iguales; no hay un ganador claro.','model'=>null];
        }
        $model = (float)$exponential['rmse'] < (float)$linear['rmse'] ? 'exponential' : 'linear';
        return ['available'=>true,'reason'=>'Se compara RMSE en la escala original de ingresos; menor error describe mejor este intervalo.','model'=>$model];
    }

    private static function numericValues(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) return [];
            $number = (float)$value;
            if (!is_finite($number)) return [];
            $result[] = $number;
        }
        return $result;
    }

    private static function metrics(array $actual, array $predicted): ?array
    {
        $n = count($actual);
        if ($n === 0 || $n !== count($predicted)) return null;
        $mean = array_sum($actual) / $n; $sse = 0.0; $sst = 0.0;
        foreach ($actual as $i => $value) {
            $residual = $value - $predicted[$i];
            $deviation = $value - $mean;
            $sse += $residual * $residual; $sst += $deviation * $deviation;
        }
        $rmse = sqrt($sse / $n);
        $rSquared = $sst > 1.0e-12 ? 1.0 - ($sse / $sst) : null;
        if (!is_finite($rmse) || ($rSquared !== null && !is_finite($rSquared))) return null;
        return ['r_squared'=>$rSquared,'rmse'=>$rmse];
    }

    private static function safeExp(float $logValue): ?float
    {
        if (!is_finite($logValue) || $logValue > 700.0 || $logValue < -700.0) return null;
        $value = exp($logValue);
        return is_finite($value) ? $value : null;
    }

    private static function unavailable(int $n, string $reason): array
    {
        return ['available'=>false,'reason'=>$reason,'n'=>$n,'slope'=>null,'intercept'=>null,'r_squared'=>null,'rmse'=>null,'fitted'=>[],'forecast'=>[]];
    }

    private static function unavailableExponential(int $n, string $reason): array
    {
        return ['available'=>false,'reason'=>$reason,'n'=>$n,'a'=>null,'b'=>null,'r_squared'=>null,'rmse'=>null,'fitted'=>[],'forecast'=>[]];
    }
}
