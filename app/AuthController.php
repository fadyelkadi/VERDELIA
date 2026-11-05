<?php
declare(strict_types=1);

final class AuthController
{
    private ?AuthService $auth;

    public function __construct(?AuthService $auth)
    {
        $this->auth = $auth;
    }

    public function handle(string $path, string $method): bool
    {
        if (!in_array($path, ['/login', '/registro', '/logout', '/cuenta'], true) || $method !== 'POST') return false;
        if (!verdelia_csrf_valid($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            header('Cache-Control: no-store');
            exit('La página expiró. Vuelve a cargarla e inténtalo nuevamente.');
        }

        if ($path === '/logout') {
            verdelia_logout_session();
            header('Location: /', true, 303);
            exit;
        }

        if ($path === '/login') {
            $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $oldEmail = substr($email, 0, 190);
            if (verdelia_auth_rate_limit($email)) {
                verdelia_set_flash(['type' => 'error', 'message' => 'No se pudo iniciar sesión. Espera unos minutos e inténtalo nuevamente.', 'old' => ['email' => $oldEmail]]);
                header('Location: /login', true, 303);
                exit;
            }
            $user = $this->service()->authenticate($email, $password);
            if ($user === null) {
                verdelia_set_flash(['type' => 'error', 'message' => 'El correo o la contraseña no son correctos.', 'old' => ['email' => $oldEmail]]);
                header('Location: /login', true, 303);
                exit;
            }
            verdelia_auth_rate_limit($email, true);
            $this->establishSession($user);
            header('Cache-Control: no-store');
            $next = $this->safeNext($_POST['next'] ?? null);
            header('Location: ' . ($next ?? ($user['role'] === 'ADMIN' ? '/admin' : '/cuenta')), true, 303);
            exit;
        }

        if ($path === '/registro') {
            $name = is_string($_POST['name'] ?? null) ? $_POST['name'] : '';
            $email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
            try {
                if (($_POST['academic_demo'] ?? null) !== '1') {
                    throw new AuthValidationException(['consent' => 'Confirma que entiendes que es una demostración académica.']);
                }
                $user = $this->service()->register($name, $email, $password, $confirmation);
                $this->establishSession($user);
                verdelia_set_flash(['type' => 'success', 'message' => 'Tu cuenta de cliente ya está lista.']);
                header('Location: ' . ($this->safeNext($_POST['next'] ?? null) ?? '/cuenta'), true, 303);
                exit;
            } catch (AuthValidationException $exception) {
                verdelia_set_flash([
                    'type' => 'error',
                    'message' => 'No pudimos crear la cuenta. Revisa los datos.',
                    'errors' => $exception->errors,
                    'old' => ['name' => substr(trim($name), 0, 120), 'email' => substr(trim($email), 0, 190)],
                ]);
                header('Location: /registro', true, 303);
                exit;
            }
        }

        if (!isAuthenticated()) {
            header('Location: /login', true, 303);
            exit;
        }
        $user = currentUser();
        if ($user === null) {
            http_response_code(503);
            header('Cache-Control: no-store');
            exit('La cuenta no está disponible temporalmente.');
        }
        $id = (int)$user['id'];
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        try {
            if ($action === 'update_name') {
                $name = is_string($_POST['name'] ?? null) ? $_POST['name'] : '';
                $this->service()->updateName($id, $name);
                verdelia_set_flash(['type' => 'success', 'message' => 'Actualizamos tu nombre.']);
            } elseif ($action === 'change_password') {
                $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
                $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
                $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
                $this->service()->changePassword($id, $current, $password, $confirmation);
                verdelia_login_session($user);
                verdelia_set_flash(['type' => 'success', 'message' => 'Actualizamos tu contraseña.']);
            } else {
                http_response_code(400);
                exit('La acción solicitada no es válida.');
            }
        } catch (AuthValidationException $exception) {
            $old = $action === 'update_name' && is_string($_POST['name'] ?? null) ? ['old' => ['name' => substr(trim($_POST['name']), 0, 120)]] : [];
            verdelia_set_flash(['type' => 'error', 'message' => 'No pudimos guardar los cambios.', 'errors' => $exception->errors] + $old);
        } catch (Throwable $exception) {
            verdelia_log_error($exception, 'account-update');
            verdelia_set_flash(['type' => 'error', 'message' => 'No pudimos guardar los cambios. Inténtalo nuevamente.']);
        }
        header('Location: /cuenta', true, 303);
        exit;
    }

    private function service(): AuthService
    {
        return $this->auth ?? throw new RuntimeException('Auth service unavailable.');
    }

    private function establishSession(array $user): void
    {
        $guestCart = is_array($_SESSION['guest_cart'] ?? null) ? $_SESSION['guest_cart'] : [];
        if ($guestCart !== []) {
            require_once __DIR__ . '/CartRepository.php';
            (new CartRepository(verdelia_db()))->mergeGuestCart((int)$user['id'], $guestCart);
        }
        verdelia_login_session($user);
    }

    private function safeNext(mixed $candidate): ?string
    {
        return is_string($candidate) && $candidate === '/checkout' ? '/checkout' : null;
    }
}
