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
  <section class="about-grid page-gutter"><div><p class="eyebrow">Nuestra mirada</p><h2>Lo natural, a tu propio ritmo.</h2></div><div><p>Imaginamos Verdelia como un pequeño estudio donde encontrar plantas, recipientes y consejos sencillos para empezar a crear un rincón verde, sin reglas complicadas.</p><a class="text-link" href="/tienda">Explora la colección <?= icon('arrow',16) ?></a></div></section>
  <section class="values-band page-gutter"><div><span>01</span><h3>Elegir con intención</h3><p>Piezas y plantas seleccionadas por su forma, textura y carácter.</p></div><div><span>02</span><h3>Aprender sin prisa</h3><p>Información clara para acompañar cada nuevo brote.</p></div><div><span>03</span><h3>Hacer espacio</h3><p>Ideas para sumar naturaleza a los espacios cotidianos.</p></div></section>
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
        <p>El formulario revisa los campos en tu navegador. El envío de mensajes no está disponible.</p>
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
  </section>
<?php elseif($route==='faq'): ?>
  <section class="faq-page page-gutter"><p class="eyebrow">Respuestas sencillas</p><h1>Preguntas<br><em>frecuentes.</em></h1><p class="faq-intro">Información sobre la colección, el carrito y los pedidos.</p><div class="faq-list"><details><summary>¿Puedo hacer una compra real?<span aria-hidden="true">+</span></summary><p>Puedes recorrer el proceso de compra con el simulador de pago. El pedido no genera un cobro ni un envío.</p></details><details><summary>¿Las imágenes representan exactamente cada planta?<span>+</span></summary><p>Son imágenes ilustrativas. El tamaño, el color y otros detalles pueden variar.</p></details><details><summary>¿Cómo elijo una planta para mi espacio?<span>+</span></summary><p>Considera primero la luz disponible, el espacio y el tiempo que puedes dedicarle. Usa la información de luz y cuidados de la ficha como punto de partida.</p></details><details><summary>¿El carrito conserva mis productos?<span>+</span></summary><p>Para visitantes se conserva en la sesión del navegador; si inicias sesión, se fusiona con tu carrito guardado en la base de datos y se mantiene entre visitas.</p></details><details><summary>¿Qué ocurre con un pago?</summary><p>El simulador permite aprobar, rechazar o cancelar un pedido. No solicita tarjetas o credenciales de PayPal ni se conecta a una entidad financiera.</p></details></div><a class="button button-outline" href="/contacto">Otra consulta <?= icon('arrow',16) ?></a></section>
<?php else: ?>
  <section class="privacy-page page-gutter"><h1>Privacidad</h1><div class="privacy-copy"><p>VERDELIA guarda las cuentas, los carritos, los pedidos y las referencias de pago simulado en su base de datos. La sesión utiliza una cookie para mantener el acceso y reconocer tu carrito.</p><p>Usa datos de ejemplo para la entrega. El simulador no solicita datos financieros, no contacta con servicios de pago y no realiza cobros.</p><a class="text-link" href="/">Volver al inicio <?= icon('arrow',16) ?></a></div></section>
<?php endif; ?>
