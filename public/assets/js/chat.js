(function () {
  'use strict';

  const root = document.querySelector('[data-store-chat]');
  if (!root) return;

  const panel = root.querySelector('[data-chat-panel]');
  const toggle = root.querySelector('[data-chat-toggle]');
  const close = root.querySelector('[data-chat-close]');
  const form = root.querySelector('[data-chat-form]');
  const input = root.querySelector('[data-chat-input]');
  const send = root.querySelector('[data-chat-send]');
  const messages = root.querySelector('[data-chat-messages]');
  const suggestions = root.querySelector('[data-chat-suggestions]');
  const launchers = document.querySelectorAll('[data-open-store-chat]');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const storageKey = 'verdelia.store-chat.v1';
  const maxEntries = 30;
  const timeoutMs = 8000;
  let conversation = [];
  let productContext = null;
  let busy = false;
  let openingControl = toggle;

  const openPanel = (trigger = toggle) => {
    openingControl = trigger;
    panel.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-label', 'Cerrar asistente de VERDELIA');
    launchers.forEach(button => button.setAttribute('aria-expanded', 'true'));
    messages.scrollTop = messages.scrollHeight;
    if (!busy) input.focus();
    else close.focus();
  };

  const closePanel = (returnTo = openingControl) => {
    panel.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Abrir asistente de VERDELIA');
    launchers.forEach(button => button.setAttribute('aria-expanded', 'false'));
    returnTo.focus();
  };

  const appendMessage = (message, kind) => {
    const article = document.createElement('article');
    article.className = `store-chat-message store-chat-message-${kind}`;
    const text = document.createElement('p');
    text.textContent = message;
    article.append(text);
    messages.append(article);
    messages.scrollTop = messages.scrollHeight;
    return article;
  };

  const safeProductPath = (value) => typeof value === 'string' && /^\/producto\/[a-z0-9-]{1,120}$/.test(value);
  const validContext = (value) => typeof value === 'string' && /^[a-z0-9-]{1,120}$/.test(value) ? value : null;
  const productLinks = (products) => Array.isArray(products) ? products
    .filter(product => product && safeProductPath(product.url) && typeof product.name === 'string')
    .slice(0, 5).map(product => ({ url: product.url, name: product.name.slice(0, 160) })) : [];

  const appendProducts = (article, products) => {
    if (products.length === 0) return;
    const links = document.createElement('div');
    links.className = 'store-chat-products';
    products.forEach((product) => {
      const link = document.createElement('a');
      link.href = product.url;
      link.textContent = product.name;
      links.append(link);
    });
    article.append(links);
  };

  const renderEntry = (entry) => {
    const article = appendMessage(entry.text, entry.kind);
    article.dataset.chatEntry = '';
    appendProducts(article, entry.products);
    messages.scrollTop = messages.scrollHeight;
  };

  const saveEntry = (kind, text, products) => {
    const entry = { kind, text, products: productLinks(products) };
    renderEntry(entry);
    conversation.push(entry);
    if (conversation.length > maxEntries) {
      conversation.shift();
      messages.querySelector('[data-chat-entry]')?.remove();
    }
    try {
      sessionStorage.setItem(storageKey, JSON.stringify({ conversation, productContext }));
    } catch {
      // The open panel still works if storage is unavailable.
    }
  };

  const restoreConversation = () => {
    try {
      const saved = JSON.parse(sessionStorage.getItem(storageKey));
      if (!saved || !Array.isArray(saved.conversation)) return;
      conversation = saved.conversation.slice(-maxEntries).filter(entry =>
        entry && ['user', 'assistant', 'error'].includes(entry.kind) && typeof entry.text === 'string'
      ).map(entry => ({ kind: entry.kind, text: entry.text.slice(0, 4000), products: productLinks(entry.products) }));
      productContext = validContext(saved.productContext);
      messages.querySelectorAll('[data-chat-entry]').forEach(entry => entry.remove());
      conversation.forEach(renderEntry);
    } catch {
      // A missing or invalid saved conversation can be skipped.
    }
  };

  const ask = async (message) => {
    const prompt = message.trim();
    if (!prompt || busy) return;

    saveEntry('user', prompt);
    input.value = '';
    busy = true;
    send.disabled = true;
    input.disabled = true;
    suggestions.querySelectorAll('button').forEach(button => { button.disabled = true; });
    messages.setAttribute('aria-busy', 'true');
    const pending = appendMessage('Estoy revisando el catálogo…', 'assistant');
    const controller = new AbortController();
    let abortMessage = 'La consulta tardó demasiado. Puedes volver a enviarla.';
    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'store-chat-cancel';
    cancel.textContent = 'Cancelar consulta';
    cancel.addEventListener('click', () => {
      abortMessage = 'Consulta cancelada. Puedes volver a enviarla.';
      controller.abort();
    });
    pending.append(cancel);
    messages.scrollTop = messages.scrollHeight;
    const timeout = setTimeout(() => controller.abort(), timeoutMs);
    let errorMessage = 'No pude consultar el catálogo. Puedes volver a enviarla.';

    try {
      const response = await fetch('/api/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ message: prompt, product_context: productContext }),
        credentials: 'same-origin',
        cache: 'no-store',
        signal: controller.signal,
      });
      if (response.status === 419) {
        errorMessage = 'La sesión expiró. Recarga la página e inténtalo de nuevo.';
      }
      if (!response.ok) throw new Error('Chat request failed');
      const result = await response.json();
      if (!result || result.ok !== true || typeof result.message !== 'string') throw new Error('Invalid chat response');

      pending.remove();
      productContext = validContext(result.product_context);
      saveEntry('assistant', result.message, result.products);
    } catch {
      pending.remove();
      input.value = prompt;
      saveEntry('error', controller.signal.aborted ? abortMessage : errorMessage);
    } finally {
      clearTimeout(timeout);
      busy = false;
      send.disabled = false;
      input.disabled = false;
      suggestions.querySelectorAll('button').forEach(button => { button.disabled = false; });
      messages.setAttribute('aria-busy', 'false');
      if (!panel.hidden) input.focus();
    }
  };

  toggle.addEventListener('click', () => panel.hidden ? openPanel() : closePanel(toggle));
  close.addEventListener('click', () => closePanel());
  launchers.forEach(button => {
    button.addEventListener('click', () => openPanel(button));
    button.hidden = false;
  });
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    ask(input.value);
  });
  input.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      form.requestSubmit();
    }
  });
  suggestions.addEventListener('click', (event) => {
    const button = event.target.closest('[data-chat-prompt]');
    if (button) ask(button.dataset.chatPrompt || '');
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !panel.hidden) closePanel();
  });
  restoreConversation();
  window.addEventListener('pageshow', (event) => {
    if (event.persisted && !busy) restoreConversation();
  });
})();
