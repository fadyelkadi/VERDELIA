<?php
declare(strict_types=1);

$navUser = currentUser();
$navLinks = [
    '/' => 'Inicio',
    '/tienda' => 'Tienda',
    '/nosotros' => 'Nuestra historia',
    '/contacto' => 'Contacto',
];
if ($navUser === null) {
    $navLinks += ['/login'=>'Iniciar sesión', '/registro'=>'Registrarse'];
} else {
    $navLinks['/cuenta'] = 'Mi perfil';
    if (isAdmin()) $navLinks['/admin'] = 'Administración';
}
?>
<header class="site-header">
  <div class="header-inner">
    <button class="icon-button mobile-menu-button" type="button" aria-label="Abrir menú" aria-controls="site-navigation" aria-expanded="false" data-menu-toggle><?= icon('menu', 22) ?></button>
    <a class="brand" href="/" aria-label="VERDELIA, inicio"><img src="/assets/images/brand/logo.svg" width="230" height="52" alt="VERDELIA"></a>
    <nav class="main-nav" id="site-navigation" aria-label="Navegación principal">
      <?php foreach ($navLinks as $href=>$label):
        $current = $href === $routePath;
        $section = ($href === '/tienda' && in_array($route, ['store','product'], true))
          || ($href === '/admin' && $route === 'admin')
          || ($href === '/cuenta' && in_array($route, ['account','account-orders'], true));
      ?>
        <a href="<?= h($href) ?>"<?= $current || $section ? ' class="is-active"' : '' ?><?= $current ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
      <?php endforeach; ?>
      <?php if ($navUser !== null): ?>
        <form class="nav-logout-form" method="post" action="/logout"><?= verdelia_csrf_field() ?><button class="nav-logout" type="submit">Cerrar sesión</button></form>
      <?php endif; ?>
    </nav>
    <div class="header-actions">
      <a class="icon-button header-search" href="/tienda#catalog-search" aria-label="Buscar productos"><?= icon('search', 21) ?></a>
      <a class="icon-button header-account" href="<?= $navUser === null ? '/login' : '/cuenta' ?>" aria-label="<?= $navUser === null ? 'Iniciar sesión' : 'Mi perfil' ?>"><?= icon('user', 21) ?></a>
      <a class="icon-button cart-link" href="/carrito" aria-label="Carrito de compras" data-cart-link>
        <?= icon('bag', 21) ?><span class="cart-count" data-cart-count aria-hidden="true">0</span>
      </a>
    </div>
  </div>
</header>
