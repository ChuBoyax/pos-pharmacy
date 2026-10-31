App.ready.then(() => {
    const { $, esc, num, peso, icon, field, input, select, initials } = App;
    let page = 1;
    let rows = [];

    async function load() {
        $('#rows').innerHTML = App.loadingRows(6);
        const res = await App.api('/suppliers', { query: { page, search: $('#q').value.trim() } });
        if (!res.ok) return;
        rows = res.data.data;

        $('#rows').innerHTML = rows.length
            ? rows.map((s) => `<tr data-id="${s.id}">
                <td class="wrap"><div class="cell"><span class="av t-brand">${initials(s.name)}</span><div><b>${esc(s.name)}</b><small>${esc(s.address ?? '')}</small></div></div></td>
                <td>${esc(s.contact_person ?? '—')}</td>
                <td>${s.phone ? `<a href="tel:${esc(s.phone)}">${esc(s.phone)}</a>` : '—'}</td>
                <td>${s.email ? `<a class="link" href="mailto:${esc(s.email)}">${esc(s.email)}</a>` : '—'}</td>
                <td class="r num">${num(s.medicines_count)}</td>
                <td><div class="acts">
                    <button class="act" data-act="edit" title="Edit">${icon('edit')}</button>
                    <button class="act del" data-act="del" title="Delete">${icon('trash')}</button>
                </div></td>
            </tr>`).join('')
            : App.emptyRow(6, 'No suppliers yet.', 'truck');

        App.pager($('#pager'), res.data, (p) => {
            page = p;
            load();
        });
    }

    function edit(supplier = null) {
        App.modal({
            title: supplier ? `Edit ${supplier.name}` : 'Add supplier',
            submit: supplier ? 'Save changes' : 'Add supplier',
            body: `<div class="form">
                ${field('Company name', 'name', input('name', 'required'), { full: true })}
                ${field('Contact person', 'contact_person', input('contact_person'))}
                ${field('Phone', 'phone', input('phone', 'type="tel"'))}
                ${field('Email', 'email', input('email', 'type="email"'), { full: true })}
                ${field('Address', 'address', input('address'), { full: true })}
            </div>`,
            onOpen: (form) => App.fill(form, supplier),
            onSubmit: async (form) => {
                const res = await App.api(supplier ? `/suppliers/${supplier.id}` : '/suppliers', { method: supplier ? 'PUT' : 'POST', body: App.read(form) });
                if (!res.ok) return App.fail(form, res);
                App.toast(supplier ? 'Supplier updated.' : 'Supplier added.');
                load();
            },
        });
    }

    async function remove(s) {
        const ok = await App.confirm({ title: `Delete ${s.name}?`, message: 'Medicines and batches linked to this supplier are kept; they just lose the supplier link.' });
        if (!ok) return;
        const res = await App.api(`/suppliers/${s.id}`, { method: 'DELETE' });
        if (!res.ok) return App.fail(null, res);
        App.toast('Supplier deleted.');
        load();
    }

    /* ---------- Price comparison ---------- */
    async function compare(medicineId = null) {
        const res = await App.api('/suppliers/compare');
        if (!res.ok) return;
        const overview = res.data;
        const pct = (part, whole) => (whole > 0 ? Math.round((part / whole) * 100) : 0);

        const m = App.modal({
            title: 'Compare supplier prices',
            size: 'lg',
            submit: null,
            cancel: 'Close',
            body: `<div class="toolbar" style="margin-bottom:14px">
                    <button type="button" class="btn sm" id="cmp-back" hidden>${icon('back')}All medicines</button>
                    ${select('cmp-medicine', [['', 'Choose a medicine…'], ...overview.map((r) => [r.medicine.id, `${r.medicine.name} (${r.suppliers} suppliers)`])], 'id="cmp-medicine" style="flex:1"')}
                </div>
                <div id="cmp"></div>`,
            onOpen: (form) => {
                form.querySelector('#cmp-medicine').addEventListener('change', (e) => (e.target.value ? detail(+e.target.value) : list()));
                form.querySelector('#cmp-back').addEventListener('click', list);
            },
        });
        const box = m.form.querySelector('#cmp');

        function list() {
            m.form.querySelector('#cmp-back').hidden = true;
            m.form.querySelector('#cmp-medicine').value = '';
            box.innerHTML = overview.length
                ? `<p class="muted" style="margin-bottom:12px">${num(overview.length)} medicines were bought from two or more suppliers. Biggest price gaps first; pick one to see the details.</p>
                <div class="table-scroll"><table class="tbl">
                    <thead><tr><th>Medicine</th><th class="r">Suppliers</th><th>Cheapest supplier</th><th class="r">Best cost</th><th class="r">Highest</th><th class="r">Save / unit</th></tr></thead>
                    <tbody>${overview.map((r) => `<tr class="clickable" data-med="${r.medicine.id}">
                        <td><b>${esc(r.medicine.name)}</b><br><small class="muted">per ${esc(r.medicine.unit)}</small></td>
                        <td class="r num">${r.suppliers}</td>
                        <td>${esc(r.best.supplier)}</td>
                        <td class="r num"><b>${peso(r.best.last_cost)}</b></td>
                        <td class="r num muted">${peso(r.highest_cost)}</td>
                        <td class="r num"><span class="pill ${r.saving_per_unit > 0 ? 'ok' : 'mute'}">${peso(r.saving_per_unit)} · ${pct(r.saving_per_unit, r.highest_cost)}%</span></td>
                    </tr>`).join('')}</tbody>
                </table></div>`
                : `<p class="muted">No medicine has been delivered by more than one supplier yet. Comparisons appear once you receive the same medicine from different suppliers in Stock.</p>`;
            box.querySelectorAll('[data-med]').forEach((tr) => tr.addEventListener('click', () => detail(+tr.dataset.med)));
        }

        async function detail(id) {
            m.form.querySelector('#cmp-back').hidden = false;
            m.form.querySelector('#cmp-medicine').value = id;
            box.innerHTML = `<span class="skeleton" style="height:120px"></span>`;
            const r = await App.api('/suppliers/compare', { query: { medicine_id: id } });
            if (!r.ok) return;
            const { medicine, suppliers: offers } = r.data;
            if (!offers.length) {
                box.innerHTML = `<p class="muted">${esc(medicine.name)} has no deliveries with a supplier recorded yet.</p>`;
                return;
            }

            const best = offers[0];
            const worst = offers[offers.length - 1];
            const freshest = Math.max(...offers.map((o) => o.avg_shelf_life_days));
            const gap = worst.last_cost - best.last_cost;

            box.innerHTML = `<div class="note-box info" style="display:flex;gap:12px;align-items:center">
                    <span class="kpi-ic t-ok" style="flex:none">${icon('check')}</span>
                    <div><b>${esc(best.supplier)}</b> has the best price for ${esc(medicine.name)} at <b>${peso(best.last_cost)}</b> per ${esc(medicine.unit)}.
                    ${offers.length > 1 && gap > 0 ? `That's ${peso(gap)} (${pct(gap, worst.last_cost)}%) cheaper than ${esc(worst.supplier)} — about <b>${peso(gap * 1000, 0)}</b> saved per 1,000 ${esc(medicine.unit)}s.` : ''}</div>
                </div>
                <div class="table-scroll"><table class="tbl">
                    <thead><tr><th>Supplier</th><th class="r">Latest cost</th><th class="r">vs best</th><th class="r">Avg cost</th><th class="r">Margin</th><th class="r">Deliveries</th><th class="r">Shelf life on arrival</th><th>Last delivery</th></tr></thead>
                    <tbody>${offers.map((o, i) => {
                        const diff = o.last_cost - best.last_cost;
                        return `<tr>
                            <td><b>${esc(o.supplier)}</b> ${i === 0 ? '<span class="pill ok">Best price</span>' : ''}<br><small class="muted">${peso(o.min_cost)} – ${peso(o.max_cost)} range</small></td>
                            <td class="r num"><b>${peso(o.last_cost)}</b></td>
                            <td class="r num">${i === 0 ? '—' : `<span style="color:var(--bad)">+${peso(diff)}<br><small>+${pct(diff, best.last_cost)}%</small></span>`}</td>
                            <td class="r num">${peso(o.avg_cost)}</td>
                            <td class="r num">${pct(medicine.price - o.last_cost, medicine.price)}%</td>
                            <td class="r num">${num(o.deliveries)}<br><small class="muted">${num(o.units)} units</small></td>
                            <td class="r num">${Math.round(o.avg_shelf_life_days / 30)} mo ${o.avg_shelf_life_days === freshest && offers.length > 1 ? '<br><span class="tag off">LONGEST</span>' : ''}</td>
                            <td>${App.date(o.last_received)}</td>
                        </tr>`;
                    }).join('')}</tbody>
                </table></div>
                <p class="muted" style="font-size:12px;margin-top:12px">Based on every batch received in Stock. Margin uses the current selling price of ${peso(medicine.price)}. Longer shelf life on arrival means less risk of the stock expiring on the shelf.</p>`;
        }

        medicineId ? detail(medicineId) : list();
    }

    $('#compare').addEventListener('click', () => compare());
    const params = new URLSearchParams(location.search);
    if (params.get('compare')) compare(+params.get('compare'));

    $('#rows').addEventListener('click', (e) => {
        const b = e.target.closest('[data-act]');
        if (!b) return;
        const s = rows.find((r) => r.id === +b.closest('tr').dataset.id);
        b.dataset.act === 'edit' ? edit(s) : remove(s);
    });
    $('#add').addEventListener('click', () => edit());
    $('#q').addEventListener('input', App.debounce(() => {
        page = 1;
        load();
    }));

    load();
});
