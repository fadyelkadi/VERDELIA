<?php
declare(strict_types=1);

const VERDELIA_SESSION_IDLE_SECONDS = 1800;

function verdelia_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $sessionDirectory = dirname(__DIR__) . '/storage/sessions';
    if (!is_dir($sessionDirectory) && !@mkdir($sessionDirectory, 0700, true) && !is_dir($sessionDirectory)) {
        throw new RuntimeException('No se pudo preparar el almacenamiento privado de sesiones.');
    }
    if (session_save_path($sessionDirectory) === false) {
        throw new RuntimeException('No se pudo usar el almacenamiento privado de sesiones.');
    }
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string)(VERDELIA_SESSION_IDLE_SECONDS + 600));
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    session_name('verdelia_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!session_start()) throw new RuntimeException('No se pudo iniciar la sesión.');
    $now = time();
    $last = filter_var($_SESSION['last_activity'] ?? null, FILTER_VALIDATE_INT);
    if ($last !== false && $last !== null && ($now - $last) > VERDELIA_SESSION_IDLE_SECONDS) {
        $_SESSION = [];
        if (!session_regenerate_id(true)) throw new RuntimeException('No se pudo renovar la sesión expirada.');
    }
    $_SESSION['last_activity'] = $now;
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function verdelia_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) verdelia_session_start();
    return (string)$_SESSION['csrf_token'];
}

function verdelia_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(verdelia_csrf_token()) . '">';
}

function verdelia_csrf_valid(mixed $candidate): bool
{
    return is_string($candidate) && isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $candidate);
}

function verdelia_login_session(array $user): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) verdelia_session_start();
    if (!session_regenerate_id(true)) throw new RuntimeException('No se pudo renovar el identificador de sesión.');
    $_SESSION = [
        'user_id' => (int)$user['id'],
        'last_activity' => time(),
        'csrf_token' => bin2hex(random_bytes(32)),
    ];
}

function verdelia_logout_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $_SESSION = [];
    $params = session_get_cookie_params();
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'],
            'secure' => (bool)$params['secure'],
            'httponly' => (bool)$params['httponly'],
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
}

/** @param array{id:int,name:string,email:string,role:string,created_at:string}|null $user */
function verdelia_set_current_user(?array $user): void
{
    $GLOBALS['verdelia_current_user'] = $user;
}

/** @return array{id:int,name:string,email:string,role:string,created_at:string}|null */
function currentUser(): ?array
{
    $user = $GLOBALS['verdelia_current_user'] ?? null;
    return is_array($user) ? $user : null;
}

function isAuthenticated(): bool
{
    return currentUser() !== null;
}

function isAdmin(): bool
{
    return (currentUser()['role'] ?? null) === 'ADMIN';
}

function verdelia_require_auth(?string $next = null): void
{
    if (!empty($GLOBALS['verdelia_auth_unavailable'])) {
        http_response_code(503);
        header('Cache-Control: no-store');
        exit('La cuenta no está disponible temporalmente. Inténtalo nuevamente.');
    }
    if (!isAuthenticated()) {
        header('Location: /login' . ($next === '/checkout' ? '?next=%2Fcheckout' : ''), true, 303);
        exit;
    }
}

function verdelia_require_admin(): void
{
    verdelia_require_auth();
    if (!isAdmin()) {
        http_response_code(403);
        header('Cache-Control: no-store');
        exit('No tienes permiso para acceder a esta página.');
    }
}

function verdelia_set_flash(array $flash): void
{
    $_SESSION['flash'] = $flash;
}

/** @return array<string, mixed> */
function verdelia_take_flash(): array
{
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : [];
}

/** @param array<string, mixed> $data */
function verdelia_auth_rate_limit(string $email, bool $clear = false): bool
{
    $ip = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    $key = hash('sha256', strtolower(trim($email)) . "\0" . $ip);
    $file = dirname(__DIR__) . '/storage/auth-attempts.json';
    $directory = dirname($file);
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) return false;
    $handle = @fopen($file, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        return false;
    }
    rewind($handle);
    $raw = stream_get_contents($handle);
    $state = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($state)) $state = [];
    $now = time();
    foreach ($state as $storedKey => $entry) {
        if (!is_array($entry) || !isset($entry['time']) || $now - (int)$entry['time'] > 900) unset($state[$storedKey]);
    }
    $entry = $state[$key] ?? ['count' => 0, 'time' => $now];
    $blocked = (int)($entry['count'] ?? 0) >= 6 && $now - (int)($entry['time'] ?? 0) <= 900;
    if ($clear) {
        unset($state[$key]);
    } elseif (!$blocked) {
        if (!isset($state[$key]) || $now - (int)$entry['time'] > 900) $state[$key] = ['count' => 1, 'time' => $now];
        else $state[$key]['count'] = (int)$entry['count'] + 1;
    }
    if (count($state) > 5000) $state = array_slice($state, -2500, null, true);
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $blocked;
}
