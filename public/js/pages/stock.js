App.ready.then(async () => {
    const { $, esc, peso, num, icon, field, input, select } = App;
    const settings = await App.settings();
    const warnDays = +settings.expiry_warning_days;
    const reasons = ['Physical count correction', 'Damaged / broken', 'Expired - disposed', 'Returned to supplier', 'Lost / missing', 'Other'];
    let status = 'active';
    let page = 1;
    let rows = [];

    const params = new URLSearchParams(location.search);
    if (params.get('status')) status = params.get('status');
    [...$('#tabs').children].forEach((b) => b.classList.toggle('on', b.dataset.s === status));

    async function load() {
        $('#rows').innerHTML = App.loadingRows(8);
        const res = await App.api('/batches', { query: { page, status, search: $('#q').value.trim() } });
        if (!res.ok) return;
        rows = res.data.data;

        $('#rows').innerHTML = rows.length
            ? rows.map((b) => {
                const days = App.daysUntil(b.expiry_date);
                const exp = days <= 0 ? ['bad', 'Expired'] : days <= 30 ? ['bad', `${days}d left`] : days <= warnDays ? ['warn', `${days}d left`] : ['ok', `${Math.round(days / 30)} mo left`];
                const pct = b.quantity_received ? Math.round((b.quantity / b.quantity_received) * 100) : 0;
                return `<tr data-id="${b.id}">
                    <td class="wrap"><b>${esc(b.medicine?.name)}</b><br><small class="muted">${esc(b.medicine?.sku)}</small></td>
                    <td class="num">${esc(b.batch_no)}</td>
                    <td>${esc(b.supplier?.name ?? '—')}</td>
                    <td>${App.date(b.received_at)}</td>
                    <td>${App.date(b.expiry_date)}<br><span class="pill ${exp[0]}">${exp[1]}</span></td>
                    <td style="min-width:130px"><b class="num">${num(b.quantity)}</b> <small class="muted">/ ${num(b.quantity_received)} ${esc(b.medicine?.unit)}</small>
                        <div class="bar-m"><i style="width:${pct}%;background:var(--brand)"></i></div></td>
                    <td class="r num">${peso(b.cost)}</td>
                    <td><div class="acts">
                        <button class="act" data-act="adjust" title="Adjust quantity">${icon('edit')}</button>
                        <button class="act" data-act="history" title="Adjustment history">${icon('history')}</button>
                    </div></td>
                </tr>`;
            }).join('')
            : App.emptyRow(8, status === 'expired' ? 'No expired stock on hand.' : 'No batches here.', 'box');

        App.pager($('#pager'), res.data, (p) => {
            page = p;
            load();
        });
    }

    async function receive(preselect = null) {
        const [meds, sups] = await Promise.all([App.api('/medicines', { query: { per_page: 100 } }), App.api('/suppliers', { query: { all: 1 } })]);
        const medicines = meds.ok ? meds.data.data : [];
        const today = new Date().toLocaleDateString('en-CA');

        App.modal({
            title: 'Receive stock',
            submit: 'Add to inventory',
            body: `<div class="form">
                ${field('Medicine', 'medicine_id', select('medicine_id', [['', 'Select a medicine…'], ...medicines.map((m) => [m.id, `${m.name} (${m.sku})`])], 'required'), { full: true })}
                ${field('Supplier', 'supplier_id', select('supplier_id', [['', '— None —'], ...(sups.ok ? sups.data : []).map((s) => [s.id, s.name])]))}
                ${field('Batch / lot no.', 'batch_no', input('batch_no', 'required placeholder="e.g. B24091"'))}
                ${field('Quantity received', 'quantity', input('quantity', 'type="number" min="1" required'))}
                ${field('Unit cost', 'cost', input('cost', 'type="number" min="0" step="0.01"'))}
                ${field('Expiry date', 'expiry_date', input('expiry_date', `type="date" min="${today}" required`))}
                ${field('Date received', 'received_at', input('received_at', `type="date" max="${today}" value="${today}"`))}
            </div>`,
            onOpen: (form) => {
                const sync = () => {
                    const m = medicines.find((x) => x.id === +form.medicine_id.value);
                    if (!m) return;
                    if (m.supplier_id) form.supplier_id.value = m.supplier_id;
                    form.cost.value = m.cost;
                };
                form.medicine_id.addEventListener('change', sync);
                if (preselect) {
                    form.medicine_id.value = preselect;
                    sync();
                }
            },
            onSubmit: async (form) => {
                const res = await App.api('/batches', { method: 'POST', body: App.read(form) });
                if (!res.ok) return App.fail(form, res);
                App.toast(`Received ${num(res.data.quantity)} × ${res.data.medicine.name}.`);
                status = 'recent';
                [...$('#tabs').children].forEach((b) => b.classList.toggle('on', b.dataset.s === status));
                page = 1;
                load();
            },
        });
    }

    function adjust(batch) {
        App.modal({
            title: `Adjust batch ${batch.batch_no}`,
            submit: 'Save adjustment',
            body: `<div class="note-box info">${esc(batch.medicine?.name)} — currently <b>${num(batch.quantity)}</b> ${esc(batch.medicine?.unit)}(s) on hand. Every change is logged with your name.</div>
                <div class="form">
                    ${field('New quantity on hand', 'quantity', input('quantity', `type="number" min="0" required value="${batch.quantity}"`))}
                    ${field('Expiry date', 'expiry_date', input('expiry_date', `type="date" value="${batch.expiry_date}"`))}
                    ${field('Reason', 'reason', select('reason_type', reasons.map((r) => [r, r])), { full: true })}
                    ${field('Notes', 'note', input('note', 'placeholder="Optional details"'), { full: true })}
                </div>`,
            onSubmit: async (form) => {
                const d = App.read(form);
                const res = await App.api(`/batches/${batch.id}`, {
                    method: 'PUT',
                    body: { quantity: d.quantity, expiry_date: d.expiry_date, reason: d.note ? `${d.reason_type}: ${d.note}` : d.reason_type },
                });
                if (!res.ok) return App.fail(form, res);
                App.toast('Stock adjusted.');
                load();
            },
        });
    }

    async function history(batch) {
        const res = await App.api(`/batches/${batch.id}/adjustments`);
        if (!res.ok) return;
        App.modal({
            title: `History · batch ${batch.batch_no}`,
            submit: null,
            cancel: 'Close',
            body: res.data.length
                ? `<ul class="list">${res.data.map((a) => {
                    const diff = a.quantity_after - a.quantity_before;
                    return `<li><div class="grow"><b>${esc(a.reason)}</b><small>${esc(a.user?.name ?? '—')} · ${App.datetime(a.created_at)}</small></div>
                        <span class="pill ${diff < 0 ? 'bad' : 'ok'}">${diff > 0 ? '+' : ''}${num(diff)}</span>
                        <small class="muted num">${num(a.quantity_before)} → ${num(a.quantity_after)}</small></li>`;
                }).join('')}</ul>`
                : `<p class="muted">No manual adjustments. Received ${num(batch.quantity_received)} on ${App.date(batch.received_at)}; ${num(batch.quantity_received - batch.quantity)} sold since.</p>`,
        });
    }

    $('#rows').addEventListener('click', (e) => {
        const b = e.target.closest('[data-act]');
        if (!b) return;
        const batch = rows.find((r) => r.id === +b.closest('tr').dataset.id);
        b.dataset.act === 'adjust' ? adjust(batch) : history(batch);
    });
    $('#tabs').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        status = b.dataset.s;
        [...$('#tabs').children].forEach((c) => c.classList.toggle('on', c === b));
        page = 1;
        load();
    });
    $('#q').addEventListener('input', App.debounce(() => {
        page = 1;
        load();
    }));
    $('#receive').addEventListener('click', () => receive());

    load();
    if (params.get('receive')) receive(params.get('receive'));
});
