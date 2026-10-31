App.ready.then(async (user) => {
    const { $, esc, peso, num, icon, labels } = App;
    const canVoid = user.role !== 'cashier';
    const settings = await App.settings();
    let page = 1;
    let rows = [];

    const params = new URLSearchParams(location.search);
    if (params.get('invoice')) $('#q').value = params.get('invoice');

    async function load(autoOpen = false) {
        $('#rows').innerHTML = App.loadingRows(9);
        const res = await App.api('/sales', {
            query: { page, search: $('#q').value.trim(), status: $('#status').value, payment_method: $('#method').value, from: $('#from').value, to: $('#to').value },
        });
        if (!res.ok) return;
        rows = res.data.data;

        $('#rows').innerHTML = rows.length
            ? rows.map((s) => `<tr class="clickable" data-id="${s.id}">
                <td><b class="num">${esc(s.invoice_no)}</b></td>
                <td>${App.datetime(s.created_at)}</td>
                <td>${esc(s.customer_name || 'Walk-in')}${s.discount_type !== 'none' ? ` <span class="tag off">${s.discount_type.toUpperCase()}</span>` : ''}</td>
                <td>${esc(s.user?.name ?? '—')}</td>
                <td class="r num">${num(s.items_count)}</td>
                <td>${labels.payment[s.payment_method]}</td>
                <td class="r num"><b>${peso(s.total)}</b></td>
                <td><span class="pill ${s.status === 'paid' ? 'ok' : 'bad'}">${s.status === 'paid' ? 'Paid' : 'Voided'}</span></td>
                <td><div class="acts"><button class="act" title="View">${icon('eye')}</button></div></td>
            </tr>`).join('')
            : App.emptyRow(9, 'No transactions match your filters.', 'receipt');

        App.pager($('#pager'), res.data, (p) => {
            page = p;
            load();
        });

        if (autoOpen && rows.length === 1) open(rows[0].id);
    }

    async function open(id) {
        const res = await App.api(`/sales/${id}`);
        if (!res.ok) return;
        const sale = res.data;
        const voidable = canVoid && sale.status === 'paid';

        const m = App.modal({
            title: `Invoice ${sale.invoice_no}`,
            size: 'sm',
            body: `${sale.status === 'voided' ? `<div class="note-box">Voided by ${esc(sale.voider?.name ?? '—')} on ${App.datetime(sale.voided_at)}. Reason: ${esc(sale.void_reason)}</div>` : ''}${App.receipt(sale, settings)}`,
            footer: `${voidable ? `<button type="button" class="btn left" id="t-void" style="color:var(--bad)">${icon('ban')}Void sale</button>` : ''}
                <button type="button" class="btn" id="t-print">${icon('printer')}Print</button>
                <button type="button" class="btn primary" data-close>Close</button>`,
            onOpen: (f) => {
                f.querySelector('#t-print').addEventListener('click', () => App.printReceipt(sale, settings));
                f.querySelector('#t-void')?.addEventListener('click', () => voidSale(sale, m));
            },
        });
    }

    async function voidSale(sale, m) {
        const reason = await App.confirm({
            title: `Void ${sale.invoice_no}?`,
            message: `The ${peso(sale.total)} sale will be cancelled and all items returned to stock. This cannot be undone.`,
            confirmText: 'Void sale',
            reason: 'Reason for voiding',
        });
        if (!reason) return;

        const res = await App.api(`/sales/${sale.id}/void`, { method: 'POST', body: { reason } });
        if (!res.ok) return App.fail(null, res);
        App.toast(`${sale.invoice_no} has been voided.`);
        m.close();
        load();
    }

    $('#rows').addEventListener('click', (e) => {
        const tr = e.target.closest('tr[data-id]');
        if (tr) open(+tr.dataset.id);
    });

    const reload = () => {
        page = 1;
        load();
    };
    $('#q').addEventListener('input', App.debounce(reload));
    ['#status', '#method', '#from', '#to'].forEach((s) => $(s).addEventListener('change', reload));

    load(!!params.get('invoice'));
});
