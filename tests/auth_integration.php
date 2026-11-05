<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo CLI\n");
}
require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/database.php';
require dirname(__DIR__) . '/app/AuthService.php';
require dirname(__DIR__) . '/app/auth.php';

$backupPath = dirname(__DIR__) . '/database/backups/verdelia_20261103_165046.sql';
if (!is_file($backupPath) || filesize($backupPath) < 1024) {
    throw new RuntimeException('Falta el respaldo pre-Fase 3 validado; no se iniciarán pruebas que escriben en users.');
}
$pdo = verdelia_db();
$auth = new AuthService($pdo);
$email = 'auth-test-' . bin2hex(random_bytes(6)) . '@verdelia.test';
$password = 'PruebaSegura-2026!';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) throw new RuntimeException('FALLÓ: ' . $message);
    $assertions++;
};

$pdo->beginTransaction();
try {
    $user = $auth->register('<script>Cuenta segura</script>', $email, $password, $password);
    $assert($user['role'] === 'CUSTOMER', 'el registro público crea solo rol CUSTOMER');
    $stored = $pdo->prepare('SELECT password_hash, role FROM users WHERE id = ?');
    $stored->execute([$user['id']]);
    $row = $stored->fetch();
    $assert(is_array($row) && $row['password_hash'] !== $password && password_verify($password, $row['password_hash']), 'la contraseña se guarda como hash verificable');
    $assert($row['role'] === 'CUSTOMER', 'parámetros extra no pueden cambiar el rol');

    try {
        $auth->register('Otra cuenta', $email, $password, $password);
        $assert(false, 'correo duplicado rechazado');
    } catch (AuthValidationException $exception) {
        $assert(isset($exception->errors['email']), 'correo duplicado rechazado');
    }
    $assert($auth->authenticate($email, 'incorrecta') === null, 'contraseña incorrecta rechazada');
    $assert(($auth->authenticate($email, $password)['id'] ?? null) === $user['id'], 'inicio de sesión valida el hash');

    $auth->updateName($user['id'], '<b>Nombre editado</b>');
    $renamed = $auth->findById($user['id']);
    $assert(($renamed['name'] ?? '') === '<b>Nombre editado</b>', 'perfil permite cambiar nombre');
    $assert(h($renamed['name']) === '&lt;b&gt;Nombre editado&lt;/b&gt;', 'el contenido de usuario se escapa al renderizar');
    try {
        $auth->changePassword($user['id'], 'incorrecta', 'NuevaClaveSegura-2026!', 'NuevaClaveSegura-2026!');
        $assert(false, 'cambio de contraseña exige la actual');
    } catch (AuthValidationException $exception) {
        $assert(isset($exception->errors['current_password']), 'contraseña actual verificada');
    }
    $newPassword = 'NuevaClaveSegura-2026!';
    $auth->changePassword($user['id'], $password, $newPassword, $newPassword);
    $assert($auth->authenticate($email, $password) === null, 'contraseña anterior queda invalidada');
    $assert(($auth->authenticate($email, $newPassword)['id'] ?? null) === $user['id'], 'nueva contraseña permite entrar');

    verdelia_session_start();
    $oldSession = session_id();
    verdelia_login_session($user);
    $assert(session_id() !== $oldSession, 'el identificador rota al iniciar sesión');
    $assert((int)($_SESSION['user_id'] ?? 0) === $user['id'], 'la sesión conserva solo el identificador de usuario');
    $csrf = verdelia_csrf_token();
    $assert(verdelia_csrf_valid($csrf), 'token CSRF válido');
    $assert(!verdelia_csrf_valid('token-inventado'), 'token CSRF inválido rechazado');
    verdelia_set_current_user($user);
    $assert(isAuthenticated() && !isAdmin(), 'cliente autenticado sin rol administrativo');
    $admin = $user;
    $admin['role'] = 'ADMIN';
    verdelia_set_current_user($admin);
    $assert(isAdmin(), 'solo el rol ADMIN obtiene autorización administrativa');
    verdelia_logout_session();
    $assert(session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['user_id']), 'logout elimina la identidad de la sesión');
    verdelia_set_current_user(null);
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}

echo "Autenticación transaccional aprobada: {$assertions} aserciones; no quedaron usuarios de prueba en la base.\n";
