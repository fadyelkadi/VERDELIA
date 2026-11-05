<?php declare(strict_types=1); ?>
<article class="product-card" data-product-card data-category="<?= h($card['category']) ?>" data-name="<?= h(lowercase($card['name'])) ?>" data-care="<?= h($card['care']) ?>" data-light="<?= h(lowercase($card['light'])) ?>" data-price="<?= (int)$card['price'] ?>">
  <a class="product-media tone-<?= h($card['category']) ?>" href="/producto/<?= h($card['slug']) ?>" aria-label="Ver <?= h($card['name']) ?>">
    <span class="product-category"><?= h($card['category_name']) ?></span>
    <img src="/assets/images/products/<?= h($card['image']) ?>" alt="<?= h($card['image_alt']) ?>" loading="lazy" width="720" height="720">
    <span class="media-arrow" aria-hidden="true"><?= icon('arrow', 18) ?></span>
  </a>
  <div class="product-info">
    <div><h3><a href="/producto/<?= h($card['slug']) ?>"><?= h($card['name']) ?></a></h3><p class="product-meta"><?= h($card['care'] !== '' ? 'Cuidado ' . strtolower($card['care']) : 'Referencia ' . $card['sku']) ?></p></div>
    <span class="product-price"><?= h(money($card['price'])) ?></span>
  </div>
  <div class="product-card-foot"><span class="stock-note <?= (int)$card['stock']<1?'is-out':((int)$card['stock']<=15?'is-low':'') ?>"><span class="stock-dot"></span><?php if ((int)$card['stock']<1): ?>Agotado<?php elseif ((int)$card['stock']<=15): ?>Pocas unidades · <?= (int)$card['stock'] ?><?php else: ?>Disponible · <?= (int)$card['stock'] ?><?php endif; ?></span><a class="product-open" href="/producto/<?= h($card['slug']) ?>" aria-label="Ver detalles de <?= h($card['name']) ?>">Ver detalles</a></div>
</article>
