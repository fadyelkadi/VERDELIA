<section class="store-chat" data-store-chat aria-label="Asistente de VERDELIA">
  <div class="store-chat-panel" id="store-chat-panel" data-chat-panel hidden aria-labelledby="store-chat-title">
    <header class="store-chat-header">
      <div>
        <h2 id="store-chat-title">Asistente de VERDELIA</h2>
      </div>
      <button class="store-chat-close" type="button" data-chat-close aria-label="Cerrar asistente">×</button>
    </header>
    <p class="store-chat-intro">Consulta plantas, disponibilidad y cuidados.</p>
    <div class="store-chat-messages" data-chat-messages role="log" aria-label="Conversación" aria-live="polite" aria-relevant="additions text">
      <article class="store-chat-message store-chat-message-assistant">
        <p>Hola. ¿Qué te gustaría saber del catálogo?</p>
      </article>
    </div>
    <div class="store-chat-suggestions" data-chat-suggestions aria-label="Consultas rápidas">
      <button type="button" data-chat-prompt="Recomiéndame una planta para poca luz">Plantas para poca luz</button>
      <button type="button" data-chat-prompt="¿Qué plantas son fáciles de cuidar?">Fáciles de cuidar</button>
      <button type="button" data-chat-prompt="Tendencia de demanda del Aloe">Demanda del Aloe</button>
      <button type="button" data-chat-prompt="¿Hay disponibilidad de Monstera?">Consultar Monstera</button>
    </div>
    <form class="store-chat-form" data-chat-form>
      <label class="visually-hidden" for="store-chat-input">Escribe tu consulta</label>
      <textarea id="store-chat-input" name="message" rows="1" maxlength="240" placeholder="Escribe tu consulta…" required data-chat-input></textarea>
      <button type="submit" aria-label="Enviar consulta" data-chat-send>
        <span aria-hidden="true">↑</span>
      </button>
    </form>
    <p class="store-chat-footnote">Orientación sobre el catálogo de demostración.</p>
  </div>
  <button class="store-chat-toggle" type="button" data-chat-toggle aria-expanded="false" aria-controls="store-chat-panel" aria-label="Abrir asistente de VERDELIA">
    <span class="store-chat-toggle-icon" aria-hidden="true">✳</span>
    <span>¿Te ayudo?</span>
  </button>
</section>
