<?php
declare(strict_types=1);

final class CheckoutController
{
    public function __construct(private OrderRepository $orders) {}

    /** Issue server-generated idempotency values for one rendered checkout form. */
    public static function formKeys(): array
    {
        if (!isset($_SESSION['checkout_order_key']) || !is_string($_SESSION['checkout_order_key'])) {
            $_SESSION['checkout_order_key'] = bin2hex(random_bytes(32));
        }
        if (!is_array($_SESSION['checkout_order_keys'] ?? null)) $_SESSION['checkout_order_keys'] = [];
        if (!is_array($_SESSION['checkout_attempt_keys'] ?? null)) $_SESSION['checkout_attempt_keys'] = [];
        $orderKey = $_SESSION['checkout_order_key'];
        $attemptKey = bin2hex(random_bytes(32));
        $_SESSION['checkout_order_keys'][$orderKey] = time();
        $_SESSION['checkout_attempt_keys'][$attemptKey] = time();
        foreach (['checkout_order_keys','checkout_attempt_keys'] as $keyName) {
            $keys = is_array($_SESSION[$keyName] ?? null) ? $_SESSION[$keyName] : [];
            asort($keys, SORT_NUMERIC);
            while (count($keys) > 20) unset($keys[array_key_first($keys)]);
            $_SESSION[$keyName] = $keys;
        }
        return ['order_key'=>$orderKey,'attempt_key'=>$attemptKey];
    }

    public static function invalidateForCartChange(): void
    {
        unset($_SESSION['checkout_order_key'], $_SESSION['checkout_order_keys'], $_SESSION['checkout_attempt_keys']);
    }

    public function handleCheckout(string $method, int $userId): never
    {
        if ($method !== 'POST') {
            http_response_code(405);
            header('Allow: GET, POST');
            exit('Método no permitido.');
        }
        header('Cache-Control: no-store, max-age=0');
        if (!verdelia_csrf_valid($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            exit('La página expiró. Vuelve a cargar el checkout e inténtalo nuevamente.');
        }
        $orderKey = $_POST['order_key'] ?? null;
        $attemptKey = $_POST['attempt_key'] ?? null;
        if (!is_string($orderKey) || !isset($_SESSION['checkout_order_keys'][$orderKey])
            || !is_string($attemptKey) || !isset($_SESSION['checkout_attempt_keys'][$attemptKey])) {
            http_response_code(419);
            exit('La sesión de compra expiró. Vuelve a cargar el checkout.');
        }

        $delivery = [];
        $limits = ['name'=>120,'email'=>190,'phone'=>40,'address'=>180,'city'=>100,'state'=>100,'postal_code'=>20,'note'=>255];
        $tooLong = [];
        foreach ($limits as $key => $max) {
            $value = $_POST[$key] ?? '';
            $delivery[$key] = is_string($value) ? trim($value) : '';
            $length = function_exists('mb_strlen') ? mb_strlen($delivery[$key],'UTF-8') : strlen($delivery[$key]);
            if ($length > $max) $tooLong[$key] = true;
        }
        $errors = [];
        if (strlen($delivery['name']) < 2) $errors['name'] = 'Escribe el nombre de quien recibirá el pedido.';
        if (filter_var($delivery['email'], FILTER_VALIDATE_EMAIL) === false) $errors['email'] = 'Escribe un correo válido.';
        $phoneDigits = preg_replace('/\D+/', '', $delivery['phone']) ?? '';
        if (strlen($phoneDigits) < 7 || preg_match('/^[0-9+() .-]{7,40}$/', $delivery['phone']) !== 1) $errors['phone'] = 'Escribe un teléfono válido de demostración.';
        if (strlen($delivery['address']) < 5) $errors['address'] = 'Indica una dirección de entrega ficticia.';
        if (strlen($delivery['city']) < 2) $errors['city'] = 'Escribe la ciudad.';
        if (strlen($delivery['state']) < 2) $errors['state'] = 'Escribe el estado o provincia.';
        if ($delivery['postal_code'] !== '' && preg_match('/^[A-Za-z0-9 -]{3,20}$/', $delivery['postal_code']) !== 1) $errors['postal_code'] = 'Revisa el código postal.';
        foreach ($tooLong as $key => $_) $errors[$key] = 'Este dato supera el largo permitido.';
        foreach ($delivery as $value) if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) { $errors['delivery'] = 'Uno de los datos contiene caracteres no permitidos.'; break; }
        $expected = $_POST['expected_total_cents'] ?? null;
        if (!is_string($expected) || preg_match('/^\d{1,10}$/', $expected) !== 1) $errors['total'] = 'Actualiza el checkout para revisar el total.';
        if ($errors !== []) {
            verdelia_set_flash(['type'=>'error','message'=>'Revisa los datos de entrega antes de continuar.','errors'=>$errors,'old'=>$delivery]);
            header('Location: /checkout', true, 303);
            exit;
        }
        $decision = is_string($_POST['payment_outcome'] ?? null) ? $_POST['payment_outcome'] : '';
        try {
            $result = $this->orders->processCheckout($userId,$delivery,$orderKey,$attemptKey,$decision,(int)$expected);
            if ($result['order_status'] === 'PAID' || $result['order_status'] === 'FULFILLED') {
                unset($_SESSION['checkout_order_key']);
                $destination = '/pedido/' . rawurlencode($result['order_number']) . '/recibo';
            } elseif ($result['order_status'] === 'CANCELLED') {
                unset($_SESSION['checkout_order_key']);
                $destination = '/pedido/' . rawurlencode($result['order_number']) . '?resultado=cancelado';
            } else {
                verdelia_set_flash(['type'=>'error','message'=>'El pago de demostración fue rechazado. No se descontó inventario; puedes intentarlo de nuevo.']);
                $destination = '/pedido/' . rawurlencode($result['order_number']) . '?resultado=rechazado';
            }
            header('Location: ' . $destination, true, 303);
            exit;
        } catch (OrderException $exception) {
            if ($exception->httpStatus === 409) unset($_SESSION['checkout_order_key']);
            if ($exception->httpStatus === 419) { http_response_code(419); exit($exception->getMessage()); }
            verdelia_set_flash(['type'=>'error','message'=>$exception->getMessage(),'old'=>$delivery]);
            header('Location: /checkout', true, 303);
            exit;
        }
    }
}
