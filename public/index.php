<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/database.php';
require dirname(__DIR__) . '/app/CatalogRepository.php';
require dirname(__DIR__) . '/app/AuthService.php';
require dirname(__DIR__) . '/app/auth.php';
require dirname(__DIR__) . '/app/CartRepository.php';
require dirname(__DIR__) . '/app/CartController.php';
require dirname(__DIR__) . '/app/AuthController.php';
require dirname(__DIR__) . '/app/OrderRepository.php';
require dirname(__DIR__) . '/app/CheckoutController.php';
require dirname(__DIR__) . '/app/AdminRepository.php';
require dirname(__DIR__) . '/app/AdminController.php';
require dirname(__DIR__) . '/app/AnalyticsModels.php';
require dirname(__DIR__) . '/app/AnalyticsRepository.php';

verdelia_session_start();
$requestMethod = strtoupper(is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET');
$authService = null;
$authUnavailable = false;
$currentUser = null;
if (isset($_SESSION['user_id'])) {
    try {
        $authService = new AuthService(verdelia_db());
        $currentUser = $authService->findById((int)$_SESSION['user_id']);
        if ($currentUser === null) verdelia_logout_session();
    } catch (Throwable $exception) {
        verdelia_log_error($exception, 'session-user');
        $authUnavailable = true;
    }
}
verdelia_set_current_user($currentUser);
$GLOBALS['verdelia_auth_unavailable'] = $authUnavailable;

$routePath = '/' . trim(rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
if ($routePath === '//') $routePath = '/';
if (in_array($routePath, ['/login', '/registro', '/logout', '/cuenta'], true) && $requestMethod === 'POST') {
    try {
        if ($authService === null && $routePath !== '/logout') $authService = new AuthService(verdelia_db());
        (new AuthController($authService))->handle($routePath, $requestMethod);
    } catch (Throwable $exception) {
        verdelia_log_error($exception, 'authentication');
        http_response_code(503);
        header('Cache-Control: no-store');
        exit('El servicio de cuentas no está disponible temporalmente. Inténtalo nuevamente.');
    }
}

$currentUser = currentUser();
if ($routePath === '/api/cart') {
    try {
        (new CartController(new CartRepository(verdelia_db())))->handle($requestMethod, $currentUser === null ? null : (int)$currentUser['id']);
    } catch (Throwable $exception) {
        verdelia_log_error($exception, 'cart-api');
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        exit(json_encode(['ok'=>false,'message'=>'El carrito no está disponible temporalmente.']));
    }
}
if ($routePath === '/checkout' && $requestMethod === 'POST') {
    verdelia_require_auth('/checkout');
    try {
        (new CheckoutController(new OrderRepository(verdelia_db())))->handleCheckout($requestMethod,(int)$currentUser['id']);
    } catch (Throwable $exception) {
        verdelia_log_error($exception, 'checkout');
        http_response_code(503);
        header('Cache-Control: no-store');
        exit('No pudimos completar la operación de demostración. Revisa el estado de tu pedido antes de volver a intentarlo.');
    }
}
if (str_starts_with($routePath,'/admin') && $requestMethod==='POST') {
    verdelia_require_admin();
    try {
        (new AdminController(new AdminRepository(verdelia_db())))->handle($requestMethod,$routePath,(int)$currentUser['id']);
    } catch (Throwable $exception) {
        verdelia_log_error($exception,'admin-mutation'); http_response_code(503); header('Cache-Control: no-store'); exit('La operación administrativa no está disponible temporalmente.');
    }
}
if (in_array($routePath, ['/login', '/registro'], true) && $currentUser !== null) {
    header('Location: ' . ($currentUser['role'] === 'ADMIN' ? '/admin' : '/cuenta'), true, 303);
    exit;
}
if ($routePath === '/logout') {
    if ($requestMethod !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Método no permitido.');
    }
}
if (in_array($routePath, ['/cuenta', '/cuenta/pedidos', '/checkout'], true) || str_starts_with($routePath, '/admin') || str_starts_with($routePath, '/pedido/')) {
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
}

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$path = '/' . trim($path, '/');
if ($path === '//') $path = '/';
$products = [];
$categories = [];
$product = null;
$related = [];
$category = null;
$categorySlug = null;
$categoryTitle = null;
$catalogResult = ['items'=>[],'total'=>0,'page'=>1,'per_page'=>8,'total_pages'=>1];
$filters = ['category'=>null,'query'=>'','min_price'=>null,'max_price'=>null,'in_stock'=>false,'care'=>[],'light'=>[],'sort'=>'featured'];
$route = 'home';
$view = 'home.php';
$pageTitle = 'Plantas, macetas y pequeños espacios verdes';
$activeNav = 'inicio';
$flash = in_array($routePath, ['/login', '/registro', '/cuenta', '/cuenta/pedidos', '/checkout'], true) || str_starts_with($routePath, '/pedido/') || str_starts_with($routePath,'/admin') ? verdelia_take_flash() : [];
$checkoutCart = [];
$checkoutKeys = [];
$orderDetail = null;
$isReceipt = false;
$accountOrdersResult = null;
$adminOrdersResult = null;
$currentOrderPage = 1;
$adminDashboard = [];
$adminRecentOrders = [];
$adminProductsResult = null;
$adminCategories = [];
$adminCustomersResult = null;
$adminProductForm = null;
$adminCategoryForm = null;
$adminImages = [];
$adminOrderHistory = [];
$adminMovements = [];
$adminInventoryProduct = null;
$adminAnalyticsResult = null;
$adminAnalyticsError = '';

if ($path === '/') {
    try {
        $repository = new CatalogRepository(verdelia_db());
        $products = $repository->featured(4);
        $categories = $repository->categories();
    } catch (Throwable $exception) {
        verdelia_log_error($exception, 'home-catalog');
        http_response_code(503);
        $route = 'database-unavailable';
        $view = 'database-unavailable.php';
        $pageTitle = 'Catálogo temporalmente no disponible';
    }
} elseif ($path === '/tienda' || preg_match('#^/categoria/([^/]+)$#', $path, $routeMatch) === 1) {
    $route = 'store';
    $view = 'store.php';
    $activeNav = 'tienda';
    $pageTitle = 'Nuestra colección';
    if (isset($routeMatch[1])) {
        $categorySlug = $routeMatch[1];
        if (preg_match('/^[a-z0-9-]{1,80}$/', $categorySlug) !== 1) {
            http_response_code(404); $route='not-found'; $view='not-found.php'; $pageTitle='Página no encontrada';
        }
    }
    if ($route === 'store') {
        try {
            $repository = new CatalogRepository(verdelia_db());
            $categories = $repository->categories();
            if ($categorySlug !== null) {
                $category = $repository->categoryBySlug($categorySlug);
                if ($category === null) { http_response_code(404); $route='not-found'; $view='not-found.php'; $pageTitle='Página no encontrada'; }
                else { $categoryTitle=$category['name']; $filters['category']=$categorySlug; $pageTitle=$category['name']; }
            } else {
                $requestedCategory = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
                $activeSlugs = array_column($categories, 'slug');
                if (in_array($requestedCategory, $activeSlugs, true)) $filters['category']=$requestedCategory;
                $category = $filters['category'] !== null ? $repository->categoryBySlug($filters['category']) : null;
            }
            $rawQuery = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
            $filters['query'] = function_exists('mb_substr') ? mb_substr($rawQuery, 0, 80, 'UTF-8') : substr($rawQuery, 0, 80);
            foreach (['min_price','max_price'] as $priceKey) {
                $raw = $_GET[$priceKey] ?? null;
                if (is_string($raw) && preg_match('/^\d{1,9}$/', $raw) === 1) $filters[$priceKey] = min(100000000, (int)$raw);
            }
            $filters['in_stock'] = ($_GET['in_stock'] ?? '') === '1';
            $requestedCare = is_array($_GET['care'] ?? null) ? array_filter($_GET['care'], 'is_string') : [];
            $requestedLight = is_array($_GET['light'] ?? null) ? array_filter($_GET['light'], 'is_string') : [];
            $filters['care'] = array_values(array_intersect(['Fácil','Media'], $requestedCare));
            $filters['light'] = array_values(array_intersect(['directa','indirecta','brillante'], $requestedLight));
            $sort = is_string($_GET['sort'] ?? null) ? $_GET['sort'] : 'featured';
            $filters['sort'] = in_array($sort, ['featured','price-asc','price-desc','name','recent'], true) ? $sort : 'featured';
            $rawPage = $_GET['page'] ?? '1';
            $page = is_string($rawPage) && preg_match('/^\d{1,6}$/', $rawPage) === 1 ? max(1,(int)$rawPage) : 1;
            if ($route === 'store') {
                $catalogResult = $repository->listing($filters, $page, 8);
                $products = $catalogResult['items'];
            }
        } catch (Throwable $exception) {
            verdelia_log_error($exception, 'store-catalog');
            http_response_code(503);
            $route = 'database-unavailable';
            $view = 'database-unavailable.php';
            $pageTitle = 'Catálogo temporalmente no disponible';
        }
    }
} elseif (preg_match('#^/producto/([a-z0-9-]{1,120})$#', $path, $match) === 1) {
    $route = 'product'; $view = 'product.php'; $activeNav = 'tienda';
    try {
        $repository = new CatalogRepository(verdelia_db());
        $product = $repository->bySlug($match[1]);
        if ($product === null) { http_response_code(404); $route='not-found'; $view='not-found.php'; $pageTitle='Página no encontrada'; }
        else {
            $pageTitle = $product['name'];
            $related = $repository->related($product['category'], (int)$product['id']);
            $products = array_merge([$product], $related);
        }
    } catch (Throwable $exception) {
        verdelia_log_error($exception, 'product-detail');
        http_response_code(503); $route='database-unavailable'; $view='database-unavailable.php'; $pageTitle='Catálogo temporalmente no disponible';
    }
} elseif (preg_match('#^/(?:pedido|admin/pedidos)/([A-Za-z0-9-]+)(?:/(recibo))?$#', $path, $orderMatch) === 1) {
    verdelia_require_auth();
    $orderNumber = strtoupper($orderMatch[1]);
    $isAdminOrder = str_starts_with($path, '/admin/');
    $isReceipt = !empty($orderMatch[2]);
    if (preg_match('/^(?:VD-[0-9]{8}-[A-F0-9]{10}|DEMO-[0-9]{5})$/', $orderNumber) !== 1) {
        http_response_code(404); $route='not-found'; $view='not-found.php'; $pageTitle='Pedido no encontrado';
    } else {
        try {
            $repository = new OrderRepository(verdelia_db());
            $orderDetail = $repository->findOrder($orderNumber,(int)$currentUser['id'],$isAdminOrder && isAdmin());
            if ($orderDetail === null || ($isAdminOrder && !isAdmin())) {
                http_response_code($isAdminOrder ? 403 : 404); $route='not-found'; $view='not-found.php'; $pageTitle='Pedido no encontrado';
            } else {
                if ($isAdminOrder && isAdmin()) $adminOrderHistory=(new AdminRepository(verdelia_db()))->orderHistory((int)$orderDetail['id']);
                $route = 'order'; $view = 'order.php'; $pageTitle = $isReceipt ? 'Comprobante ' . $orderNumber : 'Pedido ' . $orderNumber;
            }
        } catch (Throwable $exception) {
            verdelia_log_error($exception, 'order-detail');
            http_response_code(503); $route='database-unavailable'; $view='database-unavailable.php'; $pageTitle='Pedido temporalmente no disponible';
        }
    }
} elseif (in_array($path, ['/carrito','/login','/registro','/checkout','/cuenta','/cuenta/pedidos'], true)) {
    $routes=['/carrito'=>['cart','cart.php','Tu carrito'],'/login'=>['login','auth.php','Iniciar sesión'],'/registro'=>['register','auth.php','Crear cuenta'],'/checkout'=>['checkout','checkout.php','Finalizar compra'],'/cuenta'=>['account','account.php','Mi cuenta'],'/cuenta/pedidos'=>['account-orders','account-orders.php','Mis pedidos']];
    [$route,$view,$pageTitle]=$routes[$path];
    if ($path === '/checkout' || $path === '/cuenta' || $path === '/cuenta/pedidos') {
        verdelia_require_auth($path === '/checkout' ? '/checkout' : null);
        try {
            $repository = new OrderRepository(verdelia_db());
            if ($path === '/checkout') {
                $checkoutCart = $repository->cartSummary((int)$currentUser['id']);
                if (!empty($checkoutCart['items'])) $checkoutKeys = CheckoutController::formKeys();
            } else {
                $rawOrderPage = $_GET['page'] ?? '1';
                $currentOrderPage = is_string($rawOrderPage) && preg_match('/^\d{1,6}$/',$rawOrderPage) === 1 ? max(1,(int)$rawOrderPage) : 1;
                $accountOrdersResult = $repository->accountOrders((int)$currentUser['id'],$currentOrderPage,$path === '/cuenta' ? 5 : 10);
            }
        } catch (Throwable $exception) {
            verdelia_log_error($exception, 'orders-page');
            http_response_code(503); $route='database-unavailable'; $view='database-unavailable.php'; $pageTitle='Pedidos temporalmente no disponibles';
        }
    }
} elseif (in_array($path, ['/nosotros','/contacto','/preguntas-frecuentes','/privacidad'], true)) {
    $pages=['/nosotros'=>['about','Sobre Verdelia'],'/contacto'=>['contact','Contacto'],'/preguntas-frecuentes'=>['faq','Preguntas frecuentes'],'/privacidad'=>['privacy','Privacidad']];
    [$route,$pageTitle]=$pages[$path]; $view='institutional.php'; $activeNav='nosotros';
} elseif (str_starts_with($path, '/admin')) {
    verdelia_require_admin();
    $route='admin'; $view='admin.php'; $activeNav='admin'; $pageTitle='Panel administrativo';
    $adminSection=trim(substr($path, strlen('/admin')), '/');
    try {
        $adminRepository=new AdminRepository(verdelia_db());
        $pageValue=$_GET['page']??'1'; $adminPage=is_string($pageValue)&&preg_match('/^\d{1,6}$/',$pageValue)===1?max(1,(int)$pageValue):1;
        $adminQuery=is_string($_GET['q']??null)?trim($_GET['q']):'';
        if ($adminSection==='') {
            $adminDashboard=$adminRepository->dashboard(); $adminRecentOrders=$adminRepository->orders('', '', 1, 5)['items'];
        } elseif ($adminSection==='productos') {
            $adminStatus=is_string($_GET['estado']??null)?$_GET['estado']:'';
            $adminProductsResult=$adminRepository->products($adminQuery,$adminStatus,$adminPage,15);
            $adminCategories=$adminRepository->categories();
        } elseif (preg_match('#^productos/(\d+)/editar$#',$adminSection,$adminMatch)===1 || $adminSection==='productos/nuevo') {
            $adminProductForm=$adminSection==='productos/nuevo'?null:$adminRepository->product((int)$adminMatch[1]);
            if ($adminSection!=='productos/nuevo' && $adminProductForm===null) { http_response_code(404); $route='not-found'; $view='not-found.php'; $pageTitle='Producto no encontrado'; }
            else { $adminCategories=$adminRepository->categories(); $adminImages=$adminRepository->imageOptions(); }
        } elseif ($adminSection==='categorias') {
            $adminCategories=$adminRepository->categories();
            $editValue=$_GET['editar']??'';
            if (is_string($editValue)&&preg_match('/^[1-9]\d{0,9}$/',$editValue)===1) $adminCategoryForm=$adminRepository->category((int)$editValue);
            if ($editValue!=='' && $adminCategoryForm===null) { http_response_code(404); $route='not-found'; $view='not-found.php'; $pageTitle='Categoría no encontrada'; }
        } elseif ($adminSection==='inventario') {
            $adminProductsResult=$adminRepository->products($adminQuery,'',$adminPage,15);
            $inventoryId=$_GET['producto']??'';
            if (is_string($inventoryId)&&preg_match('/^[1-9]\d{0,9}$/',$inventoryId)===1) {
                $adminInventoryProduct=$adminRepository->product((int)$inventoryId);
                if ($adminInventoryProduct!==null) $adminMovements=$adminRepository->movements((int)$inventoryId);
            }
        } elseif ($adminSection==='pedidos') {
            $requestedStatus=is_string($_GET['estado']??null)?$_GET['estado']:'';
            $customerValue=$_GET['cliente_id']??'';
            $adminCustomerId=is_string($customerValue)&&preg_match('/^[1-9]\d{0,9}$/',$customerValue)===1?(int)$customerValue:null;
            $adminOrdersResult=$adminRepository->orders($adminQuery,$requestedStatus,$adminPage,15,$adminCustomerId);
        } elseif ($adminSection==='clientes' || $adminSection==='usuarios') {
            $adminSection='clientes'; $adminCustomersResult=$adminRepository->customers($adminQuery,$adminPage,15);
        } elseif ($adminSection==='analitica') {
            $analyticsRepository=new AnalyticsRepository(verdelia_db());
            $fromValue=is_string($_GET['desde']??null)?$_GET['desde']:null;
            $toValue=is_string($_GET['hasta']??null)?$_GET['hasta']:null;
            $periodValue=is_string($_GET['periodo']??null)?$_GET['periodo']:null;
            try {
                $adminAnalyticsResult=$analyticsRepository->analyze($fromValue,$toValue,$periodValue);
            } catch (InvalidArgumentException $validationError) {
                $adminAnalyticsError=$validationError->getMessage();
                $adminAnalyticsResult=$analyticsRepository->analyze(null,null,$periodValue);
            }
        } else {
            http_response_code(404); $route='not-found'; $view='not-found.php'; $pageTitle='Página no encontrada';
        }
    } catch (Throwable $exception) {
        verdelia_log_error($exception,'admin-page'); http_response_code(503); $route='database-unavailable'; $view='database-unavailable.php'; $pageTitle='Administración temporalmente no disponible';
    }
} else {
    http_response_code(404); $route='not-found'; $view='not-found.php'; $pageTitle='Página no encontrada';
}

$viewPath = __DIR__ . '/../app/views/pages/' . $view;
require dirname(__DIR__) . '/app/views/layout.php';
