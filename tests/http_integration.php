<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !extension_loaded('curl')) { fwrite(STDERR, "Se requiere PHP CLI con curl.\n"); exit(2); }
require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/database.php';

$baseUrl = rtrim(getenv('VERDELIA_TEST_BASE_URL') ?: 'http://127.0.0.1:8091', '/');
$pdo = verdelia_db();
$tag = bin2hex(random_bytes(8));
$emailA = 'fase4-http-a-' . $tag . '@verdelia.test';
$emailB = 'fase4-http-b-' . $tag . '@verdelia.test';
$emailAdmin = 'fase4-http-admin-' . $tag . '@verdelia.test';
$password = 'Fase4-Segura-' . $tag . '!';
$newPassword = 'Fase4-Nueva-' . $tag . '!';
$adminPassword = 'Fase4-Admin-' . $tag . '!';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) throw new RuntimeException('FALLÓ: ' . $message);
    $assertions++;
};
$client = static function (): CurlHandle {
    $handle = curl_init();
    if (!$handle instanceof CurlHandle) throw new RuntimeException('No se pudo iniciar el cliente HTTP de prueba.');
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>5,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>10,CURLOPT_COOKIEFILE=>'']);
    return $handle;
};
$request = static function (CurlHandle $handle, string $method, string $path, ?array $data = null, ?string $csrf = null, bool $jsonBody = false) use ($baseUrl): array {
    $headers = ['Accept: text/html, application/json'];
    if ($csrf !== null) $headers[] = 'X-CSRF-Token: ' . $csrf;
    $options = [CURLOPT_URL=>$baseUrl . $path,CURLOPT_HTTPHEADER=>$headers,CURLOPT_HTTPGET=>$method === 'GET',CURLOPT_POST=>false];
    if ($method === 'POST') {
        $options[CURLOPT_HTTPGET] = false;
        $options[CURLOPT_POST] = true;
        if ($data !== null) {
            $options[CURLOPT_POSTFIELDS] = $jsonBody ? json_encode($data, JSON_THROW_ON_ERROR) : http_build_query($data);
            $headers[] = $jsonBody ? 'Content-Type: application/json' : 'Content-Type: application/x-www-form-urlencoded';
            $options[CURLOPT_HTTPHEADER] = $headers;
        }
    }
    curl_setopt_array($handle, $options);
    $body = curl_exec($handle);
    if (!is_string($body)) throw new RuntimeException('HTTP local falló: ' . curl_error($handle));
    return ['status'=>(int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE),'body'=>$body,'url'=>(string)curl_getinfo($handle, CURLINFO_EFFECTIVE_URL)];
};
$csrfFrom = static function (string $html): string {
    if (preg_match('/<meta name="csrf-token" content="([a-f0-9]{64})"/', $html, $match) !== 1) throw new RuntimeException('La página no incluyó token CSRF.');
    return $match[1];
};
$json = static function (array $response): array {
    $decoded = json_decode($response['body'], true);
    if (!is_array($decoded)) throw new RuntimeException('La respuesta HTTP no fue JSON válido.');
    return $decoded;
};
$api = static function (CurlHandle $handle, ?array $payload = null, ?string $csrf = null) use ($request,$json): array {
    $response = $request($handle, $payload === null ? 'GET' : 'POST', '/api/cart', $payload, $csrf, true);
    return $response + ['json'=>$json($response)];
};
$stockSnapshot = $pdo->query('SELECT sku,stock FROM products ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
$orderCount = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$clientA = $client();
$clientB = $client();
$clientAdmin = $client();
$insertAdmin = $pdo->prepare("INSERT INTO users (name,email,password_hash,role) VALUES ('Admin temporal Fase 4',?,?, 'ADMIN')");
$insertAdmin->execute([$emailAdmin,password_hash($adminPassword,PASSWORD_DEFAULT)]);

try {
    $page = $request($clientA,'GET','/carrito');
    $csrf = $csrfFrom($page['body']);
    $assert($page['status'] === 200, 'carrito carga por HTTP');
    foreach ([['/tienda','Catálogo'],['/categoria/suculentas','filtro de categoría'],['/producto/aloe-vera-compacta','ficha de producto']] as [$path,$label]) {
        $response = $request($clientA,'GET',$path);
        $assert($response['status'] === 200, $label . ' sigue funcionando');
    }

    $empty = $api($clientA);
    $assert($empty['status'] === 200 && $empty['json']['items'] === [], 'GET API responde carrito invitado vacío: ' . $empty['status'] . ' ' . json_encode($empty['json']));
    $blocked = $api($clientA,['action'=>'add','sku'=>'PLA-001','quantity'=>1],null);
    $assert($blocked['status'] === 419, 'API rechaza mutaciones sin CSRF');

    $added = $api($clientA,['action'=>'add','sku'=>'PLA-001','quantity'=>2,'price_cents'=>1],$csrf);
    $lineA = $added['json']['items'][0] ?? [];
    $assert($added['status'] === 200 && ($lineA['price_cents'] ?? 0) === 1490 && ($lineA['line_total_cents'] ?? 0) === 2980, 'add consulta precio MySQL e ignora precio manipulado');
    $refresh = $api($clientA);
    $assert(($refresh['json']['unit_count'] ?? 0) === 2, 'carrito visitante persiste al actualizar');
    $api($clientA,['action'=>'add','sku'=>'PLA-002','quantity'=>1],$csrf);
    $changed = $api($clientA,['action'=>'set','sku'=>'PLA-001','quantity'=>3],$csrf);
    $assert(($changed['json']['subtotal_cents'] ?? 0) === 5620 && ($changed['json']['unit_count'] ?? 0) === 4, 'cantidades, subtotales y total usan precios vigentes');
    $negative = $api($clientA,['action'=>'set','sku'=>'PLA-001','quantity'=>-1],$csrf);
    $assert($negative['status'] === 422, 'rechaza cantidades negativas');
    $tooMany = $api($clientA,['action'=>'set','sku'=>'PLA-001','quantity'=>25],$csrf);
    $assert($tooMany['status'] === 422, 'rechaza cantidades superiores al stock');
    $missing = $api($clientA,['action'=>'add','sku'=>'ZZZ-999','quantity'=>1],$csrf);
    $assert($missing['status'] === 404, 'rechaza productos inexistentes');
    $removed = $api($clientA,['action'=>'remove','sku'=>'PLA-002'],$csrf);
    $assert(count($removed['json']['items']) === 1, 'elimina líneas del carrito');
    $cleared = $api($clientA,['action'=>'clear'],$csrf);
    $assert($cleared['json']['items'] === [], 'vacía el carrito');

    $api($clientA,['action'=>'add','sku'=>'PLA-001','quantity'=>2],$csrf);
    $csrf = $csrfFrom($request($clientA,'GET','/registro')['body']);
    $csrfDenied = $request($clientA,'POST','/registro',['name'=>'Sin token','email'=>$emailA,'password'=>$password,'password_confirmation'=>$password,'academic_demo'=>'1']);
    $assert($csrfDenied['status'] === 419, 'registro también exige CSRF');
    $registered = $request($clientA,'POST','/registro',['csrf_token'=>$csrf,'name'=>'Cliente temporal Fase 4','email'=>$emailA,'password'=>$password,'password_confirmation'=>$password,'academic_demo'=>'1','role'=>'ADMIN']);
    $assert($registered['status'] === 200 && str_contains($registered['body'],'Mi cuenta'), 'registro real inicia sesión como cliente');
    $persisted = $api($clientA);
    $assert(($persisted['json']['unit_count'] ?? 0) === 2 && ($persisted['json']['items'][0]['sku'] ?? '') === 'PLA-001', 'registro fusiona y persiste el carrito visitante');
    $user = $pdo->prepare('SELECT id,role FROM users WHERE email=?');
    $user->execute([$emailA]);
    $userA = $user->fetch(PDO::FETCH_ASSOC);
    $assert(is_array($userA) && $userA['role'] === 'CUSTOMER', 'usuario temporal persistido con rol CUSTOMER');
    $adminDenied = $request($clientA,'GET','/admin');
    $assert($adminDenied['status'] === 403, 'cliente no puede acceder al panel admin');

    $adminPage = $request($clientAdmin,'GET','/login');
    $adminCsrf = $csrfFrom($adminPage['body']);
    $adminLogin = $request($clientAdmin,'POST','/login',['csrf_token'=>$adminCsrf,'email'=>$emailAdmin,'password'=>$adminPassword]);
    $assert($adminLogin['status'] === 200 && str_contains($adminLogin['url'],'/admin'), 'el rol ADMIN autenticado accede a su panel');
    $cartDenied = $api($clientA,['action'=>'clear'],null);
    $assert($cartDenied['status'] === 419, 'sesión autenticada conserva protección CSRF');

    $account = $request($clientA,'GET','/cuenta');
    $csrf = $csrfFrom($account['body']);
    $changedPassword = $request($clientA,'POST','/cuenta',['csrf_token'=>$csrf,'action'=>'change_password','current_password'=>$password,'password'=>$newPassword,'password_confirmation'=>$newPassword]);
    $assert($changedPassword['status'] === 200 && str_contains($changedPassword['body'],'Actualizamos tu contraseña'), 'cambio de contraseña se guarda con verificación de clave actual');
    $account = $request($clientA,'GET','/cuenta');
    $csrf = $csrfFrom($account['body']);
    $logout = $request($clientA,'POST','/logout',['csrf_token'=>$csrf]);
    $assert($logout['status'] === 200, 'cierre de sesión protegido por CSRF');
    $protected = $request($clientA,'GET','/cuenta');
    $assert(str_contains($protected['url'],'/login'), 'cuenta deja de ser accesible tras logout');

    $loginPage = $request($clientA,'GET','/login');
    $csrf = $csrfFrom($loginPage['body']);
    $wrongLogin = $request($clientA,'POST','/login',['csrf_token'=>$csrf,'email'=>$emailA,'password'=>$password]);
    $assert(str_contains($wrongLogin['body'],'correo o la contraseña'), 'rechaza la contraseña anterior');
    $loginPage = $request($clientA,'GET','/login');
    $csrf = $csrfFrom($loginPage['body']);
    $login = $request($clientA,'POST','/login',['csrf_token'=>$csrf,'email'=>$emailA,'password'=>$newPassword]);
    $assert($login['status'] === 200 && str_contains($login['url'],'/cuenta'), 'inicio de sesión acepta la nueva contraseña');
    $afterLogin = $api($clientA);
    $assert(($afterLogin['json']['unit_count'] ?? 0) === 2, 'carrito autenticado persiste al iniciar sesión de nuevo');

    $csrfB = $csrfFrom($request($clientB,'GET','/registro')['body']);
    $registeredB = $request($clientB,'POST','/registro',['csrf_token'=>$csrfB,'name'=>'Cliente aislado Fase 4','email'=>$emailB,'password'=>$password,'password_confirmation'=>$password,'academic_demo'=>'1']);
    $assert($registeredB['status'] === 200, 'segundo cliente temporal registrado');
    $isolated = $api($clientB);
    $assert($isolated['json']['items'] === [], 'el carrito del segundo cliente está aislado');
    $userB = $pdo->prepare('SELECT id,role FROM users WHERE email=?');
    $userB->execute([$emailB]);
    $rowB = $userB->fetch(PDO::FETCH_ASSOC);
    $assert(is_array($rowB) && $rowB['role'] === 'CUSTOMER', 'segundo usuario persiste con rol seguro');

    $stockAfter = $pdo->query('SELECT sku,stock FROM products ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
    $assert($stockSnapshot === $stockAfter, 'ninguna operación de carrito rebaja existencias');
    $assert((int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() === $orderCount, 'no se crean ni alteran pedidos históricos');
} finally {
    foreach ([$emailA,$emailB,$emailAdmin] as $email) {
        $find = $pdo->prepare("SELECT id FROM users WHERE email=? AND email LIKE 'fase4-http-%@verdelia.test'");
        $find->execute([$email]);
        $id = $find->fetchColumn();
        if ($id !== false) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE ci FROM cart_items ci JOIN carts c ON c.id=ci.cart_id WHERE c.user_id=?')->execute([(int)$id]);
                $pdo->prepare('DELETE FROM carts WHERE user_id=?')->execute([(int)$id]);
                $pdo->prepare('DELETE FROM users WHERE id=? AND email=?')->execute([(int)$id,$email]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
        }
    }
    curl_close($clientA);
    curl_close($clientB);
    curl_close($clientAdmin);
}

echo "Integración HTTP de Fase 4 aprobada: {$assertions} aserciones; usuarios temporales eliminados.\n";
