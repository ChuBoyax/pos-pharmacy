App.ready.then(async () => {
    const { $, esc, peso, icon, num } = App;
    const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;

    const settings = await App.settings();
    const cart = new Map(); // medicine id -> { medicine, qty }
    let products = [];
    let category = '';
    let method = 'cash';
    const form = $('#checkout');

    /* ---------- Catalogue ---------- */
    const chips = $('#chips');

    App.api('/categories').then((r) => {
        if (!r.ok) return;
        chips.insertAdjacentHTML('beforeend', r.data.map((c) => `<button data-c="${c.id}">${esc(c.name)}</button>`).join(''));
        syncChipNav();
    });

    // The category bar scrolls sideways: arrows, mouse wheel and click-drag on desktop, swipe on touch.
    function syncChipNav() {
        const max = chips.scrollWidth - chips.clientWidth;
        const left = chips.scrollLeft > 2;
        const right = chips.scrollLeft < max - 2;
        $('#chip-prev').hidden = !left;
        $('#chip-next').hidden = !right;
        $('#chips-wrap').classList.toggle('fade-l', left);
        $('#chips-wrap').classList.toggle('fade-r', right);
    }
    chips.addEventListener('scroll', syncChipNav, { passive: true });
    new ResizeObserver(syncChipNav).observe(chips);
    $('#chip-prev').addEventListener('click', () => chips.scrollBy({ left: -chips.clientWidth * 0.7, behavior: 'smooth' }));
    $('#chip-next').addEventListener('click', () => chips.scrollBy({ left: chips.clientWidth * 0.7, behavior: 'smooth' }));
    chips.addEventListener('wheel', (e) => {
        if (Math.abs(e.deltaY) <= Math.abs(e.deltaX) || chips.scrollWidth <= chips.clientWidth) return;
        e.preventDefault();
        chips.scrollLeft += e.deltaY;
    }, { passive: false });

    let drag = null;
    let dragged = false;
    chips.addEventListener('pointerdown', (e) => {
        if (e.pointerType !== 'mouse' || e.button !== 0) return;
        drag = { x: e.clientX, left: chips.scrollLeft };
        dragged = false;
    });
    window.addEventListener('pointermove', (e) => {
        if (!drag) return;
        const dx = e.clientX - drag.x;
        if (Math.abs(dx) > 5) {
            dragged = true;
            chips.classList.add('dragging');
        }
        if (dragged) chips.scrollLeft = drag.left - dx;
    });
    window.addEventListener('pointerup', () => {
        drag = null;
        chips.classList.remove('dragging');
    });

    chips.addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (dragged) return (dragged = false); // a drag shouldn't also pick a category
        if (!b) return;
        category = b.dataset.c;
        [...chips.children].forEach((c) => c.classList.toggle('on', c === b));
        b.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
        loadProducts();
    });

    async function loadProducts() {
        const r = await App.api('/medicines', { query: { sellable: 1, per_page: 60, search: $('#q').value.trim(), category_id: category } });
        if (!r.ok) return;
        products = r.data.data;
        drawProducts();
    }

    function drawProducts() {
        if (!products.length) {
            $('#products').innerHTML = `<div class="card cart-empty" style="grid-column:1/-1">${icon('search')}No products match your search, or they are out of stock.</div>`;
            return;
        }
        $('#products').innerHTML = products.map((m) => {
            const inCart = cart.get(m.id)?.qty ?? 0;
            const left = m.stock - inCart;
            return `<button class="prod ${inCart ? 'in-cart' : ''}" data-id="${m.id}" title="${esc(m.name)}${m.generic_name ? ' — ' + esc(m.generic_name) : ''}" ${left <= 0 ? 'disabled' : ''}>
                ${inCart ? `<span class="qty-badge">${inCart}</span>` : ''}
                ${m.requires_prescription ? '<span class="tag rx">RX</span>' : ''}
                ${App.thumb(m)}
                <b>${esc(m.name)}</b>
                <small>${esc(m.generic_name ?? '')}</small>
                <div class="foot2">
                    <span class="price num">${peso(m.price)}</span>
                    <span class="stk ${left <= m.reorder_level ? 'low' : ''}">${num(left)} ${esc(m.unit)}</span>
                </div>
            </button>`;
        }).join('');
    }

    $('#products').addEventListener('click', (e) => {
        const b = e.target.closest('.prod');
        if (b) add(products.find((p) => p.id === +b.dataset.id));
    });

    $('#q').addEventListener('input', App.debounce(loadProducts, 250));
    $('#q').addEventListener('keydown', async (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        // Barcode scanners type the SKU and press Enter: add an exact match straight away.
        const term = $('#q').value.trim().toLowerCase();
        await loadProducts();
        const match = products.find((p) => p.sku.toLowerCase() === term) ?? products[0];
        if (match && term) {
            add(match);
            $('#q').value = '';
            loadProducts();
        }
    });

    document.addEventListener('keydown', (e) => {
        if ((e.key === '/' || e.key === 'F2') && !/input|textarea|select/i.test(document.activeElement.tagName)) {
            e.preventDefault();
            $('#q').focus();
        }
    });

    /* ---------- Cart ---------- */
    function add(medicine, qty = 1) {
        if (!medicine) return;
        const line = cart.get(medicine.id) ?? { medicine, qty: 0 };
        if (line.qty + qty > medicine.stock) return App.toast(`Only ${medicine.stock} ${medicine.unit}(s) of ${medicine.name} in stock.`, 'bad');
        line.qty += qty;
        cart.set(medicine.id, line);
        render();
    }

    function setQty(id, qty) {
        const line = cart.get(id);
        if (!line) return;
        qty = Math.max(0, Math.floor(qty) || 0);
        if (qty > line.medicine.stock) {
            App.toast(`Only ${line.medicine.stock} ${line.medicine.unit}(s) available.`, 'bad');
            qty = line.medicine.stock;
        }
        qty === 0 ? cart.delete(id) : (line.qty = qty);
        render();
    }

    $('#cart').addEventListener('click', (e) => {
        const b = e.target.closest('[data-act]');
        if (!b) return;
        const id = +b.closest('.ci').dataset.id;
        const line = cart.get(id);
        if (b.dataset.act === 'inc') setQty(id, line.qty + 1);
        if (b.dataset.act === 'dec') setQty(id, line.qty - 1);
        if (b.dataset.act === 'del') setQty(id, 0);
    });
    $('#cart').addEventListener('change', (e) => {
        if (e.target.matches('.qty input')) setQty(+e.target.closest('.ci').dataset.id, +e.target.value);
    });
    $('#clear').addEventListener('click', () => {
        cart.clear();
        render();
    });

    /* ---------- Totals (mirrors App\Services\SaleService::totals) ---------- */
    function totals() {
        const subtotal = round2([...cart.values()].reduce((a, l) => a + l.medicine.price * l.qty, 0));
        const vatRate = +settings.vat_rate / 100;
        if ($('#discount').value === 'none') {
            return { subtotal, vatExempt: 0, discount: 0, vat: round2(subtotal - subtotal / (1 + vatRate)), total: subtotal };
        }
        const net = round2(subtotal / (1 + vatRate));
        const discount = round2((net * +settings.senior_discount_rate) / 100);
        return { subtotal, vatExempt: round2(subtotal - net), discount, vat: 0, total: round2(net - discount) };
    }

    function render() {
        const lines = [...cart.values()];
        const units = lines.reduce((a, l) => a + l.qty, 0);
        $('#cart-count').textContent = units ? `· ${units} item${units > 1 ? 's' : ''}` : '';

        $('#cart').innerHTML = lines.length
            ? lines.map(({ medicine: m, qty }) => `<div class="ci" data-id="${m.id}">
                <div class="grow"><b>${esc(m.name)} ${m.requires_prescription ? '<span class="tag rx">RX</span>' : ''}</b><small>${peso(m.price)} / ${esc(m.unit)}</small></div>
                <div class="qty">
                    <button type="button" data-act="dec" aria-label="Decrease">${icon('minus')}</button>
                    <input type="number" min="0" value="${qty}" aria-label="Quantity">
                    <button type="button" data-act="inc" aria-label="Increase">${icon('plus')}</button>
                </div>
                <span class="lt num">${peso(m.price * qty)}</span>
                <button type="button" class="act del" data-act="del" aria-label="Remove">${icon('x')}</button>
            </div>`).join('')
            : `<div class="cart-empty">${icon('cart')}Cart is empty.<br>Tap a product to add it.</div>`;

        const discounted = $('#discount').value !== 'none';
        const t = totals();
        $('#id-field').hidden = !discounted;
        $('#customer-label').textContent = discounted ? 'Customer name' : 'Customer (optional)';
        $('#rx-field').hidden = !lines.some((l) => l.medicine.requires_prescription);
        if (!$('#rx-field').hidden) $('#more').open = true; // Rx needs a reference, so surface it

        const row = (l, r) => `<div><span>${l}</span><span class="num">${r}</span></div>`;
        $('#sum').innerHTML = (discounted
            ? row('Subtotal', peso(t.subtotal)) + row('Less: VAT exemption', '−' + peso(t.vatExempt)) + row(`Less: ${App.labels.discount[$('#discount').value]} ${settings.senior_discount_rate}%`, '−' + peso(t.discount))
            : row(`Includes VAT (${settings.vat_rate}%)`, peso(t.vat)))
            + `<div class="total"><span>Total due</span><span class="num">${peso(t.total)}</span></div>`;

        // Quick-cash buttons: the next round bills above the total ("Exact" lives on the keypad).
        const bills = [...new Set([20, 50, 100, 500, 1000].map((b) => Math.ceil(t.total / b) * b))].filter((v) => v > t.total).slice(0, 4);
        $('#quick').innerHTML = bills.map((v) => `<button type="button" data-v="${v}">${peso(v, 0)}</button>`).join('');
        $('#quick').hidden = !bills.length || !t.total;

        const tendered = +$('#tendered').value || 0;
        const change = round2(tendered - t.total);
        const short = method === 'cash' && change < 0;
        $('#change').classList.toggle('short', short && tendered > 0);
        $('#change').innerHTML = `<span>${short && tendered > 0 ? 'Short by' : 'Change'}</span><span class="num">${peso(Math.abs(tendered ? change : 0))}</span>`;

        $('#charge').disabled = !lines.length || short;
        $('#charge').textContent = `Charge ${peso(t.total)}`;
        drawProducts();
    }

    $('#discount').addEventListener('change', render);
    $('#tendered').addEventListener('input', () => {
        // Typing on a physical keyboard: keep digits and one decimal point, max 2 decimals.
        const el = $('#tendered');
        const [whole, ...rest] = el.value.replace(/[^\d.]/g, '').split('.');
        el.value = rest.length ? `${whole}.${rest.join('').slice(0, 2)}` : whole;
        render();
    });
    $('#keypad').addEventListener('click', (e) => {
        const key = e.target.closest('button')?.dataset.k;
        if (!key) return;
        let v = $('#tendered').value;

        if (key === 'back') v = v.slice(0, -1);
        else if (key === 'clear') v = '';
        else if (key === 'exact') v = totals().total.toFixed(2);
        else if (key === '.') v = v.includes('.') ? v : (v || '0') + '.';
        else {
            const next = (v === '0' ? '' : v) + key;
            if (/\.\d{3,}$/.test(next) || next.replace('.', '').length > 9) return;
            v = next;
        }

        $('#tendered').value = v;
        render();
    });
    $('#quick').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        $('#tendered').value = (+b.dataset.v).toFixed(2);
        render();
    });
    $('#pay').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        method = b.dataset.m;
        [...$('#pay').children].forEach((c) => c.classList.toggle('on', c === b));
        $('#pay-cash').hidden = method !== 'cash';
        $('#pay-ref').hidden = method === 'cash';
        render();
    });

    /* ---------- Checkout ---------- */
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!cart.size) return;
        App.clearErrors(form);
        $('#charge').disabled = true;
        $('#charge').textContent = 'Processing…';

        const data = App.read(form);
        const res = await App.api('/sales', {
            method: 'POST',
            body: {
                ...data,
                payment_method: method,
                amount_paid: method === 'cash' ? data.amount_paid : null,
                reference_no: method === 'cash' ? null : data.reference_no,
                items: [...cart.values()].map((l) => ({ medicine_id: l.medicine.id, quantity: l.qty })),
            },
        });

        if (!res.ok) {
            $('#more').open = true; // the field to fix may be inside
            App.fail(form, res);
            render();
            return;
        }

        App.toast(`Sale ${res.data.invoice_no} completed.`);
        cart.clear();
        form.reset();
        $('#more').open = false;
        $('#pay [data-m=cash]').click();
        loadProducts();
        showReceipt(res.data);
    });

    function showReceipt(sale) {
        App.modal({
            title: 'Sale completed',
            size: 'sm',
            body: `${sale.payment_method === 'cash' ? `<div class="change" style="margin-bottom:16px;font-size:16px"><span>Change due</span><span class="num">${peso(sale.change)}</span></div>` : ''}${App.receipt(sale, settings)}`,
            footer: `<button type="button" class="btn left" id="r-print">${icon('printer')}Print receipt</button><button type="button" class="btn primary" data-close>New sale</button>`,
            onOpen: (f) => f.querySelector('#r-print').addEventListener('click', () => App.printReceipt(sale, settings)),
        });
        $('#q').focus();
    }

    render();
    loadProducts();
    $('#q').focus();
});
