<?php
declare(strict_types=1);
$currentCategory = $filters['category'];
$maxPrice = $filters['max_price'] ?? 6000;
$sortLabels = ['featured'=>'Selección VERDELIA','price-asc'=>'Precio: menor a mayor','price-desc'=>'Precio: mayor a menor','name'=>'Nombre','recent'=>'Más recientes'];
?>
<section class="catalog-hero page-gutter">
  <div><p class="eyebrow">La colección VERDELIA</p><h1><?= h($categoryTitle ?? 'Encuentra tu próxima planta.') ?></h1></div>
  <p>Plantas y objetos que nos gustan por sus formas, texturas y maneras distintas de vivir en casa. <span>Catálogo e inventario de demostración académica.</span></p>
</section>
<section class="catalog-wrap page-gutter" aria-label="Catálogo de productos">
  <div class="catalog-toolbar"><span><strong><?= (int)$catalogResult['total'] ?> piezas</strong> en la colección · Página <?= (int)$catalogResult['page'] ?> de <?= (int)$catalogResult['total_pages'] ?></span><label class="sort-control">Ordenar por <select name="sort" data-sort form="catalog-filters"><?php foreach($sortLabels as $value=>$label): ?><option value="<?= h($value) ?>" <?= $filters['sort']===$value?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label></div>
  <div class="catalog-layout">
    <form class="filter-panel" id="catalog-filters" method="get" action="/tienda">
      <p class="eyebrow">Afina tu búsqueda</p>
      <label class="field-label" for="catalog-search">Buscar por nombre o palabra clave</label>
      <input id="catalog-search" class="text-input" type="search" name="q" maxlength="80" placeholder="Ej. monstera" value="<?= h($filters['query']) ?>">
      <fieldset><legend>Categoría</legend>
        <label class="check-row"><input type="radio" name="category" value="" <?= $currentCategory===null?'checked':'' ?>><span>Todo</span></label>
        <?php foreach($categories as $option): ?><label class="check-row"><input type="radio" name="category" value="<?= h($option['slug']) ?>" <?= $currentCategory===$option['slug']?'checked':'' ?>><span><?= h($option['name']) ?></span></label><?php endforeach; ?>
      </fieldset>
      <fieldset><legend>Disponibilidad</legend><label class="check-row"><input type="checkbox" name="in_stock" value="1" <?= $filters['in_stock']?'checked':'' ?>><span>Solo disponibles</span></label></fieldset>
      <fieldset><legend>Cuidados</legend><label class="check-row"><input type="checkbox" name="care[]" value="Fácil" <?= in_array('Fácil',$filters['care'],true)?'checked':'' ?>><span>Fáciles</span></label><label class="check-row"><input type="checkbox" name="care[]" value="Media" <?= in_array('Media',$filters['care'],true)?'checked':'' ?>><span>Intermedios</span></label></fieldset>
      <fieldset><legend>Luz</legend><label class="check-row"><input type="checkbox" name="light[]" value="indirecta" <?= in_array('indirecta',$filters['light'],true)?'checked':'' ?>><span>Indirecta</span></label><label class="check-row"><input type="checkbox" name="light[]" value="brillante" <?= in_array('brillante',$filters['light'],true)?'checked':'' ?>><span>Brillante</span></label><label class="check-row"><input type="checkbox" name="light[]" value="directa" <?= in_array('directa',$filters['light'],true)?'checked':'' ?>><span>Directa</span></label></fieldset>
      <fieldset class="price-filter"><legend>Precio máximo</legend><label class="range-label" for="price-limit"><span>Hasta</span><strong data-price-label><?= h(money((int)$maxPrice)) ?></strong></label><input id="price-limit" type="range" min="1000" max="6000" step="100" value="<?= min(6000,max(1000,(int)$maxPrice)) ?>" name="max_price" data-filter-price aria-label="Precio máximo"></fieldset>
      <button class="button button-primary button-wide" type="submit">Aplicar filtros</button>
      <a class="filter-reset" href="/tienda">Limpiar filtros</a>
    </form>
    <div class="catalog-products">
      <?php if ($catalogResult['items']===[]): ?><div class="empty-filter"><h2>No encontramos productos</h2><p>Prueba otra búsqueda o limpia los filtros.</p><a class="text-link" href="/tienda">Ver toda la colección</a></div>
      <?php else: ?><div class="product-grid" data-product-grid><?php foreach ($products as $card) require __DIR__ . '/../partials/product-card.php'; ?></div><?php endif; ?>
      <?php if ((int)$catalogResult['total_pages']>1): ?><nav class="catalog-pagination" aria-label="Paginación del catálogo">
        <?php if ((int)$catalogResult['page']>1): ?><a class="button button-outline" href="<?= h(query_url(['page'=>(int)$catalogResult['page']-1])) ?>">Anterior</a><?php endif; ?>
        <span>Página <?= (int)$catalogResult['page'] ?> / <?= (int)$catalogResult['total_pages'] ?></span>
        <?php if ((int)$catalogResult['page']<(int)$catalogResult['total_pages']): ?><a class="button button-outline" href="<?= h(query_url(['page'=>(int)$catalogResult['page']+1])) ?>">Siguiente</a><?php endif; ?>
      </nav><?php endif; ?>
    </div>
  </div>
</section>
