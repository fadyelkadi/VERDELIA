<?php declare(strict_types=1); ?>
<?php if($route==='about'): ?>
  <section class="institutional-hero">
    <div class="institutional-hero-inner page-gutter">
      <div class="institutional-copy">
        <p class="eyebrow">Un estudio botánico digital</p>
        <h1>Una pausa verde<br><em>en medio de todo.</em></h1>
        <p class="institutional-lead">En VERDELIA reunimos plantas y objetos de diseño natural para los espacios de todos los días.</p>
      </div>
      <div class="institutional-image">
        <img src="/assets/images/products/PLA-004-palma-areca.png" alt="Palma areca en maceta" width="1166" height="1349" fetchpriority="high">
      </div>
    </div>
  </section>
  <section class="about-grid page-gutter">
    <div><h2>Nuestra historia</h2></div>
    <div><p><?= h(StoreInformation::HISTORY) ?></p><h3>Nuestra misión</h3><p><?= h(StoreInformation::MISSION) ?></p><h3>Nuestra visión</h3><p><?= h(StoreInformation::VISION) ?></p><a class="text-link" href="/tienda">Explora la colección <?= icon('arrow',16) ?></a></div>
  </section>
  <section class="values-band page-gutter" aria-label="Nuestros valores">
    <?php foreach (StoreInformation::VALUES as $title => $description): ?>
      <div><h3><?= h($title) ?></h3><p><?= h($description) ?></p></div>
    <?php endforeach; ?>
  </section>
  <section class="about-grid page-gutter">
    <div><h2>Cómo funciona la tienda</h2></div>
    <div><p><?= h(StoreInformation::PURCHASE) ?></p><p><?= h(StoreInformation::SHIPPING) ?></p><a class="text-link" href="/preguntas-frecuentes">Preguntas frecuentes <?= icon('arrow',16) ?></a> <a class="text-link" href="/contacto">Conoce al equipo <?= icon('arrow',16) ?></a></div>
  </section>
<?php elseif($route==='contact'): ?>
  <section class="contact-page page-gutter">
    <div class="contact-intro">
      <h1>Contacto</h1>
      <p class="contact-lead">¿Buscas una planta para tu espacio o tienes dudas sobre un pedido? Empieza por aquí.</p>
      <div class="contact-guide">
        <div class="contact-guide-copy">
          <h2>Ayuda para elegir</h2>
          <p>Consulta qué plantas se adaptan a tu luz, sus precios y sus existencias.</p>
          <button class="text-link" type="button" data-open-store-chat aria-controls="store-chat-panel" aria-expanded="false" hidden>Preguntar al asistente <?= icon('arrow',16) ?></button>
        </div>
        <img src="/assets/images/products/PLA-003-bambu-suerte.png" alt="" width="1067" height="1475">
      </div>
      <a class="contact-faq-link" href="/preguntas-frecuentes">
        <span><strong>Preguntas frecuentes</strong><small>Carrito, pedidos y pagos</small></span>
        <?= icon('arrow',18) ?>
      </a>
    </div>
    <form class="contact-form" data-contact-form aria-labelledby="contact-form-title">
      <div class="contact-form-heading">
        <h2 id="contact-form-title">Revisa tu mensaje</h2>
        <p><?= h(StoreInformation::CONTACT) ?> El envío desde el formulario no está disponible.</p>
      </div>
      <div class="contact-fields">
        <label class="field-label" for="contact-name">Tu nombre<input class="text-input" id="contact-name" autocomplete="name" required></label>
        <label class="field-label" for="contact-email">Tu correo<input class="text-input" id="contact-email" type="email" autocomplete="email" required></label>
      </div>
      <label class="field-label" for="contact-subject">Tema<select class="text-input" id="contact-subject"><option>Consulta general</option><option>Cuidados de plantas</option><option>Productos y pedidos</option></select></label>
      <label class="field-label" for="contact-message">Mensaje<textarea class="text-input textarea" id="contact-message" rows="5" required></textarea></label>
      <button class="button button-primary" type="submit">Revisar mensaje <?= icon('arrow',16) ?></button>
      <p class="form-feedback" data-form-feedback role="status" aria-live="polite"></p>
    </form>
    <section class="contact-team" aria-labelledby="contact-team-title">
      <p class="eyebrow">Diseño y desarrollo</p>
      <h2 id="contact-team-title">Equipo de VERDELIA</h2>
      <ul class="contact-team-list">
        <?php foreach (StoreInformation::TEAM as $name => $email): ?>
          <li><strong><?= h($name) ?></strong><a href="mailto:<?= h($email) ?>"><?= h($email) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </section>
<?php elseif($route==='faq'): ?>
  <section class="faq-page page-gutter">
    <h1>Preguntas<br><em>frecuentes.</em></h1><p class="faq-intro">Información sobre la colección, el carrito y los pedidos.</p>
    <div class="faq-list">
      <?php foreach (StoreInformation::faq() as $question => $answer): ?>
        <details><summary><?= h($question) ?><span aria-hidden="true">+</span></summary><p><?= h($answer) ?></p></details>
      <?php endforeach; ?>
    </div>
    <a class="button button-outline" href="/contacto">Otra consulta <?= icon('arrow',16) ?></a>
  </section>
<?php else: ?>
  <section class="privacy-page page-gutter"><h1>Privacidad</h1><div class="privacy-copy"><p><?= h(StoreInformation::PRIVACY) ?></p><p>Usa datos de ejemplo para la entrega. <?= h(StoreInformation::PAYMENT) ?></p><a class="text-link" href="/">Volver al inicio <?= icon('arrow',16) ?></a></div></section>
<?php endif; ?>
