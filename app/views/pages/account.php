<?php
declare(strict_types=1);
$user = currentUser();
$errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
?>
<section class="simple-page-head page-gutter"><p class="eyebrow">Mi cuenta · espacio privado</p><h1>Hola,<br><em><?= h($user['name'] ?? '') ?>.</em></h1><p>Consulta tus datos y mantén segura tu cuenta.</p></section>
<section class="account-layout page-gutter">
  <article class="account-card"><p class="eyebrow">Perfil</p><h2>Tus datos</h2>
    <?php if (isset($flash['message']) && is_string($flash['message'])): ?><p class="auth-message <?= ($flash['type'] ?? '') === 'success' ? 'is-success' : 'is-error' ?>" role="status"><?= h($flash['message']) ?></p><?php endif; ?>
    <dl class="account-details"><div><dt>Nombre</dt><dd><?= h($user['name'] ?? '') ?></dd></div><div><dt>Correo electrónico</dt><dd><?= h($user['email'] ?? '') ?></dd></div><div><dt>Tipo de cuenta</dt><dd><?= ($user['role'] ?? '') === 'ADMIN' ? 'Administrador' : 'Cliente' ?></dd></div><div><dt>Fecha de registro</dt><dd><?php try { echo h((new DateTimeImmutable((string)$user['created_at']))->format('d/m/Y')); } catch (Throwable) { echo '—'; } ?></dd></div></dl>
    <form class="form-stack account-form" method="post" action="/cuenta">
      <?= verdelia_csrf_field() ?><input type="hidden" name="action" value="update_name">
      <label class="field-label" for="account-name">Editar nombre<input class="text-input" id="account-name" name="name" maxlength="120" minlength="2" required value="<?= h(is_string($flash['old']['name'] ?? null) ? $flash['old']['name'] : ($user['name'] ?? '')) ?>" aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>"><?php if (isset($errors['name'])): ?><span class="field-error"><?= h($errors['name']) ?></span><?php endif; ?></label>
      <button class="button button-primary" type="submit">Guardar nombre</button>
    </form>
  </article>
  <article class="account-card"><p class="eyebrow">Seguridad</p><h2>Cambiar contraseña</h2><p>Confirma tu contraseña actual antes de elegir una nueva.</p>
    <form class="form-stack account-form" method="post" action="/cuenta">
      <?= verdelia_csrf_field() ?><input type="hidden" name="action" value="change_password">
      <label class="field-label" for="current-password">Contraseña actual<input class="text-input" id="current-password" name="current_password" type="password" autocomplete="current-password" required aria-invalid="<?= isset($errors['current_password']) ? 'true' : 'false' ?>"><?php if (isset($errors['current_password'])): ?><span class="field-error"><?= h($errors['current_password']) ?></span><?php endif; ?></label>
      <label class="field-label" for="new-password">Nueva contraseña<input class="text-input" id="new-password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>"><?php if (isset($errors['password'])): ?><span class="field-error"><?= h($errors['password']) ?></span><?php endif; ?></label>
      <label class="field-label" for="new-password-confirmation">Confirma la nueva contraseña<input class="text-input" id="new-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" required aria-invalid="<?= isset($errors['confirmation']) ? 'true' : 'false' ?>"><?php if (isset($errors['confirmation'])): ?><span class="field-error"><?= h($errors['confirmation']) ?></span><?php endif; ?></label>
      <button class="button button-primary" type="submit">Actualizar contraseña</button>
    </form>
  </article>
</section>
<section class="order-history page-gutter"><div class="admin-panel-heading"><div><p class="eyebrow">Actividad de compra</p><h2>Mis pedidos</h2></div><a class="text-link" href="/cuenta/pedidos">Ver historial completo <?= icon('arrow',15) ?></a></div>
  <?php $recentOrders = is_array($accountOrdersResult['items'] ?? null) ? $accountOrdersResult['items'] : []; ?>
  <?php if ($recentOrders === []): ?><p class="order-history-empty">Aún no hay pedidos en esta cuenta. <a href="/tienda">Explorar la colección</a></p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Pedido</th><th>Fecha</th><th>Estado</th><th>Pago</th><th>Total</th><th></th></tr></thead><tbody><?php foreach ($recentOrders as $order): ?><tr><td><?= h($order['order_number']) ?></td><td><?= h($order['created_at']) ?></td><td><?= h(match ($order['status']) {'PAID'=>'Pagado','FULFILLED'=>'Entregado','CANCELLED'=>'Cancelado',default=>'Pendiente'}) ?></td><td><?= h(match ($order['payment_status']) {'APPROVED'=>'Aprobado','REJECTED'=>'Rechazado','CANCELLED'=>'Cancelado','PENDING'=>'Pendiente',default=>'Sin intentos'}) ?></td><td><?= h(money((int)$order['total_cents'])) ?></td><td><a class="text-link" href="/pedido/<?= rawurlencode((string)$order['order_number']) ?><?= in_array($order['status'],['PAID','FULFILLED'],true) ? '/recibo' : '' ?>">Consultar</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
