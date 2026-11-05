<?php declare(strict_types=1); $navUser=currentUser(); ?>
<header class="site-header">
  <div class="header-inner">
    <button class="icon-button mobile-menu-button" type="button" aria-label="Abrir menú" aria-expanded="false" data-menu-toggle><?= icon('menu', 22) ?></button>
    <a class="brand" href="/" aria-label="VERDELIA, inicio"><img src="/assets/images/brand/logo.svg" width="230" height="52" alt="VERDELIA"></a>
    <nav class="main-nav" aria-label="Navegación principal" data-main-nav>
      <a class="<?= $activeNav==='inicio'?'is-active':'' ?>" href="/">Inicio</a>
      <a class="<?= $activeNav==='tienda'?'is-active':'' ?>" href="/tienda">Tienda</a>
      <a class="<?= $activeNav==='nosotros'?'is-active':'' ?>" href="/nosotros">Nuestra historia</a>
      <a href="/contacto">Contacto</a>
      <?php if ($navUser === null): ?>
        <a href="/login">Iniciar sesión</a>
        <a href="/registro">Registrarse</a>
      <?php else: ?>
        <a href="/cuenta">Mi perfil</a>
        <?php if (($navUser['role'] ?? '') === 'ADMIN'): ?><a href="/admin">Panel admin</a><?php endif; ?>
        <form class="nav-logout-form" method="post" action="/logout"><?= verdelia_csrf_field() ?><button class="nav-logout" type="submit">Cerrar sesión</button></form>
      <?php endif; ?>
    </nav>
    <div class="header-actions">
      <a class="icon-button header-search" href="/tienda" aria-label="Buscar productos"><?= icon('search', 21) ?></a>
      <a class="icon-button header-account" href="<?= $navUser === null ? '/login' : '/cuenta' ?>" aria-label="<?= $navUser === null ? 'Iniciar sesión' : 'Mi perfil' ?>"><?= icon('user', 21) ?></a>
      <a class="icon-button cart-link" href="/carrito" aria-label="Carrito de compras">
        <?= icon('bag', 21) ?><span class="cart-count" data-cart-count>0</span>
      </a>
    </div>
  </div>
</header>
