/**
 * Shared shell for every signed-in page: auth guard, permission-based nav, API wrapper,
 * and the small UI kit (modals, toasts, form helpers, receipt) the page scripts use.
 */
const App = (() => {
    const $ = (sel, root = document) => root.querySelector(sel);

    /* ---------- Formatting ---------- */
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const num = (n) => Number(n || 0).toLocaleString('en-PH');
    const peso = (n, dec = 2) => '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    const date = (d) => (d ? new Date(d.length === 10 ? d + 'T00:00:00' : d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—');
    const datetime = (d) => (d ? new Date(d).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—');
    const icon = (name) => `<svg class="i"><use href="#i-${name}"/></svg>`;
    const thumb = (m, cls = '') => (m.image_url
        ? `<img class="thumb ${cls}" src="${esc(m.image_url)}" alt="" loading="lazy">`
        : `<span class="thumb ${cls}">${icon('pill')}</span>`);
    const initials = (name) => String(name || '?').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();
    const daysUntil = (d) => Math.round((new Date(d + 'T00:00:00') - new Date(new Date().toDateString())) / 864e5);
    const debounce = (fn, ms = 300) => {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), ms);
        };
    };
    const labels = {
        payment: { cash: 'Cash', gcash: 'GCash', card: 'Card' },
        discount: { none: 'None', senior: 'Senior citizen', pwd: 'PWD' },
    };

    /* ---------- API ---------- */
    async function api(path, { method = 'GET', body, query } = {}) {
        if (query) {
            const q = new URLSearchParams(Object.entries(query).filter(([, v]) => v !== '' && v != null && v !== false));
            if ([...q].length) path += '?' + q;
        }

        let res;
        try {
            res = await Auth.api(path, { method, body: body === undefined || body instanceof FormData ? body : JSON.stringify(body) });
        } catch (e) {
            toast('Cannot reach the server. Check your connection.', 'bad');
            return { ok: false, status: 0, data: {} };
        }

        if (res.status === 401) {
            Auth.clear();
            location.href = '/';
        } else if (res.status === 403) {
            toast(res.data.message || 'You do not have access to this feature.', 'bad');
        } else if (res.status >= 500) {
            toast('Something went wrong on the server. Please try again.', 'bad');
        }

        return res;
    }

    /* ---------- Toasts ---------- */
    function toast(message, type = 'ok') {
        const el = document.createElement('div');
        el.className = 'toast ' + type;
        el.innerHTML = icon(type === 'ok' ? 'check' : 'alert') + `<span>${esc(message)}</span>`;
        $('#toasts').append(el);
        setTimeout(() => {
            el.style.opacity = 0;
            setTimeout(() => el.remove(), 300);
        }, 3200);
    }

    /* ---------- Forms ---------- */
    const field = (label, name, control, { full = false, hint = '' } = {}) =>
        `<label class="f ${full ? 'full' : ''}"><span>${label}</span>${control}${hint ? `<small class="muted">${hint}</small>` : ''}<small class="f-err" data-err="${name}"></small></label>`;

    const input = (name, attrs = '') => `<input class="input" name="${name}" ${attrs}>`;

    const select = (name, options, attrs = '') =>
        `<select class="input" name="${name}" ${attrs}>${options.map(([v, l]) => `<option value="${esc(v)}">${esc(l)}</option>`).join('')}</select>`;

    function read(form) {
        const data = {};
        form.querySelectorAll('[name]').forEach((el) => {
            if (el.type === 'checkbox') data[el.name] = el.checked;
            else data[el.name] = el.value.trim() === '' ? null : el.value.trim();
        });
        return data;
    }

    function fill(form, data) {
        Object.entries(data || {}).forEach(([key, value]) => {
            const el = form.querySelector(`[name="${key}"]`);
            if (!el) return;
            if (el.type === 'checkbox') el.checked = !!value;
            else el.value = value ?? '';
        });
    }

    function clearErrors(form) {
        form.querySelectorAll('.f-err').forEach((el) => (el.textContent = ''));
        form.querySelectorAll('.invalid').forEach((el) => el.classList.remove('invalid'));
    }

    /**
     * Show a failed API response on the form. Returns false so a modal stays open.
     */
    function fail(form, res) {
        if (res.status === 422 && res.data.errors) {
            let unplaced = null;
            Object.entries(res.data.errors).forEach(([key, messages]) => {
                const slot = form?.querySelector(`[data-err="${key}"]`);
                form?.querySelector(`[name="${key}"]`)?.classList.add('invalid');
                if (slot) slot.textContent = messages[0];
                else unplaced ??= messages[0];
            });
            if (unplaced) toast(unplaced, 'bad');
        } else if (res.status && res.status !== 403 && res.status < 500) {
            toast(res.data.message || 'Request failed.', 'bad');
        }
        return false;
    }

    /* ---------- Modals ---------- */
    function modal({ title, body, submit = 'Save', cancel = 'Cancel', size = '', danger = false, footer, onSubmit, onOpen }) {
        const bg = document.createElement('div');
        bg.className = 'modal-bg';
        bg.innerHTML = `<form class="modal ${size}" novalidate>
            <div class="modal-h"><h3>${esc(title)}</h3><button type="button" class="x-btn" data-close aria-label="Close">${icon('x')}</button></div>
            <div class="modal-b">${body}</div>
            <div class="modal-f">${footer ?? `<button type="button" class="btn" data-close>${cancel}</button>${submit ? `<button class="btn ${danger ? 'danger' : 'primary'}" type="submit">${submit}</button>` : ''}`}</div>
        </form>`;
        document.body.append(bg);

        const form = bg.firstElementChild;
        const onKey = (e) => e.key === 'Escape' && close();
        const close = () => {
            bg.remove();
            document.removeEventListener('keydown', onKey);
        };

        document.addEventListener('keydown', onKey);
        bg.addEventListener('mousedown', (e) => e.target === bg && close());
        bg.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', close));
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!onSubmit) return close();
            const btn = form.querySelector('[type=submit]');
            if (btn) btn.disabled = true;
            clearErrors(form);
            try {
                if ((await onSubmit(form, close)) !== false) close();
            } finally {
                if (btn) btn.disabled = false;
            }
        });

        onOpen?.(form, close);
        setTimeout(() => form.querySelector('.modal-b input:not([type=hidden]):not([type=checkbox]), .modal-b select, .modal-b textarea')?.focus(), 40);
        return { form, close };
    }

    /**
     * Styled replacement for window.confirm. With `reason`, asks for a required note
     * and resolves to it; otherwise resolves to true / false.
     */
    function confirm({ title, message, confirmText = 'Delete', danger = true, reason = false }) {
        return new Promise((resolve) => {
            let answered = false;
            const m = modal({
                title,
                size: 'sm',
                submit: confirmText,
                danger,
                body: `<p class="muted" style="margin-bottom:${reason ? 14 : 0}px">${message}</p>${reason ? field(reason, 'reason', input('reason', 'required')) : ''}`,
                onSubmit: (form) => {
                    const value = reason ? read(form).reason : true;
                    if (reason && !value) {
                        form.querySelector('[data-err=reason]').textContent = 'Please enter a reason.';
                        return false;
                    }
                    answered = true;
                    resolve(value);
                },
            });
            const observer = new MutationObserver(() => {
                if (!document.body.contains(m.form)) {
                    observer.disconnect();
                    if (!answered) resolve(false);
                }
            });
            observer.observe(document.body, { childList: true });
        });
    }

    /* ---------- Tables ---------- */
    const emptyRow = (cols, text, ic = 'search') => `<tr class="empty-row"><td colspan="${cols}">${icon(ic)}${text}</td></tr>`;
    const loadingRows = (cols, rows = 5) => Array.from({ length: rows }, () => `<tr>${'<td><span class="skeleton"></span></td>'.repeat(cols)}</tr>`).join('');

    function pager(el, page, go) {
        if (!page || page.total === 0) return (el.innerHTML = '');
        el.innerHTML = `<span>Showing ${num(page.from)}–${num(page.to)} of ${num(page.total)}</span>
            <div><button class="btn sm" data-p="${page.current_page - 1}" ${page.current_page <= 1 ? 'disabled' : ''}>Previous</button>
            <button class="btn sm" data-p="${page.current_page + 1}" ${page.current_page >= page.last_page ? 'disabled' : ''}>Next</button></div>`;
        el.querySelectorAll('[data-p]').forEach((b) => b.addEventListener('click', () => go(Number(b.dataset.p))));
    }

    /* ---------- Images ---------- */
    const fileSize = (bytes) => (bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB');

    /**
     * Shrink a photo in the browser before uploading (the server optimizes again).
     * Falls back to the original file if the browser can't do it or it wouldn't help.
     */
    async function compressImage(file, { maxSide = 1200, quality = 0.85 } = {}) {
        try {
            const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
            const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(bitmap.width * scale);
            canvas.height = Math.round(bitmap.height * scale);
            canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            bitmap.close();

            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', quality));
            if (!blob || blob.size >= file.size) return file;
            return new File([blob], file.name.replace(/\.\w+$/, '') + '.webp', { type: 'image/webp' });
        } catch (e) {
            return file;
        }
    }

    /* ---------- Settings & receipt ---------- */
    let settingsPromise;
    const settings = (fresh = false) => {
        if (fresh || !settingsPromise) settingsPromise = api('/settings').then((r) => r.data);
        return settingsPromise;
    };

    function receipt(sale, st) {
        const ln = (l, r, cls = '') => `<div class="ln ${cls}"><span>${l}</span><span>${r}</span></div>`;
        // A line can be split across batches; merge them back for the customer.
        const lines = Object.values(sale.items.reduce((acc, it) => {
            const key = it.name + '|' + it.price;
            acc[key] ??= { name: it.name, price: +it.price, quantity: 0, total: 0 };
            acc[key].quantity += it.quantity;
            acc[key].total += +it.total;
            return acc;
        }, {}));
        const discounted = sale.discount_type !== 'none';
        const vatExempt = discounted ? +sale.subtotal - +sale.discount - +sale.total : 0;

        return `<div class="receipt">
            <div class="c"><h4>${esc(st.store_name)}</h4><div>${esc(st.store_address)}</div><div>Tel. ${esc(st.store_phone)}</div><div>VAT REG TIN ${esc(st.store_tin)}</div></div>
            <hr>
            ${ln('Invoice', esc(sale.invoice_no))}
            ${ln('Date', datetime(sale.created_at))}
            ${ln('Cashier', esc(sale.user?.name ?? '—'))}
            ${sale.customer_name ? ln('Customer', esc(sale.customer_name)) : ''}
            ${sale.prescription_ref ? ln('Rx ref', esc(sale.prescription_ref)) : ''}
            <hr>
            ${lines.map((l) => `<div>${esc(l.name)}</div>${ln(`&nbsp;&nbsp;${l.quantity} × ${peso(l.price)}`, peso(l.total))}`).join('')}
            <hr>
            ${ln('Subtotal', peso(sale.subtotal))}
            ${discounted
                ? ln('Less: VAT exemption', '-' + peso(vatExempt)) + ln(`Less: ${labels.discount[sale.discount_type]} (${st.senior_discount_rate}%)`, '-' + peso(sale.discount)) + ln('ID no.', esc(sale.discount_id_no))
                : ln('VATable sales', peso(sale.total - sale.vat)) + ln(`VAT (${st.vat_rate}%)`, peso(sale.vat))}
            ${ln('<b>TOTAL</b>', `<b>${peso(sale.total)}</b>`, 'big')}
            ${ln(labels.payment[sale.payment_method], peso(sale.amount_paid))}
            ${sale.payment_method === 'cash' ? ln('Change', peso(sale.change)) : ln('Ref no.', esc(sale.reference_no))}
            ${sale.status === 'voided' ? `<hr><div class="c void">*** VOIDED ***</div><div class="c">${esc(sale.void_reason)}</div>` : ''}
            <hr>
            <div class="c">${esc(st.receipt_footer)}</div>
            <div class="c muted">This serves as your official receipt.</div>
        </div>`;
    }

    function printReceipt(sale, st) {
        $('#print-area').innerHTML = receipt(sale, st);
        window.print();
    }

    /* ---------- Shell chrome ---------- */
    function chrome() {
        $('#menu').addEventListener('click', () => $('#side').classList.toggle('open'));
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.side') && !e.target.closest('#menu')) $('#side').classList.remove('open');
            if (e.target.closest('#user-btn')) $('#menu-dd').classList.toggle('open');
            else $('#menu-dd').classList.remove('open');
        });
        $('#logout').addEventListener('click', async () => {
            await Auth.api('/logout', { method: 'POST' }).catch(() => {});
            Auth.clear();
            location.href = '/';
        });
    }

    // True when the user holds any of the comma-separated permissions (an empty list means everyone).
    let permissions = [];
    const can = (list = '') => !list || list.split(',').some((p) => permissions.includes(p.trim()));

    const ready = (async () => {
        chrome();
        const res = Auth.token() ? await api('/user') : { ok: false };
        if (!res.ok) {
            Auth.clear();
            location.href = '/';
            return new Promise(() => {});
        }

        const user = res.data.user;
        permissions = user.permissions;
        const shown = (menu) => !menu || user.menus.includes(menu);

        $('#name').textContent = user.name;
        $('#role').textContent = user.role_label;
        $('#avatar').textContent = initials(user.name);

        document.querySelectorAll('[data-can]:not(body)').forEach((el) => !can(el.dataset.can) && el.remove());
        document.querySelectorAll('[data-menu]:not(body)').forEach((el) => !shown(el.dataset.menu) && el.remove());

        // Inventory opens on the first tab this user may see, and disappears if there are none.
        const inv = $('#nav-inventory');
        const tab = JSON.parse(inv.dataset.tabs).find(([, perm, menu]) => can(perm) && shown(menu));
        tab ? (inv.href = tab[0]) : inv.remove();
        const tabs = $('.inv-tabs');
        if (tabs && tabs.children.length < 2) tabs.remove(); // a single tab is just noise

        // The API enforces every permission too; this keeps people off pages they can't use or whose
        // menu their role hides, sending them to the first page they do have (another tab first).
        if (!can(document.body.dataset.can) || !shown(document.body.dataset.menu)) {
            const home = $('.inv-tabs a') ?? $('#nav-inventory.on') ?? $('.nav a');
            if (home) {
                location.href = home.href;
                return new Promise(() => {});
            }
            $('.page').innerHTML = `<div class="card" style="text-align:center;padding:48px 20px">${icon('lock')}<h3 style="margin-top:10px">No pages assigned</h3><p class="muted">Your role has no menus yet. Please ask the super admin to give it access.</p></div>`;
            $('#splash').remove();
            return new Promise(() => {});
        }

        $('#splash').remove();

        if (can('stock.manage,medicines.manage')) {
            api('/medicines', { query: { stock: 'low', per_page: 1 } }).then((r) => {
                if (r.ok && r.data.total) {
                    document.querySelectorAll('.stock-badge').forEach((b) => {
                        b.textContent = r.data.total;
                        b.hidden = false;
                    });
                    $('#alert-dot').hidden = false;
                }
            });
        }

        return user;
    })();

    return {
        $, esc, num, peso, date, datetime, icon, thumb, initials, daysUntil, debounce, labels,
        api, toast, field, input, select, read, fill, clearErrors, fail, modal, confirm,
        emptyRow, loadingRows, pager, fileSize, compressImage, settings, receipt, printReceipt, can, ready,
    };
})();
