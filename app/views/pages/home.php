<?php
declare(strict_types=1);
$featured=$products;
?>
<section class="home-hero page-gutter">
  <div class="hero-copy">
    <p class="eyebrow"><span class="eyebrow-line"></span> Un pequeño estudio botánico digital</p>
    <h1>Haz espacio<br>para lo <em>vivo.</em></h1>
    <p class="hero-description">Plantas y objetos elegidos para que tu hogar encuentre su propio ritmo natural.</p>
    <div class="hero-actions"><a class="button button-primary" href="/tienda">Explorar plantas <?= icon('arrow', 17) ?></a><a class="text-link hero-secondary" href="/nosotros">Conoce Verdelia</a></div>
    <div class="hero-footnote"><span class="hero-footnote-mark">✳</span><span>Pequeños gestos, espacios con vida.</span></div>
  </div>
  <div class="hero-visual">
    <span class="hero-orbit orbit-one"></span><span class="hero-orbit orbit-two"></span>
    <div class="hero-caption"><span>01 / 04</span><span>Follaje para cada rincón</span></div>
    <img src="/assets/images/products/PLA-006-monstera.png" alt="Monstera deliciosa en maceta, imagen botánica ilustrativa generada con IA" width="900" height="900" fetchpriority="high">
    <div class="hero-stamp"><span>CRECE A TU<br>MANERA</span><?= icon('leaf', 22) ?></div>
  </div>
</section>

<section class="intro-strip page-gutter" aria-label="Presentación de marca">
  <span class="intro-symbol"><?= icon('leaf', 22) ?></span>
  <p>Una colección serena de <strong>plantas, macetas y pequeños rituales</strong> para empezar a crear tu rincón verde.</p>
  <a class="text-link" href="/nosotros">Nuestra mirada <?= icon('arrow', 15) ?></a>
</section>

<section class="section-block category-section page-gutter">
  <div class="section-heading section-heading-split"><div><p class="eyebrow">Encuentra tu verde</p><h2>Una buena compañía<br>para cada espacio.</h2></div><a class="text-link" href="/tienda">Ver toda la colección <?= icon('arrow', 16) ?></a></div>
  <div class="category-grid">
    <?php $categoryArt=['interior'=>['PLA-004-palma-areca.png','Follaje suave'],'suculentas'=>['PLA-005-echeveria.png','Pequeñas y luminosas'],'macetas'=>['MAC-003-maceta-oliva.png','Texturas que acompañan'],'accesorios'=>['ACC-001-regadera-mini.png','Pequeños rituales']]; foreach ($categories as $index=>$tile): $art=$categoryArt[$tile['slug']]??['PLA-006-monstera.png','Una colección botánica']; ?>
      <a class="category-tile <?= $index===0?'category-tile-tall':($index===1?'category-tile-succulent':'category-tile-pot') ?>" href="/categoria/<?= h($tile['slug']) ?>"><span class="category-index"><?= h(str_pad((string)($index+1),2,'0',STR_PAD_LEFT)) ?></span><div class="category-art"><img src="/assets/images/products/<?= h($art[0]) ?>" alt="<?= h($tile['name']) ?>, imagen ilustrativa" loading="lazy" width="520" height="620"></div><div class="category-label"><span><small><?= h($art[1]) ?></small><strong><?= h($tile['name']) ?></strong></span><?= icon('arrow', 19) ?></div></a>
    <?php endforeach; ?>
  </div>
</section>

<section class="section-block featured-section page-gutter">
  <div class="section-heading section-heading-split"><div><p class="eyebrow">Para empezar con calma</p><h2>Favoritas de la casa.</h2></div><p class="section-aside">Una selección botánica de demostración,<br>pensada para distintos niveles de luz.</p></div>
  <div class="product-grid product-grid-featured">
    <?php foreach ($featured as $card) require __DIR__ . '/../partials/product-card.php'; ?>
  </div>
</section>

<section class="care-feature page-gutter">
  <div class="care-art"><span class="care-sun"></span><img src="/assets/images/products/PLA-008-ficus-lyrata.png" alt="Ficus lyrata ilustrativo en maceta clara" loading="lazy" width="640" height="700"></div>
  <div class="care-copy"><p class="eyebrow">Un poco de cuidado</p><h2>Deja que cada hoja<br>encuentre su <em>luz.</em></h2><p>Elegir un rincón luminoso, mirar la tierra y regar con calma: a veces, cuidar una planta empieza con gestos muy sencillos.</p><a class="button button-outline" href="/preguntas-frecuentes">Ideas para cuidar <?= icon('arrow', 17) ?></a><span class="care-note">Contenido orientativo para esta demostración.</span></div>
</section>

<section class="story-band page-gutter">
  <div class="story-index">VERDELIA<br><span>EST. 2026 · FICCIÓN</span></div>
  <blockquote>“Creemos en los espacios que crecen contigo, hoja a hoja.”</blockquote>
  <a class="text-link" href="/nosotros">Conoce nuestra historia <?= icon('arrow', 16) ?></a>
</section>

<section class="closing-cta page-gutter"><p class="eyebrow">Tu próximo rincón verde</p><h2>Empieza por algo<br><em>que te haga bien.</em></h2><a class="button button-light" href="/tienda">Ver la colección <?= icon('arrow', 17) ?></a><span>Productos e importes ficticios · sin ventas reales</span></section>
