<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/database.php';
require dirname(__DIR__) . '/app/CatalogRepository.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('FALLO: ' . $message);
    echo "OK: $message\n";
}

try {
    $pdo = verdelia_db();
    $repo = new CatalogRepository($pdo);
    $tableCount = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
    check($tableCount === 12, 'el esquema incluye las 11 tablas de tienda y la auditoría administrativa');
    check((int)$pdo->query("SELECT COUNT(*) FROM schema_versions WHERE version='admin_v1'")->fetchColumn()===1, 'la migración administrativa está aplicada');
    $counts = [];
    foreach (['categories','products','product_images','users','orders','order_items','payments'] as $table) {
        $counts[$table] = (int)$pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }
    check($counts['categories'] >= 4, 'hay varias categorías activas');
    check($counts['products'] >= 12, 'hay al menos 12 productos');
    check($counts['product_images'] >= 12, 'cada producto de muestra tiene imagen local vinculada');
    check($counts['users'] >= 2 && $counts['orders'] >= 96 && $counts['order_items'] >= 96 && $counts['payments'] >= 96, 'los usuarios y pedidos históricos se conservan');
    $lowStock=(int)$pdo->query('SELECT COUNT(*) FROM products WHERE active=1 AND stock BETWEEN 1 AND 15')->fetchColumn();
    check($lowStock >= 2, 'hay productos de stock bajo para probar sus indicadores');
    $category = $repo->categoryBySlug('interior');
    check($category !== null && $category['name'] !== '', 'las categorías se leen desde MySQL');

    $baseFilters = ['category'=>null,'query'=>'','min_price'=>null,'max_price'=>null,'in_stock'=>false,'care'=>[],'light'=>[],'sort'=>'featured'];
    $first = $repo->listing($baseFilters, 1, 8);
    $second = $repo->listing($baseFilters, 2, 8);
    check(count($first['items']) === 8 && count($second['items']) >= 4, 'la paginación divide los resultados');
    check(count(array_unique(array_merge(array_column($first['items'],'id'),array_column($second['items'],'id')))) === 12, 'las páginas no duplican productos');
    $filtered = $repo->listing(array_merge($baseFilters,['category'=>'suculentas','max_price'=>1500,'in_stock'=>true]),1,8);
    check($filtered['total'] >= 2, 'categoría, precio y disponibilidad filtran en SQL');
    $searched = $repo->listing(array_merge($baseFilters,['query'=>'Monstera']),1,8);
    check($searched['total'] === 1 && $searched['items'][0]['sku'] === 'PLA-006', 'la búsqueda por palabra devuelve el producto esperado');
    $injection = $repo->listing(array_merge($baseFilters,['query'=>"%' OR 1=1 --"]),1,8);
    check($injection['total'] === 0, 'el texto de búsqueda no altera la consulta SQL');
    $ascending = $repo->listing(array_merge($baseFilters,['sort'=>'price-asc']),1,12);
    $descending = $repo->listing(array_merge($baseFilters,['sort'=>'price-desc']),1,12);
    check((int)$ascending['items'][0]['price_cents'] <= (int)$ascending['items'][1]['price_cents'], 'el orden ascendente usa precios de MySQL');
    check((int)$descending['items'][0]['price_cents'] >= (int)$descending['items'][1]['price_cents'], 'el orden descendente usa precios de MySQL');
    $monstera = $repo->bySlug('monstera-deliciosa');
    check($monstera !== null && $monstera['price_cents'] === 4200 && (int)$monstera['stock'] === 14, 'la ficha carga precio y existencia reales');
    check($repo->bySlug('producto-que-no-existe') === null, 'una ficha inexistente se identifica correctamente');
    $stockBefore=(int)$pdo->query('SELECT SUM(stock) FROM products')->fetchColumn();
    $cartItems = $repo->bySkus(['PLA-006','SKU-INVALIDO']);
    check(count($cartItems) === 1 && $cartItems[0]['sku'] === 'PLA-006', 'la consulta de vista previa valida SKU y existencia');
    $stockAfter=(int)$pdo->query('SELECT SUM(stock) FROM products')->fetchColumn();
    check($stockBefore===$stockAfter, 'consultar la vista previa no descuenta inventario');
    foreach ($repo->listing($baseFilters,1,12)['items'] as $item) {
        $image = dirname(__DIR__) . '/public/assets/images/products/' . $item['image'];
        check(is_file($image), 'la imagen local existe para ' . $item['sku']);
    }
    echo "RESUMEN: " . json_encode($counts, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
