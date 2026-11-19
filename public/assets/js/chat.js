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
  const maxProducts = 12;
  const storePages = new Map([
    ['/tienda', 'Ver tienda'],
    ['/contacto', 'Ver contacto'],
    ['/preguntas-frecuentes', 'Ver preguntas frecuentes'],
    ['/cuenta/pedidos', 'Ver mis pedidos'],
    ['/registro', 'Crear cuenta'],
  ]);
  const timeoutMs = 8000;
  const defaultPrompts = [
    ['Recomiéndame una planta para poca luz', 'Plantas para poca luz'],
    ['¿Qué plantas son fáciles de cuidar?', 'Fáciles de cuidar'],
    ['¿Hay disponibilidad de Monstera?', 'Consultar Monstera'],
    ['Quiero una maceta', 'Ver macetas'],
    ['Tendencia de demanda del Aloe', 'Demanda del Aloe'],
  ];
  let conversation = [];
  let productContext = null;
  let productOptions = [];
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
  const safeImagePath = (value) => typeof value === 'string' && /^\/assets\/images\/products\/[a-z0-9][a-z0-9._-]*\.(?:png|webp|jpe?g|svg)$/i.test(value);
  const placeholder = '/assets/images/products/product-placeholder.svg';
  const validContext = (value) => typeof value === 'string' && /^[a-z0-9-]{1,120}$/.test(value) ? value : null;
  const productLinks = (products) => Array.isArray(products) ? products
    .filter(product => product && safeProductPath(product.url) && typeof product.name === 'string')
    .slice(0, maxProducts).map(product => ({
      url: product.url,
      name: product.name.slice(0, 160),
      image: safeImagePath(product.image) ? product.image : placeholder,
      imageAlt: String(product.image_alt ?? product.imageAlt ?? '').slice(0, 200),
      description: typeof product.description === 'string' ? product.description.slice(0, 2000) : '',
      size: typeof product.size === 'string' ? product.size.slice(0, 64) : '',
      subtotal: typeof product.subtotal === 'string' ? product.subtotal.slice(0, 80) : '',
      price: typeof product.price === 'string' ? product.price.slice(0, 32) : '',
      stock: Number.isInteger(product.stock) ? Math.max(0, product.stock) : null,
      light: typeof product.light === 'string' ? product.light.slice(0, 80) : '',
      care: typeof product.care === 'string' ? product.care.slice(0, 80) : '',
      placement: typeof product.placement === 'string' ? product.placement.slice(0, 120) : '',
    })) : [];

  const appendProducts = (article, products, detailed) => {
    if (products.length === 0) return;
    const links = document.createElement('div');
    links.className = 'store-chat-products';
    let placement = '';
    products.forEach((product) => {
      if (product.placement && product.placement !== placement) {
        const heading = document.createElement('h3');
        heading.className = 'store-chat-placement';
        heading.textContent = product.placement;
        links.append(heading);
        placement = product.placement;
      }
      const card = document.createElement('div');
      card.className = 'store-chat-product';
      if (detailed) {
        const image = document.createElement('img');
        image.className = 'store-chat-product-image';
        image.src = product.image;
        image.alt = product.image === placeholder ? '' : product.imageAlt;
        image.width = 480;
        image.height = 360;
        image.loading = 'lazy';
        image.decoding = 'async';
        image.addEventListener('error', () => {
          image.src = placeholder;
          image.alt = '';
        }, { once: true });
        card.append(image);
      }
      const body = document.createElement('div');
      body.className = 'store-chat-product-body';
      const name = document.createElement('strong');
      name.textContent = product.name;
      body.append(name);
      if (detailed && product.description) {
        const description = document.createElement('p');
        description.textContent = product.description;
        body.append(description);
      }
      const details = [
        product.price,
        product.stock === null ? '' : (product.stock === 0 ? 'Agotado' : product.stock + ' en stock'),
        product.light ? 'Luz: ' + product.light : '',
        product.care ? 'Cuidado: ' + product.care : '',
        product.size ? 'Medida: ' + product.size : '',
        product.subtotal ? 'Subtotal: ' + product.subtotal : '',
      ].filter(Boolean);
      if (details.length) {
        const meta = document.createElement('small');
        meta.textContent = details.join(' · ');
        body.append(meta);
      }
      const link = document.createElement('a');
      link.href = product.url;
      link.textContent = 'Ver producto';
      link.setAttribute('aria-label', 'Ver producto: ' + product.name);
      body.append(link);
      card.append(body);
      links.append(card);
    });
    article.append(links);
  };

  const renderEntry = (entry) => {
    const article = appendMessage(entry.text, entry.kind);
    article.dataset.chatEntry = '';
    if (entry.detailed) article.classList.add('store-chat-message-detailed');
    appendProducts(article, entry.products, entry.detailed);
    if (entry.moreResults) {
      const catalogLink = document.createElement('a');
      catalogLink.href = '/tienda';
      catalogLink.className = 'store-chat-more';
      catalogLink.textContent = 'Ver catálogo completo';
      article.append(catalogLink);
    }
    if (storePages.has(entry.storeLink)) {
      const link = document.createElement('a');
      link.href = entry.storeLink;
      link.className = 'store-chat-more';
      link.textContent = storePages.get(entry.storeLink);
      article.append(link);
    }
    if (entry.followUp) {
      const followUp = document.createElement('p');
      followUp.className = 'store-chat-follow-up';
      followUp.textContent = entry.followUp;
      article.append(followUp);
    }
    messages.scrollTop = entry.detailed ? article.offsetTop - 12 : messages.scrollHeight;
    return article;
  };

  const saveEntry = (kind, text, products, presentation = {}) => {
    const entry = { kind, text, products: productLinks(products), ...presentation };
    const article = renderEntry(entry);
    conversation.push(entry);
    if (conversation.length > maxEntries) {
      conversation.shift();
      messages.querySelector('[data-chat-entry]')?.remove();
    }
    try {
      sessionStorage.setItem(storageKey, JSON.stringify({ conversation, productContext, productOptions }));
    } catch {
      // The open panel still works if storage is unavailable.
    }
    return article;
  };

  const setPrompts = (prompts) => {
    suggestions.replaceChildren();
    prompts.forEach(([prompt, label]) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.dataset.chatPrompt = prompt;
      button.textContent = label;
      suggestions.append(button);
    });
    const reset = document.createElement('button');
    reset.type = 'button';
    reset.dataset.chatNew = '';
    reset.textContent = 'Nueva consulta';
    suggestions.append(reset);
  };

  const resetConversation = () => {
    if (busy) return;
    conversation = [];
    productContext = null;
    productOptions = [];
    messages.querySelectorAll('[data-chat-entry]').forEach(entry => entry.remove());
    try { sessionStorage.removeItem(storageKey); } catch { /* El chat sigue disponible sin almacenamiento. */ }
    setPrompts(defaultPrompts);
    appendMessage('Hola. ¿Qué te gustaría saber del catálogo?', 'assistant').dataset.chatEntry = '';
  };

  const restoreConversation = () => {
    try {
      const saved = JSON.parse(sessionStorage.getItem(storageKey));
      if (!saved || !Array.isArray(saved.conversation)) return;
      conversation = saved.conversation.slice(-maxEntries).filter(entry =>
        entry && ['user', 'assistant', 'error'].includes(entry.kind) && typeof entry.text === 'string'
      ).map(entry => ({
        kind: entry.kind,
        text: entry.text.slice(0, 4000),
        products: productLinks(entry.products),
        detailed: entry.detailed === true,
        moreResults: entry.moreResults === true,
        storeLink: storePages.has(entry.storeLink) ? entry.storeLink : '',
        followUp: typeof entry.followUp === 'string' ? entry.followUp.slice(0, 240) : '',
      }));
      productContext = validContext(saved.productContext);
      productOptions = productLinks(saved.productOptions);
      messages.querySelectorAll('[data-chat-entry]').forEach(entry => entry.remove());
      conversation.forEach(renderEntry);
      if (productOptions.length) {
        setPrompts([['¿Cuál es la más económica?', 'Ordenar por precio'], ['¿Cuál requiere menos cuidado?', 'Comparar cuidados']]);
      }
    } catch {
      // A missing or invalid saved conversation can be skipped.
    }
  };

  const ask = async (message, retry = false) => {
    const prompt = message.trim();
    if (!prompt || busy) return;

    if (!retry) saveEntry('user', prompt);
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
        body: JSON.stringify({
          message: prompt,
          product_context: productContext,
          product_options: productOptions.map(product => product.url.slice('/producto/'.length)),
          history: conversation.filter(entry => entry.kind === 'user').slice(0, -1).slice(-8).map(entry => entry.text),
        }),
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
      const links = productLinks(result.products);
      if (result.keep_product_options !== true || links.length > 0) {
        productContext = validContext(result.product_context);
        productOptions = links;
      }
      const detailed = typeof result.intro === 'string' && links.length > 0;
      saveEntry('assistant', detailed ? result.intro : result.message, result.products, {
        detailed,
        moreResults: result.more_results === true,
        storeLink: storePages.has(result.store_link) ? result.store_link : '',
        followUp: typeof result.follow_up === 'string' ? result.follow_up.slice(0, 240) : '',
      });
      if (productOptions.length) {
        setPrompts([['¿Cuál es la más económica?', 'Ordenar por precio'], ['¿Cuál requiere menos cuidado?', 'Comparar cuidados']]);
      } else {
        setPrompts(defaultPrompts);
      }
    } catch {
      pending.remove();
      input.value = prompt;
      const errorArticle = saveEntry('error', controller.signal.aborted ? abortMessage : errorMessage);
      const retryButton = document.createElement('button');
      retryButton.type = 'button';
      retryButton.className = 'store-chat-retry';
      retryButton.textContent = 'Reintentar';
      retryButton.addEventListener('click', () => {
        errorArticle.remove();
        conversation = conversation.filter(entry => entry !== errorArticle._chatEntry);
        ask(prompt, true);
      }, { once: true });
      errorArticle.append(retryButton);
      errorArticle._chatEntry = conversation[conversation.length - 1];
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
    if (event.target.closest('[data-chat-new]')) { resetConversation(); return; }
    const button = event.target.closest('[data-chat-prompt]');
    if (button) ask(button.dataset.chatPrompt || '');
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !panel.hidden) closePanel();
  });
  restoreConversation();
  if (conversation.length === 0) setPrompts(defaultPrompts);
  window.addEventListener('pageshow', (event) => {
    if (event.persisted && !busy) restoreConversation();
  });
})();
