(function () {
  'use strict';

  const pageProducts = Array.isArray(window.VERDELIA_PRODUCTS) ? window.VERDELIA_PRODUCTS : [];
  const productBySku = new Map(pageProducts.map((product) => [product.sku, product]));
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const money = (cents) => '$' + (Number(cents || 0) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  let cartProducts = new Map();
  let toastTimer;

  const toast = (message) => {
    const box = document.querySelector('[data-toast]');
    if (!box) return;
    box.textContent = message;
    box.classList.add('is-visible');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => box.classList.remove('is-visible'), 3600);
  };

  const updateCartBadge = (count) => {
    document.querySelectorAll('[data-cart-count]').forEach((node) => { node.textContent = String(count); });
  };

  const renderCart = (cart) => {
    const items = cart.items;
    cartProducts = new Map(items.map((item) => [item.sku, item]));
    const list = document.querySelector('[data-cart-list]');
    const checkoutItems = document.querySelector('[data-checkout-items]');
    if (list) {
      list.replaceChildren();
      if (!items.length) {
        const empty = document.createElement('div');
        empty.className = 'empty-cart';
        empty.innerHTML = '<span class="empty-cart-mark">✳</span><h2>Tu carrito aún está vacío.</h2><p>Explora las plantas y los objetos de la colección para elegir tus favoritos.</p><a class="button button-outline" href="/tienda">Ir a la tienda</a>';
        list.append(empty);
      } else items.forEach((product) => {
        const row = document.createElement('article'); row.className = 'cart-item';
        const imageLink = document.createElement('a'); imageLink.className = 'cart-item-image'; imageLink.href = '/producto/' + encodeURIComponent(product.slug);
        const image = document.createElement('img'); image.src = '/assets/images/products/' + product.image; image.alt = 'Imagen ilustrativa de ' + product.name; image.loading = 'lazy'; imageLink.append(image);
        const details = document.createElement('div'); details.className = 'cart-item-details';
        const category = document.createElement('span'); category.className = 'eyebrow'; category.textContent = product.category_name;
        const name = document.createElement('a'); name.className = 'cart-item-name'; name.href = imageLink.href; name.textContent = product.name;
        const price = document.createElement('span'); price.className = 'cart-item-unit-price'; price.textContent = money(product.price_cents) + ' · cada pieza';
        const controls = document.createElement('div'); controls.className = 'cart-quantity-control';
        const minus = document.createElement('button'); minus.type = 'button'; minus.textContent = '−'; minus.setAttribute('aria-label', 'Quitar una unidad de ' + product.name); minus.dataset.qtyChange = '-1'; minus.dataset.sku = product.sku;
        const quantity = document.createElement('span'); quantity.textContent = String(product.quantity); quantity.setAttribute('aria-live', 'polite');
        const plus = document.createElement('button'); plus.type = 'button'; plus.textContent = '+'; plus.setAttribute('aria-label', 'Añadir una unidad de ' + product.name); plus.dataset.qtyChange = '1'; plus.dataset.sku = product.sku; plus.disabled = Number(product.quantity) >= Number(product.stock);
        controls.append(minus, quantity, plus); details.append(category, name, price, controls);
        const total = document.createElement('strong'); total.className = 'cart-item-total'; total.textContent = money(product.line_total_cents);
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'cart-remove'; remove.textContent = 'Quitar'; remove.dataset.removeSku = product.sku;
        row.append(imageLink, details, total, remove);
        if (product.over_stock) {
          const warning = document.createElement('p'); warning.className = 'cart-stock-warning'; warning.textContent = 'Solo quedan ' + product.stock + ' unidades. Reduce la cantidad para continuar.'; row.append(warning);
        }
        list.append(row);
      });
    }
    document.querySelectorAll('[data-cart-subtotal], [data-checkout-subtotal]').forEach((node) => { node.textContent = money(cart.subtotal_cents); });
    document.querySelectorAll('[data-cart-total], [data-checkout-total]').forEach((node) => { node.textContent = money(cart.total_cents); });
    document.querySelectorAll('[data-cart-units]').forEach((node) => { node.textContent = String(cart.unit_count); });
    const clearButton = document.querySelector('[data-cart-clear]');
    if (clearButton) clearButton.hidden = items.length === 0;
    if (checkoutItems) {
      checkoutItems.replaceChildren();
      if (!items.length) { const empty = document.createElement('p'); empty.textContent = 'Aún no hay productos en tu carrito.'; checkoutItems.append(empty); }
      else items.forEach((product) => {
        const row = document.createElement('div'); row.className = 'checkout-item';
        const image = document.createElement('img'); image.src = '/assets/images/products/' + product.image; image.alt = '';
        const name = document.createElement('span'); name.textContent = product.name + ' × ' + product.quantity;
        const amount = document.createElement('strong'); amount.textContent = money(product.line_total_cents);
        row.append(image, name, amount); checkoutItems.append(row);
      });
    }
    const checkoutLink = document.querySelector('[data-checkout-link]');
    if (checkoutLink) {
      checkoutLink.setAttribute('aria-disabled', items.length && cart.valid ? 'false' : 'true');
      checkoutLink.classList.toggle('is-disabled', items.length === 0 || !cart.valid);
    }
    updateCartBadge(cart.unit_count);
  };

  const readCart = async () => {
    const response = await fetch('/api/cart', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
    const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.message || 'No se pudo consultar el carrito.');
    renderCart(result);
    return result;
  };

  const mutateCart = async (payload) => {
    const response = await fetch('/api/cart', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(payload),
      credentials: 'same-origin',
      cache: 'no-store',
    });
    const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.message || 'No se pudo actualizar el carrito.');
    renderCart(result);
    return result;
  };

  const addToCart = async (button) => {
    const sku = button.dataset.addToCart;
    const product = productBySku.get(sku);
    const input = button.dataset.quantitySource ? document.querySelector('[data-product-quantity]') : null;
    const quantity = input ? Number(input.value) : 1;
    if (!product || !Number.isInteger(quantity) || quantity < 1) {
      if (input) { input.setCustomValidity('Elige una cantidad entera positiva.'); input.reportValidity(); }
      else toast('No pudimos validar este producto. Recarga el catálogo.');
      return;
    }
    input?.setCustomValidity('');
    try {
      await mutateCart({ action: 'add', sku, quantity });
      toast(product.name + ' se añadió al carrito. Las existencias se descuentan al aprobar la compra demo.');
    } catch (error) { toast(error.message); }
  };

  const changeQuantity = async (sku, delta) => {
    const product = cartProducts.get(sku);
    if (!product) return;
    const next = Number(product.quantity) + delta;
    try {
      if (next < 1) await mutateCart({ action: 'remove', sku });
      else await mutateCart({ action: 'set', sku, quantity: next });
    } catch (error) { toast(error.message); }
  };

  document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    const menuButton = target.closest('[data-menu-toggle]');
    if (menuButton) {
      const nav = document.querySelector('[data-main-nav]');
      const open = menuButton.getAttribute('aria-expanded') !== 'true';
      menuButton.setAttribute('aria-expanded', String(open));
      menuButton.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
      nav?.classList.toggle('is-open', open);
    }
    const addButton = target.closest('[data-add-to-cart]'); if (addButton) addToCart(addButton);
    const stepButton = target.closest('[data-quantity-step]');
    if (stepButton) {
      const input = document.querySelector('[data-product-quantity]');
      if (input) input.value = String(Math.max(Number(input.min) || 1, Math.min(Number(input.max) || 999, (Number(input.value) || 1) + Number(stepButton.dataset.quantityStep))));
    }
    const quantityButton = target.closest('[data-qty-change]'); if (quantityButton) changeQuantity(quantityButton.dataset.sku, Number(quantityButton.dataset.qtyChange));
    const removeButton = target.closest('[data-remove-sku]'); if (removeButton) mutateCart({ action: 'remove', sku: removeButton.dataset.removeSku }).catch((error) => toast(error.message));
    const clearButton = target.closest('[data-cart-clear]'); if (clearButton) mutateCart({ action: 'clear' }).then(() => toast('Vaciamos tu carrito.')).catch((error) => toast(error.message));
    const checkoutLink = target.closest('[data-checkout-link]');
    if (checkoutLink?.getAttribute('aria-disabled') === 'true') event.preventDefault();
    if (target.closest('[data-print-receipt]')) window.print();
  });

  document.querySelectorAll('.admin-image-select').forEach((select) => select.addEventListener('change', () => {
    const option = select.selectedOptions[0];
    const preview = select.closest('label')?.querySelector('.admin-image-preview');
    if (preview && option?.dataset.image) preview.src = option.dataset.image;
  }));

  const priceRange = document.querySelector('[data-filter-price]');
  priceRange?.addEventListener('input', () => { const label=document.querySelector('[data-price-label]'); if (label) label.textContent=money(Number(priceRange.value)); });
  document.querySelector('[data-sort]')?.addEventListener('change', (event) => document.getElementById(event.target.getAttribute('form'))?.requestSubmit());

  document.querySelectorAll('[data-demo-form]').forEach((form) => form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const submit=form.querySelector('[type="submit"]'); const feedback=form.querySelector('[data-form-feedback]');
    if (submit) { submit.disabled=true; submit.classList.add('is-loading'); }
    if (feedback) feedback.textContent='Comprobando campos…';
    window.setTimeout(() => {
      if (submit) { submit.disabled=false; submit.classList.remove('is-loading'); }
      const kind=form.dataset.formKind||'formulario';
      const message=kind==='checkout'?'El checkout está disponible. Revisa el resumen antes de continuar.':kind==='contacto'?'Mensaje de ejemplo preparado; no se envió ni se guardó.':'Formulario de demostración validado.';
      if (feedback) feedback.textContent=message;
      toast(message);
    },450);
  }));

  const chartColors = { revenue: '#4f7d5b', cost: '#b8795a', profit: '#355d84', linear: '#b8795a', exponential: '#355d84', grid: '#e6e8e0', text: '#59665f' };
  const chartCanvases = [...document.querySelectorAll('canvas[data-chart][data-points]')];
  const fitCanvas = (canvas) => {
    const rect = canvas.getBoundingClientRect();
    const width = Math.max(300, Math.floor(rect.width));
    const height = Math.max(220, Math.floor(rect.height));
    const ratio = Math.min(2, window.devicePixelRatio || 1);
    canvas.width = Math.floor(width * ratio);
    canvas.height = Math.floor(height * ratio);
    const context = canvas.getContext('2d');
    context.setTransform(ratio, 0, 0, ratio, 0, 0);
    context.clearRect(0, 0, width, height);
    context.font = '12px system-ui, sans-serif';
    context.fillStyle = chartColors.text;
    return { context, width, height };
  };
  const chartFrame = (context, width, height, min, max, labels) => {
    const pad = { left: 54, right: 12, top: 14, bottom: 38 };
    const plotW = Math.max(20, width - pad.left - pad.right);
    const plotH = Math.max(20, height - pad.top - pad.bottom);
    const span = max - min || 1;
    const y = (value) => pad.top + (max - value) * plotH / span;
    context.strokeStyle = chartColors.grid;
    context.fillStyle = chartColors.text;
    context.lineWidth = 1;
    for (let i = 0; i <= 4; i += 1) {
      const value = min + span * (4 - i) / 4;
      const py = pad.top + plotH * i / 4;
      context.beginPath(); context.moveTo(pad.left, py); context.lineTo(width - pad.right, py); context.stroke();
      context.textAlign = 'right'; context.textBaseline = 'middle';
      context.fillText(money(value), pad.left - 7, py);
    }
    const count = labels.length;
    const widestLabel = labels.reduce((widest, label) => Math.max(widest, context.measureText(String(label || '')).width), 0);
    const candidateCount = Math.min(6, count, Math.max(2, Math.floor(plotW / (widestLabel + 12)) + 1));
    const candidates = Array.from({ length: candidateCount }, (_, index) => candidateCount === 1 ? 0 : Math.round(index * (count - 1) / (candidateCount - 1)));
    const tickIndices = [];
    const labelBounds = (at, index) => {
      const px = pad.left + (count <= 1 ? 0 : at * plotW / (count - 1));
      const labelWidth = context.measureText(String(labels[at] || '')).width;
      const align = index === 0 ? 'left' : (index === candidateCount - 1 ? 'right' : 'center');
      return { at, px, align, left: align === 'left' ? px : (align === 'right' ? px - labelWidth : px - labelWidth / 2), right: align === 'left' ? px + labelWidth : (align === 'right' ? px : px + labelWidth / 2) };
    };
    candidates.forEach((at, index) => {
      const candidate = labelBounds(at, index);
      const previous = tickIndices.length ? labelBounds(tickIndices[tickIndices.length - 1], tickIndices.length - 1) : null;
      if (!previous || candidate.left >= previous.right + 8) tickIndices.push(at);
      else if (index === candidates.length - 1 && tickIndices.length > 1) tickIndices[tickIndices.length - 1] = at;
    });
    tickIndices.forEach((at, index) => {
      const tick = labelBounds(at, index);
      context.textAlign = tick.align; context.textBaseline = 'top';
      context.fillText(String(labels[at] || ''), tick.px, height - pad.bottom + 12);
    });
    return { pad, plotW, plotH, y };
  };
  const showChartEmpty = (canvas, message) => {
    const { context, width, height } = fitCanvas(canvas);
    context.fillStyle = chartColors.text; context.textAlign = 'center'; context.textBaseline = 'middle';
    context.fillText(message, width / 2, height / 2);
  };
  const compressBars = (points, maxBars = 36) => {
    if (points.length <= maxBars) return points;
    const stride = Math.ceil(points.length / maxBars);
    const result = [];
    for (let i = 0; i < points.length; i += stride) {
      const part = points.slice(i, i + stride);
      result.push({ label: part.length > 1 ? `${part[0].label}–${part[part.length - 1].label}` : part[0].label, value: part.reduce((sum, point) => sum + Number(point.value || 0), 0) });
    }
    return result;
  };
  const drawBars = (canvas, points) => {
    if (!points.length) return showChartEmpty(canvas, 'No hay ventas confirmadas en este intervalo.');
    const rows = compressBars(points);
    const values = rows.map((point) => Number(point.value || 0));
    const maximum = Math.max(1, ...values);
    const { context, width, height } = fitCanvas(canvas);
    const frame = chartFrame(context, width, height, 0, maximum * 1.1, rows.map((point) => point.label));
    const slot = frame.plotW / rows.length;
    const barWidth = Math.max(1, slot * 0.62);
    context.fillStyle = chartColors.revenue;
    rows.forEach((point, index) => {
      const value = Math.max(0, Number(point.value || 0));
      const barHeight = frame.y(0) - frame.y(value);
      context.fillRect(frame.pad.left + slot * index + (slot - barWidth) / 2, frame.y(value), barWidth, Math.max(0, barHeight));
    });
  };
  const drawLines = (canvas, labels, datasets) => {
    if (!labels.length || !datasets.some((series) => series.values.some((value) => Number.isFinite(Number(value))))) return showChartEmpty(canvas, 'No hay valores para graficar en este intervalo.');
    const plotted = datasets.flatMap((series) => series.values).filter((value) => value !== null && Number.isFinite(Number(value))).map(Number);
    let min = Math.min(0, ...plotted); let max = Math.max(0, ...plotted);
    if (min === max) { min -= 1; max += 1; }
    const { context, width, height } = fitCanvas(canvas);
    const frame = chartFrame(context, width, height, min, max, labels);
    datasets.forEach((series) => {
      context.strokeStyle = series.color; context.fillStyle = series.color; context.lineWidth = 2;
      context.setLineDash(series.dashed ? [6, 4] : []);
      let started = false;
      series.values.forEach((value, index) => {
        if (value === null || !Number.isFinite(Number(value))) { if (started) context.stroke(); started = false; return; }
        const x = frame.pad.left + (labels.length <= 1 ? frame.plotW / 2 : index * frame.plotW / (labels.length - 1));
        const y = frame.y(Number(value));
        if (!started) { context.beginPath(); context.moveTo(x, y); started = true; } else context.lineTo(x, y);
        if (series.points && labels.length <= 100) { context.fillRect(x - 2, y - 2, 4, 4); }
      });
      if (started) context.stroke();
    });
    context.setLineDash([]);
  };
  const drawEvolution = (canvas, points) => {
    if (!points.length) return showChartEmpty(canvas, 'No hay ventas confirmadas en este intervalo.');
    drawLines(canvas, points.map((point) => point.label), [
      { name: 'Ingresos', color: chartColors.revenue, values: points.map((point) => point.revenue) },
      { name: 'Costo', color: chartColors.cost, values: points.map((point) => point.cost) },
      { name: 'Ganancia bruta', color: chartColors.profit, values: points.map((point) => point.profit) },
    ]);
  };
  const drawModels = (canvas, data) => {
    const labels = Array.isArray(data.labels) ? data.labels.slice() : [];
    const actual = Array.isArray(data.actual) ? data.actual.slice() : [];
    if (!labels.length) return showChartEmpty(canvas, 'No hay ventas confirmadas en este intervalo.');
    const forecastCount = Math.max((data.linear_forecast || []).length, (data.exponential_forecast || []).length);
    for (let i = 0; i < forecastCount; i += 1) labels.push(`P+${i + 1}`);
    const pad = (values) => [...values, ...new Array(Math.max(0, labels.length - values.length)).fill(null)];
    const projection = (values) => {
      const result = new Array(labels.length).fill(null);
      if (actual.length) result[actual.length - 1] = actual[actual.length - 1];
      values.forEach((value, index) => { result[actual.length + index] = value; });
      return result;
    };
    drawLines(canvas, labels, [
      { name: 'Histórico', color: chartColors.revenue, values: pad(actual), points: true },
      { name: 'Lineal', color: chartColors.linear, values: pad(data.linear || []) },
      { name: 'Exponencial', color: chartColors.exponential, values: pad(data.exponential || []) },
      { name: 'Proyección lineal', color: chartColors.linear, values: projection(data.linear_forecast || []), dashed: true },
      { name: 'Proyección exponencial', color: chartColors.exponential, values: projection(data.exponential_forecast || []), dashed: true },
    ]);
  };
  const renderAnalyticsCharts = () => chartCanvases.forEach((canvas) => {
    try {
      const points = JSON.parse(canvas.dataset.points || '[]');
      if (canvas.dataset.chart === 'bars') drawBars(canvas, points);
      else if (canvas.dataset.chart === 'evolution') drawEvolution(canvas, points);
      else if (canvas.dataset.chart === 'models') drawModels(canvas, points);
    } catch (_) { showChartEmpty(canvas, 'No se pudo representar la gráfica.'); }
  });
  if (chartCanvases.length) {
    renderAnalyticsCharts();
    let chartResizeTimer;
    window.addEventListener('resize', () => { window.clearTimeout(chartResizeTimer); chartResizeTimer = window.setTimeout(renderAnalyticsCharts, 120); }, { passive: true });
  }

  readCart().catch(() => {
    const list = document.querySelector('[data-cart-list]');
    if (list) list.innerHTML = '<p class="cart-loading">No se pudo consultar el carrito. Comprueba la conexión e inténtalo nuevamente.</p>';
    const checkoutItems = document.querySelector('[data-checkout-items]');
    if (checkoutItems) checkoutItems.textContent = 'No se pudo consultar el carrito.';
  });
})();
