<?php
declare(strict_types=1);
$user = currentUser();
$errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
$old = is_array($flash['old'] ?? null) ? $flash['old'] : [];
$checkoutItems = is_array($checkoutCart['items'] ?? null) ? $checkoutCart['items'] : [];
$checkoutValid = !empty($checkoutCart['valid']) && $checkoutItems !== [];
$deliveryValues = [
    'name'=>is_string($old['name'] ?? null) ? $old['name'] : '',
    'email'=>is_string($old['email'] ?? null) ? $old['email'] : '',
    'phone'=>is_string($old['phone'] ?? null) ? $old['phone'] : '',
    'address'=>is_string($old['address'] ?? null) ? $old['address'] : '',
    'city'=>is_string($old['city'] ?? null) ? $old['city'] : '',
    'state'=>is_string($old['state'] ?? null) ? $old['state'] : '',
    'postal_code'=>is_string($old['postal_code'] ?? null) ? $old['postal_code'] : '',
    'note'=>is_string($old['note'] ?? null) ? $old['note'] : '',
];
$fieldError = static function (string $key) use ($errors): void {
    if (isset($errors[$key]) && is_string($errors[$key])) echo '<span class="field-error">' . h($errors[$key]) . '</span>';
};
?>
<section class="simple-page-head page-gutter"><p class="eyebrow">Finalizar pedido</p><h1>Un último paso<br><em>con calma.</em></h1><p>Esta tienda es una demostración: el pago y el pedido son simulados. No se cobra, prepara ni envía ningún producto. Usa datos ficticios.</p></section>
<section class="checkout-layout page-gutter">
  <?php if (isset($flash['message']) && is_string($flash['message'])): ?><p class="auth-message <?= ($flash['type'] ?? '') === 'success' ? 'is-success' : 'is-error' ?> checkout-message" role="alert"><?= h($flash['message']) ?></p><?php endif; ?>
  <?php if ($checkoutItems === []): ?>
    <div class="empty-cart checkout-empty"><span class="empty-cart-mark">✳</span><h2>Tu carrito está vacío.</h2><p>Añade algún producto antes de pasar al checkout.</p><a class="button button-primary" href="/tienda">Explorar plantas <?= icon('arrow',16) ?></a></div>
  <?php else: ?>
    <form class="checkout-form" method="post" action="/checkout" data-checkout-form>
      <?= verdelia_csrf_field() ?>
      <input type="hidden" name="order_key" value="<?= h($checkoutKeys['order_key'] ?? '') ?>">
      <input type="hidden" name="attempt_key" value="<?= h($checkoutKeys['attempt_key'] ?? '') ?>">
      <input type="hidden" name="expected_total_cents" value="<?= (int)($checkoutCart['total_cents'] ?? 0) ?>">
      <div class="form-section-title"><span>01</span><div><h2>Datos ficticios del pedido</h2><p>No ingreses una dirección, teléfono ni correo reales. VERDELIA no realiza entregas.</p></div></div>
      <div class="form-grid">
        <label class="field-label" for="checkout-name">Nombre ficticio<input class="text-input" id="checkout-name" name="name" autocomplete="off" maxlength="120" required placeholder="Cliente de prueba" value="<?= h($deliveryValues['name']) ?>" aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>"><?php $fieldError('name'); ?></label>
        <label class="field-label" for="checkout-email">Correo ficticio<input class="text-input" id="checkout-email" name="email" type="email" autocomplete="off" maxlength="190" required placeholder="cliente@example.test" value="<?= h($deliveryValues['email']) ?>" aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>"><?php $fieldError('email'); ?></label>
        <label class="field-label" for="checkout-phone">Teléfono ficticio<input class="text-input" id="checkout-phone" name="phone" type="tel" autocomplete="off" maxlength="40" placeholder="0000-0000000" required value="<?= h($deliveryValues['phone']) ?>" aria-invalid="<?= isset($errors['phone']) ? 'true' : 'false' ?>"><?php $fieldError('phone'); ?></label>
        <label class="field-label full-field" for="checkout-address">Dirección de ejemplo<input class="text-input" id="checkout-address" name="address" autocomplete="off" maxlength="180" required placeholder="Calle Ficticia 123" value="<?= h($deliveryValues['address']) ?>" aria-invalid="<?= isset($errors['address']) ? 'true' : 'false' ?>"><?php $fieldError('address'); ?></label>
        <label class="field-label" for="checkout-city">Ciudad de ejemplo<input class="text-input" id="checkout-city" name="city" autocomplete="off" maxlength="100" required placeholder="Ciudad de prueba" value="<?= h($deliveryValues['city']) ?>" aria-invalid="<?= isset($errors['city']) ? 'true' : 'false' ?>"><?php $fieldError('city'); ?></label>
        <label class="field-label" for="checkout-state">Estado ficticio<input class="text-input" id="checkout-state" name="state" autocomplete="off" maxlength="100" required placeholder="Estado de prueba" value="<?= h($deliveryValues['state']) ?>" aria-invalid="<?= isset($errors['state']) ? 'true' : 'false' ?>"><?php $fieldError('state'); ?></label>
        <label class="field-label" for="checkout-postal">Código postal <span>(opcional)</span><input class="text-input" id="checkout-postal" name="postal_code" autocomplete="postal-code" maxlength="20" value="<?= h($deliveryValues['postal_code']) ?>" aria-invalid="<?= isset($errors['postal_code']) ? 'true' : 'false' ?>"><?php $fieldError('postal_code'); ?></label>
        <label class="field-label" for="checkout-note">Nota del pedido simulado <span>(opcional)</span><input class="text-input" id="checkout-note" name="note" maxlength="255" placeholder="Nota para esta demostración" value="<?= h($deliveryValues['note']) ?>" aria-invalid="<?= isset($errors['note']) ? 'true' : 'false' ?>"><?php $fieldError('note'); ?></label>
      </div>
      <?php if (!$checkoutValid): ?><p class="auth-message is-error" role="alert">Algún producto excede el stock disponible o ya no está activo. <a href="/carrito">Actualiza tu carrito</a> antes de comprar.</p><?php endif; ?>
      <div class="form-section-title payment-title"><span>02</span><div><h2>Pago simulado</h2><p>Elige si quieres simular una aprobación, un rechazo o cancelar el pedido.</p></div></div>
      <div class="simulator-notice"><strong>Simulador VERDELIA</strong><span>La aprobación registra el pedido y descuenta existencias. El rechazo no lo hace.</span></div>
      <div class="checkout-actions">
        <button class="button button-primary button-wide" type="submit" name="payment_outcome" value="approve" <?= $checkoutValid ? '' : 'disabled' ?>>Simular pago aprobado <?= icon('arrow',17) ?></button>
        <button class="button button-outline button-wide" type="submit" name="payment_outcome" value="reject" <?= $checkoutValid ? '' : 'disabled' ?>>Simular pago rechazado</button>
        <button class="checkout-cancel" type="submit" name="payment_outcome" value="cancel" <?= $checkoutValid ? '' : 'disabled' ?>>Cancelar pedido</button>
      </div>
      <p class="checkout-disclaimer">El simulador no solicita números de tarjeta, CVV ni contraseñas.</p>
    </form>
    <aside class="checkout-summary"><p class="eyebrow">Tu selección</p><div class="checkout-items">
      <?php foreach ($checkoutItems as $item): ?><div class="checkout-item"><img src="/assets/images/products/<?= h($item['image'] ?? 'product-placeholder.svg') ?>" alt="" width="42" height="42"><span><?= h($item['name']) ?> × <?= (int)$item['quantity'] ?></span><strong><?= h(money((int)$item['line_total_cents'])) ?></strong></div><?php endforeach; ?>
    </div><div class="summary-line"><span>Subtotal</span><strong><?= h(money((int)$checkoutCart['subtotal_cents'])) ?></strong></div><div class="summary-line"><span>Entrega</span><strong>No disponible</strong></div><div class="summary-line summary-total"><span>Total simulado</span><strong><?= h(money((int)$checkoutCart['total_cents'])) ?></strong></div><a class="text-link" href="/carrito">Editar carrito</a></aside>
  <?php endif; ?>
</section>
