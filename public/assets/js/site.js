(function () {
  'use strict';

  const productData = document.getElementById('verdelia-products');
  const pageProducts = JSON.parse(productData?.content.textContent || '[]');
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
    const link = document.querySelector('[data-cart-link]');
    const label = count === 1 ? 'Carrito de compras, 1 unidad' : 'Carrito de compras, ' + count + ' unidades';
    link?.setAttribute('aria-label', label);
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
      toast(product.name + ' se añadió al carrito.');
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

  const menuButton = document.querySelector('[data-menu-toggle]');
  const nav = menuButton && document.getElementById(menuButton.getAttribute('aria-controls'));
  if (menuButton && nav) {
    const mobile = window.matchMedia('(max-width: 800px)');
    const isOpen = () => menuButton.getAttribute('aria-expanded') === 'true';
    const insideMenu = (target) => target instanceof Node && (menuButton.contains(target) || nav.contains(target));
    const setMenu = (open) => {
      nav.classList.toggle('is-open', open);
      menuButton.setAttribute('aria-expanded', String(open));
      menuButton.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    };
    menuButton.closest('.header-inner').setAttribute('data-menu-ready', '');
    menuButton.addEventListener('click', (event) => {
      const open = !isOpen();
      setMenu(open);
      if (open && event.detail === 0) nav.querySelector('a')?.focus();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && isOpen() && insideMenu(event.target)) {
        event.preventDefault();
        setMenu(false);
        menuButton.focus();
      }
    });
    document.addEventListener('focusin', (event) => {
      if (isOpen() && !insideMenu(event.target)) setMenu(false);
    });
    document.addEventListener('pointerdown', (event) => {
      if (!isOpen() || insideMenu(event.target)) return;
      const focusWasInNav = nav.contains(document.activeElement);
      setMenu(false);
      if (focusWasInNav) menuButton.focus();
    });
    mobile.addEventListener('change', (event) => {
      const focusWasInNav = nav.contains(document.activeElement);
      const focusWasOnButton = document.activeElement === menuButton;
      setMenu(false);
      if (event.matches && focusWasInNav) menuButton.focus();
      if (!event.matches && focusWasOnButton) nav.querySelector('a')?.focus();
    });
  }

  document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
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
  document.addEventListener('submit', (event) => {
    if (event.target instanceof HTMLFormElement && event.target.dataset.confirm
      && !window.confirm(event.target.dataset.confirm)) event.preventDefault();
  });

  const priceRange = document.querySelector('[data-filter-price]');
  priceRange?.addEventListener('input', () => { const label=document.querySelector('[data-price-label]'); if (label) label.textContent=money(Number(priceRange.value)); });
  document.querySelector('[data-sort]')?.addEventListener('change', (event) => document.getElementById(event.target.getAttribute('form'))?.requestSubmit());

  document.querySelectorAll('[data-contact-form]').forEach((form) => form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const feedback = form.querySelector('[data-form-feedback]');
    if (feedback) feedback.textContent = 'Mensaje revisado. El formulario no realiza envíos.';
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
    context.font = '13px system-ui, sans-serif';
    context.fillStyle = chartColors.text;
    return { context, width, height };
  };
  const chartFrame = (context, width, height, min, max, labels, barCenters = false) => {
    const span = max - min || 1;
    const amounts = Array.from({ length: 5 }, (_, index) => money(min + span * (4 - index) / 4));
    const left = Math.ceil(Math.max(...amounts.map(label => context.measureText(label).width))) + 12;
    const pad = { left, right: 12, top: 14, bottom: 38 };
    const plotW = Math.max(20, width - pad.left - pad.right);
    const plotH = Math.max(20, height - pad.top - pad.bottom);
    const y = (value) => pad.top + (max - value) * plotH / span;
    context.strokeStyle = chartColors.grid;
    context.fillStyle = chartColors.text;
    context.lineWidth = 1;
    for (let i = 0; i <= 4; i += 1) {
      const py = pad.top + plotH * i / 4;
      context.beginPath(); context.moveTo(pad.left, py); context.lineTo(width - pad.right, py); context.stroke();
      context.textAlign = 'right'; context.textBaseline = 'middle';
      context.fillText(amounts[i], pad.left - 7, py);
    }
    const count = labels.length;
    if (count > 0) {
      const gap = 8;
      const labelLimit = count === 1 ? plotW : (plotW - gap) / 2;
      const tickFor = (index) => {
        const label = String(labels[index] || '');
        let prefix = label;
        while (context.measureText(prefix + (prefix !== label ? '…' : '')).width > labelLimit && prefix.length > 1) {
          prefix = prefix.slice(0, -1);
        }
        const text = prefix === label ? label : prefix + '…';
        const labelWidth = context.measureText(text).width;
        const fraction = count === 1 ? 0.5 : (barCenters ? (index + 0.5) / count : index / (count - 1));
        const x = Math.max(pad.left, Math.min(pad.left + plotW - labelWidth, pad.left + fraction * plotW - labelWidth / 2));
        return { text, x, right: x + labelWidth };
      };
      const ticks = [tickFor(0)];
      if (count > 1) {
        const last = tickFor(count - 1);
        const step = Math.max(1, Math.ceil((count - 1) / 5));
        for (let index = step; index < count - 1; index += step) {
          const tick = tickFor(index);
          if (tick.x >= ticks[ticks.length - 1].right + gap && tick.right + gap <= last.x) ticks.push(tick);
        }
        ticks.push(last);
      }
      context.textAlign = 'left'; context.textBaseline = 'top';
      ticks.forEach(tick => context.fillText(tick.text, tick.x, height - pad.bottom + 12, labelLimit));
    }
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
      result.push({ label: part.length > 1 ? `${part[0].label}–${part[part.length - 1].label}` : part[0].label,
        value: part.reduce((sum, point) => sum + Number(point.value || 0), 0), partial: part.some(point => point.partial) });
    }
    return result;
  };
  const drawBars = (canvas, points) => {
    if (!points.length) return showChartEmpty(canvas, 'No hay ventas confirmadas en este intervalo.');
    const rows = compressBars(points);
    const values = rows.map((point) => Number(point.value || 0));
    let minimum = Math.min(0, ...values); let maximum = Math.max(0, ...values);
    if (minimum === maximum) { minimum = -1; maximum = 1; }
    else {
      const padding = (maximum - minimum) * 0.08;
      if (minimum < 0) minimum -= padding;
      if (maximum > 0) maximum += padding;
    }
    const { context, width, height } = fitCanvas(canvas);
    const frame = chartFrame(context, width, height, minimum, maximum, rows.map((point) => point.label), true);
    const zeroY = frame.y(0);
    context.strokeStyle = chartColors.text; context.lineWidth = 1;
    context.beginPath(); context.moveTo(frame.pad.left, zeroY); context.lineTo(width - frame.pad.right, zeroY); context.stroke();
    const slot = frame.plotW / rows.length;
    const barWidth = Math.max(1, slot * 0.62);
    rows.forEach((point, index) => {
      const value = Number(point.value || 0);
      const top = frame.y(Math.max(0, value));
      const bottom = frame.y(Math.min(0, value));
      context.fillStyle = value < 0 ? chartColors.cost : chartColors.revenue;
      context.globalAlpha = point.partial ? 0.45 : 1;
      context.fillRect(frame.pad.left + slot * index + (slot - barWidth) / 2, top, barWidth, Math.max(0, bottom - top));
    });
    context.globalAlpha = 1;
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
    const metrics = ['revenue', 'cost', 'profit'];
    const series = metrics.flatMap(metric => [
      { color: chartColors[metric], points: points.length === 1, values: points.map(point => point.partial ? null : point[metric]) },
      { color: chartColors[metric], points: points.length === 1, dashed: true, values: points.map((point, index) =>
        point.partial || points[index - 1]?.partial || points[index + 1]?.partial ? point[metric] : null) },
    ]);
    drawLines(canvas, points.map(point => point.label), series);
  };
  const drawModels = (canvas, data) => {
    const labels = Array.isArray(data.labels) ? data.labels.slice() : [];
    const actual = Array.isArray(data.actual) ? data.actual.slice() : [];
    if (!labels.length) return showChartEmpty(canvas, 'No hay períodos completos para ajustar los modelos.');
    const forecastCount = Math.max((data.linear_forecast || []).length, (data.exponential_forecast || []).length);
    for (let i = 0; i < forecastCount; i += 1) labels.push(`P+${i + 1}`);
    const pad = (values) => [...values, ...new Array(Math.max(0, labels.length - values.length)).fill(null)];
    const projection = (values, fitted) => {
      const result = new Array(labels.length).fill(null);
      if (values.length && fitted.length) result[actual.length - 1] = fitted[fitted.length - 1];
      values.forEach((value, index) => { result[actual.length + index] = value; });
      return result;
    };
    drawLines(canvas, labels, [
      { name: 'Histórico', color: chartColors.revenue, values: pad(actual), points: true },
      { name: 'Lineal', color: chartColors.linear, values: pad(data.linear || []) },
      { name: 'Exponencial', color: chartColors.exponential, values: pad(data.exponential || []) },
      { name: 'Proyección lineal', color: chartColors.linear, values: projection(data.linear_forecast || [], data.linear || []), dashed: true },
      { name: 'Proyección exponencial', color: chartColors.exponential, values: projection(data.exponential_forecast || [], data.exponential || []), dashed: true },
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
