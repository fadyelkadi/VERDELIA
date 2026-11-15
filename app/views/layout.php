<?php
declare(strict_types=1);
$storeData=json_encode(array_map(static fn(array $p): array => ['sku'=>$p['sku'],'slug'=>$p['slug'],'name'=>$p['name'],'price'=>(int)$p['price_cents'],'stock'=>(int)$p['stock'],'image'=>$p['image'],'category_name'=>$p['category_name']], $products), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE);
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f8f6f0">
  <meta name="description" content="Descubre la colección de plantas, macetas y accesorios de VERDELIA. Encuentra opciones para la luz y el espacio de tu casa.">
  <meta name="csrf-token" content="<?= h(verdelia_csrf_token()) ?>">
  <title><?= h($pageTitle) ?> · VERDELIA</title>
  <link rel="icon" href="/assets/images/brand/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/site.css">
  <?php if ($route !== 'admin' && !isset($adminSection) && $route !== 'order' && empty($isReceipt)): ?>
    <link rel="stylesheet" href="/assets/css/chat.css">
  <?php endif; ?>
  <script defer src="/assets/js/site.js"></script>
</head>
<body data-page="<?= h($route) ?>">
  <a class="skip-link" href="#contenido">Saltar al contenido</a>
  <?php require __DIR__ . '/partials/header.php'; ?>
  <main id="contenido" tabindex="-1">
    <?php require $viewPath; ?>
  </main>
  <?php require __DIR__ . '/partials/footer.php'; ?>
  <div class="toast" role="status" aria-live="polite" data-toast></div>
  <template id="verdelia-products"><?= h($storeData ?: '[]') ?></template>
  <?php if ($route !== 'admin' && !isset($adminSection) && $route !== 'order' && empty($isReceipt)): ?>
    <?php require __DIR__ . '/partials/chat.php'; ?>
    <script defer src="/assets/js/chat.js"></script>
  <?php endif; ?>
</body>
</html>
