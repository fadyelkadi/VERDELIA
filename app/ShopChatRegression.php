<?php
declare(strict_types=1);

/** Small adapter for the optional Python linear-regression engine. */
final class ShopChatRegression
{
    private const MAX_MONTHLY_UNITS = 1000000000;
    private const PROCESS_TIMEOUT_SECONDS = 1.5;

    /** @return array{available:bool,slope:?float,forecast:array,engine:string} */
    public static function linear(array $monthlyUnits): array
    {
        if (!self::validSeries($monthlyUnits)) {
            return self::unavailable('php');
        }

        $python = getenv('VERDELIA_PYTHON_EXE');
        if (is_string($python) && trim($python) !== '') {
            $python = self::trustedPythonExecutable(trim($python));
            if ($python !== null) {
                $result = self::runPython($python, $monthlyUnits);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return self::runPhp($monthlyUnits);
    }

    private static function runPhp(array $monthlyUnits): array
    {
        $model = AnalyticsModels::linear($monthlyUnits, 1);
        $available = ($model['available'] ?? false) === true
            && is_numeric($model['slope'] ?? null)
            && is_finite((float)$model['slope'])
            && isset($model['forecast'][0])
            && is_numeric($model['forecast'][0])
            && is_finite((float)$model['forecast'][0]);

        return [
            'available' => $available,
            'slope' => $available ? (float)$model['slope'] : null,
            'forecast' => $available ? [(float)$model['forecast'][0]] : [],
            'engine' => 'php',
        ];
    }

    private static function runPython(string $executable, array $monthlyUnits): ?array
    {
        if (!function_exists('proc_open') || !function_exists('proc_get_status')) {
            return null;
        }

        $script = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'chat-regression.py';
        if (!is_file($script)) {
            return null;
        }

        $outputPath = tempnam(sys_get_temp_dir(), 'verd-reg-');
        if (!is_string($outputPath)) {
            return null;
        }

        $command = [$executable, $script, json_encode(array_values($monthlyUnits), JSON_THROW_ON_ERROR)];
        $descriptors = [
            0 => ['file', 'NUL', 'r'],
            1 => ['file', $outputPath, 'w'],
            2 => ['file', $outputPath, 'a'],
        ];
        $root = getenv('SystemRoot') ?: 'C:\\Windows';
        $environment = [
            'SystemRoot' => $root,
            'WINDIR' => $root,
            'TEMP' => sys_get_temp_dir(),
            'TMP' => sys_get_temp_dir(),
        ];
        $process = null;

        try {
            $process = @proc_open(
                $command,
                $descriptors,
                $pipes,
                dirname($script),
                $environment,
                ['bypass_shell' => true]
            );
            if (!is_resource($process)) {
                return null;
            }

            $startedAt = microtime(true);
            $exitCode = null;
            while (true) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    $exitCode = (int)$status['exitcode'];
                    break;
                }
                if (microtime(true) - $startedAt >= self::PROCESS_TIMEOUT_SECONDS) {
                    proc_terminate($process, 9);
                    return null;
                }
                usleep(10000);
            }

            proc_close($process);
            $process = null;
            if ($exitCode !== 0) {
                return null;
            }

            $output = file_get_contents($outputPath);
            if (!is_string($output) || $output === '') {
                return null;
            }
            $decoded = json_decode($output, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || !array_key_exists('available', $decoded)
                || !is_bool($decoded['available']) || !array_key_exists('slope', $decoded)
                || !array_key_exists('forecast', $decoded) || !is_array($decoded['forecast'])) {
                return null;
            }

            if (!$decoded['available']) {
                if ($decoded['slope'] !== null || $decoded['forecast'] !== []) {
                    return null;
                }
                return ['available' => false, 'slope' => null, 'forecast' => [], 'engine' => 'python'];
            }

            if (!is_numeric($decoded['slope']) || !is_finite((float)$decoded['slope'])
                || count($decoded['forecast']) !== 1 || !is_numeric($decoded['forecast'][0])
                || !is_finite((float)$decoded['forecast'][0])) {
                return null;
            }
            return [
                'available' => true,
                'slope' => (float)$decoded['slope'],
                'forecast' => [(float)$decoded['forecast'][0]],
                'engine' => 'python',
            ];
        } catch (Throwable $error) {
            return null;
        } finally {
            if (is_resource($process)) {
                proc_terminate($process, 9);
                proc_close($process);
            }
            @unlink($outputPath);
        }
    }

    private static function trustedPythonExecutable(string $configuredPath): ?string
    {
        // VERDELIA_PYTHON_EXE is deployment configuration, never request input.
        if (!preg_match('/^[A-Za-z]:[\\\\\/]/', $configuredPath)
            || strtolower(pathinfo($configuredPath, PATHINFO_EXTENSION)) !== 'exe') {
            return null;
        }
        $resolved = realpath($configuredPath);
        return is_string($resolved) && is_file($resolved) ? $resolved : null;
    }

    private static function unavailable(string $engine): array
    {
        return ['available' => false, 'slope' => null, 'forecast' => [], 'engine' => $engine];
    }

    private static function validSeries(array $monthlyUnits): bool
    {
        if (count($monthlyUnits) < 3 || count($monthlyUnits) > 12) {
            return false;
        }
        foreach ($monthlyUnits as $value) {
            if (!is_int($value) || $value < 0 || $value > self::MAX_MONTHLY_UNITS) {
                return false;
            }
        }
        return true;
    }
}
