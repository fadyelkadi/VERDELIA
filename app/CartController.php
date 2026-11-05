<?php
declare(strict_types=1);

final class CartController
{
    public function __construct(private CartRepository $cart) {}

    public function handle(string $method, ?int $userId): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        if ($method === 'GET') {
            $result = $this->cart->getCart($userId, $_SESSION['guest_cart'] ?? []);
            $this->respond(200, ['ok'=>true] + $result + ['csrf_token'=>verdelia_csrf_token()]);
        }
        if ($method !== 'POST') {
            header('Allow: GET, POST');
            $this->respond(405, ['ok'=>false,'message'=>'Método no permitido.']);
        }
        $body = file_get_contents('php://input', false, null, 0, 16385);
        if ($body === false || strlen($body) > 16384) $this->respond(413, ['ok'=>false,'message'=>'La solicitud supera el tamaño permitido.']);
        $payload = json_decode($body, true);
        if (!is_array($payload)) $this->respond(400, ['ok'=>false,'message'=>'La solicitud no contiene JSON válido.']);
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($payload['csrf_token'] ?? null);
        if (!verdelia_csrf_valid($token)) $this->respond(419, ['ok'=>false,'message'=>'La sesión expiró. Recarga la página e inténtalo nuevamente.']);
        try {
            $guestCart = is_array($_SESSION['guest_cart'] ?? null) ? $_SESSION['guest_cart'] : [];
            $result = $this->cart->mutate(
                $userId,
                $guestCart,
                is_string($payload['action'] ?? null) ? $payload['action'] : '',
                $payload['sku'] ?? null,
                $payload['quantity'] ?? null
            );
            if ($userId === null) $_SESSION['guest_cart'] = $guestCart;
            CheckoutController::invalidateForCartChange();
            $this->respond(200, ['ok'=>true] + $result + ['csrf_token'=>verdelia_csrf_token()]);
        } catch (CartException $exception) {
            $this->respond($exception->httpStatus, ['ok'=>false,'message'=>$exception->getMessage()]);
        } catch (Throwable $exception) {
            verdelia_log_error($exception, 'cart-api');
            $this->respond(503, ['ok'=>false,'message'=>'No pudimos actualizar el carrito. Inténtalo nuevamente.']);
        }
    }

    private function respond(int $status, array $payload): never
    {
        http_response_code($status);
        echo json_encode($payload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE);
        exit;
    }
}
