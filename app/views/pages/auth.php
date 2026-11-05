<?php
declare(strict_types=1);
$isRegister = $route === 'register';
$next = is_string($_GET['next'] ?? null) && $_GET['next'] === '/checkout' ? '/checkout' : '';
$errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
$old = is_array($flash['old'] ?? null) ? $flash['old'] : [];
$fieldError = static function (string $key) use ($errors): void {
    if (isset($errors[$key]) && is_string($errors[$key])) echo '<span class="field-error">' . h($errors[$key]) . '</span>';
};
?>
<section class="auth-page page-gutter">
  <div class="auth-art"><span class="auth-art-kicker">VERDELIA · DEMO ACADÉMICA</span><div class="auth-art-leaves"><img src="/assets/images/products/PLA-003-bambu-suerte.png" alt="Bambú ilustrativo en maceta" width="650" height="650"></div><div class="auth-art-copy"><span>Un espacio<br>para crecer.</span><small>Haz espacio para lo vivo.</small></div></div>
  <div class="auth-panel"><a class="back-link" href="/">← Volver al inicio</a><p class="eyebrow"><?= $isRegister ? 'Una cuenta para tu rincón verde' : 'Qué bueno tenerte aquí' ?></p><h1><?= $isRegister ? 'Crea tu cuenta.' : 'Inicia sesión.' ?></h1><p class="form-intro"><?= $isRegister ? 'Guarda tus favoritos y vuelve cuando quieras.' : 'Entra para continuar explorando la colección.' ?></p>
    <?php if (isset($flash['message']) && is_string($flash['message'])): ?><p class="auth-message <?= ($flash['type'] ?? '') === 'success' ? 'is-success' : 'is-error' ?>" role="status"><?= h($flash['message']) ?></p><?php endif; ?>
    <?php if ($errors !== []): ?><p class="auth-message is-error" role="alert">Revisa los campos marcados y vuelve a intentarlo.</p><?php endif; ?>
    <form class="form-stack" method="post" action="<?= $isRegister ? '/registro' : '/login' ?>">
      <?= verdelia_csrf_field() ?>
      <?php if ($next !== ''): ?><input type="hidden" name="next" value="/checkout"><?php endif; ?>
      <?php if ($isRegister): ?>
        <input type="hidden" name="action" value="register">
        <label class="field-label" for="full-name">Nombre<input class="text-input" id="full-name" name="name" autocomplete="name" placeholder="Tu nombre" required minlength="2" maxlength="120" value="<?= h(is_string($old['name'] ?? null) ? $old['name'] : '') ?>" aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>"><?php $fieldError('name'); ?></label>
      <?php else: ?>
        <input type="hidden" name="action" value="login">
      <?php endif; ?>
      <label class="field-label" for="email">Correo electrónico<input class="text-input" id="email" name="email" type="email" autocomplete="email" maxlength="190" placeholder="nombre@ejemplo.com" required value="<?= h(is_string($old['email'] ?? null) ? $old['email'] : '') ?>" aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>"><?php $fieldError('email'); ?></label>
      <label class="field-label" for="password">Contraseña<input class="text-input" id="password" name="password" type="password" autocomplete="<?= $isRegister ? 'new-password' : 'current-password' ?>" placeholder="<?= $isRegister ? 'Al menos 8 caracteres' : 'Tu contraseña' ?>" required <?= $isRegister ? 'minlength="8" maxlength="72"' : '' ?> aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>"><?php $fieldError('password'); ?></label>
      <?php if ($isRegister): ?>
        <label class="field-label" for="confirm-password">Confirma tu contraseña<input class="text-input" id="confirm-password" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8" maxlength="72" aria-invalid="<?= isset($errors['confirmation']) ? 'true' : 'false' ?>"><?php $fieldError('confirmation'); ?></label>
        <label class="check-row form-consent"><input type="checkbox" name="academic_demo" value="1" required><span>Entiendo que este sitio es una demostración académica.</span></label><?php $fieldError('consent'); ?>
      <?php endif; ?>
      <button class="button button-primary button-wide" type="submit"><?= $isRegister ? 'Crear cuenta' : 'Iniciar sesión' ?> <?= icon('arrow', 17) ?></button>
    </form>
    <p class="auth-switch"><?= $isRegister ? '¿Ya tienes una cuenta?' : '¿Aún no tienes cuenta?' ?> <a href="<?= $isRegister ? '/login' : '/registro' ?><?= $next !== '' ? '?next=%2Fcheckout' : '' ?>"><?= $isRegister ? 'Inicia sesión' : 'Regístrate' ?></a></p>
    <p class="demo-auth-note">Las cuentas se guardan en la base local VERDELIA. No se realizan compras ni pagos.</p>
  </div>
</section>
