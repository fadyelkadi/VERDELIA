<?php
declare(strict_types=1);
$guide = ProductGuide::forProduct($product);
$scientificName = ProductGuide::scientificName($product, $guide);
$guideSections = [
    'specifications'=>'Material y medidas',
    'light'=>'Dónde colocarla',
    'watering'=>'Riego',
    'substrate'=>'Sustrato y maceta',
    'tip'=>'Consejo práctico',
];
?>
<nav class="breadcrumbs page-gutter" aria-label="Ruta de navegación"><a href="/">Inicio</a><span>/</span><a href="/categoria/<?= h($product['category']) ?>"><?= h($product['category_name']) ?></a><span>/</span><span aria-current="page"><?= h($product['name']) ?></span></nav>
<section class="product-detail page-gutter">
  <div class="detail-media tone-<?= h($product['category']) ?>"><span class="product-category"><?= h($product['category_name']) ?></span><img src="/assets/images/products/<?= h($product['image']) ?>" alt="<?= h($product['image_alt']) ?>" width="900" height="900" fetchpriority="high"></div>
  <div class="detail-info"><p class="eyebrow">Pieza <?= h($product['sku']) ?> · imagen ilustrativa</p><h1><?= h($product['name']) ?></h1>
    <?php if ($scientificName !== '' || isset($guide['common'])): ?>
      <p class="detail-description"><?php if ($scientificName !== ''): ?><i><?= h($scientificName) ?></i><?php endif; ?><?php if (isset($guide['common'])): ?><?= $scientificName !== '' ? ' · ' : '' ?><?= h($guide['common']) ?><?php endif; ?></p>
    <?php endif; ?>
    <p class="detail-price"><?= h(money($product['price'])) ?></p><p class="detail-description"><?= h($product['description']) ?></p>
    <div class="detail-stock <?= (int)$product['stock']<1?'is-out':((int)$product['stock']<=15?'is-low':'') ?>"><span class="stock-dot"></span><?php if ((int)$product['stock']<1): ?>Agotado<?php elseif ((int)$product['stock']<=15): ?>Quedan pocas unidades · <?= (int)$product['stock'] ?> disponibles<?php else: ?><?= (int)$product['stock'] ?> unidades disponibles<?php endif; ?></div>
    <div class="detail-size"><span>Referencia</span><strong><?= h($product['sku']) ?></strong></div>
    <?php if ($product['care'] !== ''): ?><div class="care-tags"><span><?= icon('sun', 18) ?> <?= h($product['light']) ?></span><span><?= icon('drop', 18) ?> Cuidado <?= h(strtolower($product['care'])) ?></span></div><?php endif; ?>
    <div class="purchase-row"><label class="field-label" for="quantity">Cantidad</label><div class="quantity-control"><button type="button" aria-label="Restar una unidad" data-quantity-step="-1">−</button><input id="quantity" type="number" min="1" max="<?= max(1,(int)$product['stock']) ?>" value="1" inputmode="numeric" <?= (int)$product['stock']<1?'disabled':'' ?> data-product-quantity><button type="button" aria-label="Sumar una unidad" data-quantity-step="1" <?= (int)$product['stock']<1?'disabled':'' ?>>+</button></div></div>
    <button class="button button-primary button-wide" type="button" data-add-to-cart="<?= h($product['sku']) ?>" data-quantity-source="product" <?= (int)$product['stock']<1?'disabled':'' ?>><?= (int)$product['stock']<1?'Producto agotado':'Añadir al carrito' ?> <?= icon('arrow', 17) ?></button>
    <p class="product-stock-note">Añadir al carrito no reserva existencias.</p>
    <?php if ($guide !== []): ?>
      <section class="detail-accordion" aria-labelledby="product-guide-title">
        <h2 id="product-guide-title"><?= isset($guide['watering']) ? 'Guía de cuidados' : 'Detalles y uso' ?></h2>
        <p><?= h($guide['overview']) ?></p>
        <?php foreach ($guideSections as $key => $label): ?>
          <?php if (isset($guide[$key])): ?><details<?= in_array($key, ['watering','specifications'], true) ? ' open' : '' ?>><summary><?= h($label) ?></summary><p><?= h($guide[$key]) ?></p></details><?php endif; ?>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
    <div class="detail-accordion"><details><summary>Sobre las imágenes</summary><p><?= h(StoreInformation::IMAGES) ?></p></details></div>
  </div>
</section>
<?php if ($related !== []): ?><section class="section-block related-section page-gutter"><div class="section-heading section-heading-split"><div><p class="eyebrow">Más de esta familia</p><h2>También podría gustarte.</h2></div><a class="text-link" href="/categoria/<?= h($product['category']) ?>">Ver categoría <?= icon('arrow', 16) ?></a></div><div class="product-grid"><?php foreach($related as $card) require __DIR__ . '/../partials/product-card.php'; ?></div></section><?php endif; ?>
