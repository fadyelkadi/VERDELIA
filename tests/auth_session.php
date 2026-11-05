<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo CLI\n");
}
require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/auth.php';

$count = 0;
$assert = static function (bool $condition, string $message) use (&$count): void {
    if (!$condition) throw new RuntimeException('FALLÓ: ' . $message);
    $count++;
};

verdelia_session_start();
$cookie = session_get_cookie_params();
$assert($cookie['httponly'] === true, 'cookie HttpOnly');
$assert(($cookie['samesite'] ?? '') === 'Lax', 'cookie SameSite=Lax');
$assert((int)ini_get('session.gc_maxlifetime') >= VERDELIA_SESSION_IDLE_SECONDS, 'recolección de archivos no vence antes que el timeout');
$oldId = session_id();
$user = ['id' => 42, 'name' => 'Cuenta demo', 'email' => 'cliente@verdelia.test', 'role' => 'CUSTOMER', 'created_at' => '2026-01-01 00:00:00'];
verdelia_login_session($user);
$assert(session_id() !== $oldId, 'ID de sesión regenerado al autenticar');
$assert(array_keys($_SESSION) === ['user_id', 'last_activity', 'csrf_token'], 'la sesión guarda solo identidad mínima y seguridad');
$token = verdelia_csrf_token();
$assert(verdelia_csrf_valid($token), 'token CSRF válido');
$assert(!verdelia_csrf_valid('invalid'), 'token CSRF incorrecto rechazado');
verdelia_set_current_user($user);
$assert(isAuthenticated() && !isAdmin(), 'cliente autenticado');
$admin = $user;
$admin['role'] = 'ADMIN';
verdelia_set_current_user($admin);
$assert(isAdmin(), 'rol administrador identificado');
verdelia_set_current_user(null);

$authenticatedId = session_id();
$_SESSION['last_activity'] = time() - VERDELIA_SESSION_IDLE_SECONDS - 1;
$_SESSION['user_id'] = 42;
session_write_close();
session_id($authenticatedId);
verdelia_session_start();
$assert(session_id() !== $authenticatedId, 'sesión expirada rota');
$assert(!isset($_SESSION['user_id']), 'sesión expirada pierde identidad');

$email = 'rate-' . bin2hex(random_bytes(6)) . '@verdelia.test';
for ($i = 0; $i < 6; $i++) $assert(!verdelia_auth_rate_limit($email), 'se permiten los primeros seis intentos');
$assert(verdelia_auth_rate_limit($email), 'el siguiente intento queda limitado');
verdelia_auth_rate_limit($email, true);
$assert(!verdelia_auth_rate_limit($email), 'el contador se limpia tras una autenticación correcta');
verdelia_auth_rate_limit($email, true);
verdelia_logout_session();
$assert(session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['user_id']), 'logout elimina el usuario de sesión');

echo "Sesiones/CSRF/límite de intentos aprobados: {$count} aserciones.\n";
