<?php
declare(strict_types=1);
$user = currentUser();
$errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
$old = is_array($flash['old'] ?? null) ? $flash['old'] : [];
$checkoutItems = is_array($checkoutCart['items'] ?? null) ? $checkoutCart['items'] : [];
$checkoutValid = !empty($checkoutCart['valid']) && $checkoutItems !== [];
$deliveryValues = [
    'name'=>is_string($old['name'] ?? null) ? $old['name'] : (string)($user['name'] ?? ''),
    'email'=>is_string($old['email'] ?? null) ? $old['email'] : (string)($user['email'] ?? ''),
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
<section class="simple-page-head page-gutter"><p class="eyebrow">Paso final · simulador académico local</p><h1>Un último paso<br><em>con calma.</em></h1><p>Revisa el total, confirma la entrega ficticia y elige un resultado del simulador. No se solicitan tarjetas ni se mueve dinero real.</p></section>
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
      <div class="form-section-title"><span>01</span><div><h2>Datos de entrega</h2><p>Utiliza solo datos ficticios en esta demo.</p></div></div>
      <div class="form-grid">
        <label class="field-label" for="checkout-name">Nombre<input class="text-input" id="checkout-name" name="name" autocomplete="name" maxlength="120" required value="<?= h($deliveryValues['name']) ?>" aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>"><?php $fieldError('name'); ?></label>
        <label class="field-label" for="checkout-email">Correo<input class="text-input" id="checkout-email" name="email" type="email" autocomplete="email" maxlength="190" required value="<?= h($deliveryValues['email']) ?>" aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>"><?php $fieldError('email'); ?></label>
        <label class="field-label" for="checkout-phone">Teléfono de demostración<input class="text-input" id="checkout-phone" name="phone" type="tel" autocomplete="tel" maxlength="40" placeholder="+58 412 000 0000" required value="<?= h($deliveryValues['phone']) ?>" aria-invalid="<?= isset($errors['phone']) ? 'true' : 'false' ?>"><?php $fieldError('phone'); ?></label>
        <label class="field-label full-field" for="checkout-address">Dirección ficticia<input class="text-input" id="checkout-address" name="address" autocomplete="street-address" maxlength="180" required placeholder="Av. de ejemplo, edificio y apartamento" value="<?= h($deliveryValues['address']) ?>" aria-invalid="<?= isset($errors['address']) ? 'true' : 'false' ?>"><?php $fieldError('address'); ?></label>
        <label class="field-label" for="checkout-city">Ciudad<input class="text-input" id="checkout-city" name="city" autocomplete="address-level2" maxlength="100" required value="<?= h($deliveryValues['city']) ?>" aria-invalid="<?= isset($errors['city']) ? 'true' : 'false' ?>"><?php $fieldError('city'); ?></label>
        <label class="field-label" for="checkout-state">Estado o provincia<input class="text-input" id="checkout-state" name="state" autocomplete="address-level1" maxlength="100" required value="<?= h($deliveryValues['state']) ?>" aria-invalid="<?= isset($errors['state']) ? 'true' : 'false' ?>"><?php $fieldError('state'); ?></label>
        <label class="field-label" for="checkout-postal">Código postal <span>(opcional)</span><input class="text-input" id="checkout-postal" name="postal_code" autocomplete="postal-code" maxlength="20" value="<?= h($deliveryValues['postal_code']) ?>" aria-invalid="<?= isset($errors['postal_code']) ? 'true' : 'false' ?>"><?php $fieldError('postal_code'); ?></label>
        <label class="field-label" for="checkout-note">Nota de entrega <span>(opcional)</span><input class="text-input" id="checkout-note" name="note" maxlength="255" placeholder="Indicaciones ficticias" value="<?= h($deliveryValues['note']) ?>" aria-invalid="<?= isset($errors['note']) ? 'true' : 'false' ?>"><?php $fieldError('note'); ?></label>
      </div>
      <?php if (!$checkoutValid): ?><p class="auth-message is-error" role="alert">Algún producto excede el stock disponible o ya no está activo. <a href="/carrito">Actualiza tu carrito</a> antes de comprar.</p><?php endif; ?>
      <div class="form-section-title payment-title"><span>02</span><div><h2>Pago de demostración</h2><p>Los tres botones son simulaciones locales; no introduzcas datos financieros.</p></div></div>
      <div class="simulator-notice"><strong>Simulador VERDELIA · sin conexión externa</strong><span>La aprobación registra el pedido y descuenta existencias. El rechazo no lo hace.</span></div>
      <div class="checkout-actions">
        <button class="button button-primary button-wide" type="submit" name="payment_outcome" value="approve" <?= $checkoutValid ? '' : 'disabled' ?>>Aprobar pago demo <?= icon('arrow',17) ?></button>
        <button class="button button-outline button-wide" type="submit" name="payment_outcome" value="reject" <?= $checkoutValid ? '' : 'disabled' ?>>Simular pago rechazado</button>
        <button class="checkout-cancel" type="submit" name="payment_outcome" value="cancel" <?= $checkoutValid ? '' : 'disabled' ?>>Cancelar esta compra demo</button>
      </div>
      <p class="checkout-disclaimer">Al aprobar se registrará un pedido ficticio, una referencia de transacción local y un movimiento de inventario. Nunca se pide número de tarjeta, CVV o contraseña de PayPal.</p>
    </form>
    <aside class="checkout-summary"><p class="eyebrow">Tu selección</p><div class="checkout-items">
      <?php foreach ($checkoutItems as $item): ?><div class="checkout-item"><img src="/assets/images/products/<?= h($item['image'] ?? 'product-placeholder.svg') ?>" alt="" width="42" height="42"><span><?= h($item['name']) ?> × <?= (int)$item['quantity'] ?></span><strong><?= h(money((int)$item['line_total_cents'])) ?></strong></div><?php endforeach; ?>
    </div><div class="summary-line"><span>Subtotal</span><strong><?= h(money((int)$checkoutCart['subtotal_cents'])) ?></strong></div><div class="summary-line"><span>Entrega demo</span><strong>Gratis</strong></div><div class="summary-line summary-total"><span>Total</span><strong><?= h(money((int)$checkoutCart['total_cents'])) ?></strong></div><p class="summary-note">Total calculado por el servidor desde los precios vigentes de MySQL. Los importes enviados por el navegador no se utilizan.</p><a class="text-link" href="/carrito">Editar carrito</a></aside>
  <?php endif; ?>
</section>
