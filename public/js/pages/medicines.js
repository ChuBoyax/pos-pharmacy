App.ready.then(async () => {
    const { $, esc, peso, num, icon, field, input, select } = App;
    const units = ['tablet', 'capsule', 'bottle', 'box', 'pack', 'sachet', 'tube', 'nebule', 'vial', 'bar', 'piece'];
    let page = 1;
    let rows = [];
    let categories = [];
    let suppliers = [];

    const params = new URLSearchParams(location.search);
    if (params.get('stock')) $('#stock').value = params.get('stock');

    async function loadLookups() {
        const [c, s] = await Promise.all([App.api('/categories'), App.api('/suppliers', { query: { all: 1 } })]);
        categories = c.ok ? c.data : [];
        suppliers = s.ok ? s.data : [];
        const current = $('#cat').value;
        $('#cat').innerHTML = '<option value="">All categories</option>' + categories.map((x) => `<option value="${x.id}">${esc(x.name)}</option>`).join('');
        $('#cat').value = current;
    }

    async function load() {
        $('#rows').innerHTML = App.loadingRows(7);
        const res = await App.api('/medicines', { query: { page, search: $('#q').value.trim(), category_id: $('#cat').value, stock: $('#stock').value } });
        if (!res.ok) return;
        rows = res.data.data;

        $('#rows').innerHTML = rows.length
            ? rows.map((m) => {
                const tone = m.stock === 0 ? 'bad' : m.stock <= m.reorder_level ? 'warn' : 'ok';
                return `<tr data-id="${m.id}">
                    <td class="wrap"><div class="cell">${App.thumb(m)}<div><b>${esc(m.name)} ${m.requires_prescription ? '<span class="tag rx">RX</span>' : ''}</b><small>${esc(m.generic_name ?? '')} · ${esc(m.sku)}</small></div></div></td>
                    <td>${esc(m.category?.name ?? '—')}</td>
                    <td class="r num"><b>${peso(m.price)}</b><br><small class="muted">per ${esc(m.unit)}</small></td>
                    <td class="r num"><span class="pill ${tone}">${num(m.stock)}</span><br><small class="muted">reorder at ${num(m.reorder_level)}</small></td>
                    <td>${esc(m.supplier?.name ?? '—')}</td>
                    <td>${m.is_active ? '<span class="pill ok">Active</span>' : '<span class="pill mute">Inactive</span>'}</td>
                    <td><div class="acts">
                        <a class="act" href="/stock?receive=${m.id}" title="Receive stock">${icon('box')}</a>
                        <a class="act" href="/suppliers?compare=${m.id}" title="Compare supplier prices">${icon('scale')}</a>
                        <button class="act" data-act="edit" title="Edit">${icon('edit')}</button>
                        <button class="act del" data-act="del" title="Delete">${icon('trash')}</button>
                    </div></td>
                </tr>`;
            }).join('')
            : App.emptyRow(7, 'No medicines found.', 'pill');

        App.pager($('#pager'), res.data, (p) => {
            page = p;
            load();
        });
    }

    function edit(medicine = null) {
        const opts = (list) => [['', '— None —'], ...list.map((x) => [x.id, x.name])];
        let file = null; // new image waiting to be uploaded
        let removeImage = false;

        App.modal({
            title: medicine ? `Edit ${medicine.name}` : 'Add medicine',
            size: 'lg',
            submit: medicine ? 'Save changes' : 'Add medicine',
            body: `<div class="form">
                <div class="f full">
                    <span>Product image</span>
                    <div class="uploader" id="uploader" tabindex="0" role="button" aria-label="Upload product image">
                        <div class="up-preview" id="up-preview"></div>
                        <div class="up-text"><b>Drop an image here or <u>browse</u></b><small id="up-info">PNG, JPG or WEBP · up to 10 MB · compressed automatically</small></div>
                        <button type="button" class="btn sm" id="up-remove" hidden>Remove</button>
                        <input type="file" id="up-file" accept="image/png,image/jpeg,image/webp" hidden>
                    </div>
                    <small class="f-err" data-err="image"></small>
                </div>
                ${field('Brand name', 'name', input('name', 'required placeholder="e.g. Biogesic 500mg"'))}
                ${field('Generic name', 'generic_name', input('generic_name', 'placeholder="e.g. Paracetamol"'))}
                ${field('SKU / barcode', 'sku', input('sku', 'required placeholder="Scan or type"'))}
                ${field('Unit', 'unit', select('unit', units.map((u) => [u, u[0].toUpperCase() + u.slice(1)])))}
                ${field('Category', 'category_id', select('category_id', opts(categories)))}
                ${field('Default supplier', 'supplier_id', select('supplier_id', opts(suppliers)))}
                ${field('Selling price (VAT incl.)', 'price', input('price', 'type="number" min="0" step="0.01" required'))}
                ${field('Unit cost', 'cost', input('cost', 'type="number" min="0" step="0.01"'))}
                ${field('Reorder level', 'reorder_level', input('reorder_level', 'type="number" min="0" required'), { hint: 'Flag as low stock at or below this quantity.' })}
                <div class="f" style="justify-content:center;gap:12px">
                    <label class="switch"><input type="checkbox" name="requires_prescription"> Requires prescription (Rx)</label>
                    <label class="switch"><input type="checkbox" name="is_active" checked> Active / sellable</label>
                </div>
                ${field('Notes', 'description', '<textarea class="input" name="description" rows="2" placeholder="Dosage, storage, warnings…"></textarea>', { full: true })}
            </div>`,
            onOpen: (form) => {
                App.fill(form, medicine ?? { unit: 'tablet', reorder_level: 20, is_active: true });

                const box = form.querySelector('#uploader');
                const picker = form.querySelector('#up-file');
                const err = form.querySelector('[data-err=image]');
                const show = (url) => {
                    form.querySelector('#up-preview').innerHTML = url ? `<img src="${esc(url)}" alt="">` : icon('pill');
                    form.querySelector('#up-remove').hidden = !url;
                };
                const info = form.querySelector('#up-info');
                const pick = async (f) => {
                    err.textContent = '';
                    if (!f) return;
                    if (!['image/png', 'image/jpeg', 'image/webp'].includes(f.type)) return (err.textContent = 'Please choose a PNG, JPG or WEBP image.');
                    if (f.size > 10 * 1024 * 1024) return (err.textContent = 'The image must not be larger than 10 MB.');

                    info.textContent = 'Compressing…';
                    file = await App.compressImage(f);
                    removeImage = false;
                    show(URL.createObjectURL(file));
                    info.textContent = file === f
                        ? `${App.fileSize(f.size)} · will be optimized on upload`
                        : `Compressed ${App.fileSize(f.size)} → ${App.fileSize(file.size)}`;
                };

                show(medicine?.image_url);
                box.addEventListener('click', (e) => !e.target.closest('#up-remove') && picker.click());
                box.addEventListener('keydown', (e) => (e.key === 'Enter' || e.key === ' ') && (e.preventDefault(), picker.click()));
                picker.addEventListener('change', () => pick(picker.files[0]));
                box.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    box.classList.add('drag');
                });
                box.addEventListener('dragleave', () => box.classList.remove('drag'));
                box.addEventListener('drop', (e) => {
                    e.preventDefault();
                    box.classList.remove('drag');
                    pick(e.dataTransfer.files[0]);
                });
                form.querySelector('#up-remove').addEventListener('click', () => {
                    file = null;
                    removeImage = true;
                    picker.value = '';
                    show(null);
                });
            },
            onSubmit: async (form) => {
                const res = await App.api(medicine ? `/medicines/${medicine.id}` : '/medicines', { method: medicine ? 'PUT' : 'POST', body: App.read(form) });
                if (!res.ok) return App.fail(form, res);

                // The medicine is saved at this point, so an image problem is reported but doesn't block.
                const id = res.data.id;
                if (file) {
                    const data = new FormData();
                    data.append('image', file);
                    const up = await App.api(`/medicines/${id}/image`, { method: 'POST', body: data });
                    if (!up.ok) App.toast(`Medicine saved, but the image was not uploaded: ${up.data.errors?.image?.[0] ?? up.data.message ?? 'unknown error'}`, 'bad');
                } else if (removeImage && medicine?.image_url) {
                    await App.api(`/medicines/${id}/image`, { method: 'DELETE' });
                }

                App.toast(medicine ? 'Medicine updated.' : 'Medicine added.');
                load();
            },
        });
    }

    async function remove(m) {
        const ok = await App.confirm({ title: `Delete ${m.name}?`, message: 'Medicines with stock or sales history are deactivated instead, so past receipts stay intact.' });
        if (!ok) return;
        const res = await App.api(`/medicines/${m.id}`, { method: 'DELETE' });
        if (!res.ok) return App.fail(null, res);
        App.toast(res.data.message);
        load();
    }

    function manageCategories() {
        const list = () => categories.map((c) => `<div class="ci" data-id="${c.id}">
            <input class="input" value="${esc(c.name)}" style="flex:1">
            <small class="muted" style="width:70px;text-align:right">${num(c.medicines_count ?? 0)} items</small>
            <button type="button" class="act" data-act="save" title="Save">${icon('check')}</button>
            <button type="button" class="act del" data-act="del" title="Delete">${icon('trash')}</button>
        </div>`).join('') || '<p class="muted">No categories yet.</p>';

        App.modal({
            title: 'Categories',
            submit: 'Add category',
            body: `<div class="toolbar" style="margin-bottom:12px">${input('name', 'placeholder="New category name" style="flex:1"')}</div>
                <small class="f-err" data-err="name"></small>
                <div id="cat-list" style="max-height:360px;overflow-y:auto">${list()}</div>`,
            onOpen: (form) => {
                form.querySelector('#cat-list').addEventListener('click', async (e) => {
                    const b = e.target.closest('[data-act]');
                    if (!b) return;
                    const row = b.closest('[data-id]');
                    const id = row.dataset.id;
                    const res = b.dataset.act === 'save'
                        ? await App.api(`/categories/${id}`, { method: 'PUT', body: { name: row.querySelector('input').value } })
                        : await App.api(`/categories/${id}`, { method: 'DELETE' });
                    if (!res.ok) return App.fail(null, res);
                    App.toast(b.dataset.act === 'save' ? 'Category renamed.' : 'Category deleted.');
                    await loadLookups();
                    form.querySelector('#cat-list').innerHTML = list();
                    load();
                });
            },
            onSubmit: async (form) => {
                const res = await App.api('/categories', { method: 'POST', body: { name: App.read(form).name } });
                if (!res.ok) return App.fail(form, res);
                App.toast('Category added.');
                await loadLookups();
                form.querySelector('#cat-list').innerHTML = list();
                form.querySelector('[name=name]').value = '';
                return false; // keep the dialog open for more edits
            },
        });
    }

    $('#rows').addEventListener('click', (e) => {
        const b = e.target.closest('[data-act]');
        if (!b) return;
        const m = rows.find((r) => r.id === +b.closest('tr').dataset.id);
        b.dataset.act === 'edit' ? edit(m) : remove(m);
    });
    $('#add').addEventListener('click', () => edit());
    $('#manage-cats').addEventListener('click', manageCategories);

    const reload = () => {
        page = 1;
        load();
    };
    $('#q').addEventListener('input', App.debounce(reload));
    $('#cat').addEventListener('change', reload);
    $('#stock').addEventListener('change', reload);

    await loadLookups();
    load();
});
