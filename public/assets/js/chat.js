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
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  let busy = false;

  const openPanel = () => {
    panel.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-label', 'Cerrar asistente de VERDELIA');
    input.focus();
  };

  const closePanel = () => {
    panel.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Abrir asistente de VERDELIA');
    toggle.focus();
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

  const appendProducts = (article, products) => {
    if (!Array.isArray(products) || products.length === 0) return;
    const links = document.createElement('div');
    links.className = 'store-chat-products';
    products.forEach((product) => {
      if (!product || !safeProductPath(product.url) || typeof product.name !== 'string') return;
      const link = document.createElement('a');
      link.href = product.url;
      link.textContent = product.name;
      links.append(link);
    });
    if (links.childElementCount) article.append(links);
  };

  const ask = async (message) => {
    const prompt = message.trim();
    if (!prompt || busy) return;

    appendMessage(prompt, 'user');
    input.value = '';
    busy = true;
    send.disabled = true;
    input.disabled = true;
    messages.setAttribute('aria-busy', 'true');
    const pending = appendMessage('Estoy revisando el catálogo…', 'assistant');

    try {
      const response = await fetch('/api/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ message: prompt }),
        credentials: 'same-origin',
        cache: 'no-store',
      });
      const result = await response.json();
      pending.remove();
      if (!response.ok || !result || result.ok !== true || typeof result.message !== 'string') {
        throw new Error(result && typeof result.message === 'string' ? result.message : 'No pude consultar el catálogo. Inténtalo de nuevo.');
      }
      const reply = appendMessage(result.message, 'assistant');
      appendProducts(reply, result.products);
    } catch (error) {
      pending.remove();
      appendMessage(error instanceof Error ? error.message : 'No pude consultar el catálogo. Inténtalo de nuevo.', 'error');
    } finally {
      busy = false;
      send.disabled = false;
      input.disabled = false;
      messages.setAttribute('aria-busy', 'false');
      if (!panel.hidden) input.focus();
    }
  };

  toggle.addEventListener('click', () => panel.hidden ? openPanel() : closePanel());
  close.addEventListener('click', closePanel);
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
})();
