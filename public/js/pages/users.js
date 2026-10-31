App.ready.then((me) => {
    const { $, esc, num, icon, field, input, select, initials } = App;
    const roleTone = { admin: 't-brand', pharmacist: 't-info', cashier: 't-ok' };
    let page = 1;
    let rows = [];

    async function load() {
        $('#rows').innerHTML = App.loadingRows(6);
        const res = await App.api('/users', { query: { page, search: $('#q').value.trim(), role: $('#role').value } });
        if (!res.ok) return;
        rows = res.data.data;

        $('#rows').innerHTML = rows.length
            ? rows.map((u) => `<tr data-id="${u.id}">
                <td><div class="cell"><span class="av ${roleTone[u.role]}">${initials(u.name)}</span><div><b>${esc(u.name)} ${u.id === me.id ? '<span class="tag off">YOU</span>' : ''}</b><small>${esc(u.email)}</small></div></div></td>
                <td><span class="pill ${u.role === 'admin' ? 'info' : u.role === 'pharmacist' ? 'ok' : 'warn'}" style="text-transform:capitalize">${esc(u.role)}</span></td>
                <td>${u.is_active ? '<span class="pill ok">Active</span>' : '<span class="pill mute">Deactivated</span>'}</td>
                <td class="r num">${num(u.sales_count)}</td>
                <td>${App.date(u.created_at)}</td>
                <td><div class="acts">
                    <button class="act" data-act="edit" title="Edit">${icon('edit')}</button>
                    ${u.id === me.id ? '' : `<button class="act del" data-act="del" title="Delete">${icon('trash')}</button>`}
                </div></td>
            </tr>`).join('')
            : App.emptyRow(6, 'No users found.', 'users');

        App.pager($('#pager'), res.data, (p) => {
            page = p;
            load();
        });
    }

    function edit(user = null) {
        App.modal({
            title: user ? `Edit ${user.name}` : 'Add user',
            submit: user ? 'Save changes' : 'Create account',
            body: `<div class="form">
                ${field('Full name', 'name', input('name', 'required'), { full: true })}
                ${field('Email', 'email', input('email', 'type="email" required autocomplete="off"'), { full: true })}
                ${field('Role', 'role', select('role', [['cashier', 'Cashier'], ['pharmacist', 'Pharmacist'], ['admin', 'Admin']]))}
                ${field(user ? 'New password' : 'Password', 'password', input('password', `type="password" autocomplete="new-password" ${user ? 'placeholder="Leave blank to keep current"' : 'required'}`), { hint: 'At least 8 characters.' })}
                <div class="f full"><label class="switch"><input type="checkbox" name="is_active" checked> Account active (can sign in)</label></div>
            </div>`,
            onOpen: (form) => App.fill(form, user ? { ...user, password: '' } : { role: 'cashier', is_active: true }),
            onSubmit: async (form) => {
                const res = await App.api(user ? `/users/${user.id}` : '/users', { method: user ? 'PUT' : 'POST', body: App.read(form) });
                if (!res.ok) return App.fail(form, res);
                App.toast(user ? 'User updated.' : 'User created.');
                load();
            },
        });
    }

    async function remove(u) {
        const ok = await App.confirm({ title: `Delete ${u.name}?`, message: 'Users who have rung up sales are deactivated instead, so the sales history keeps their name.' });
        if (!ok) return;
        const res = await App.api(`/users/${u.id}`, { method: 'DELETE' });
        if (!res.ok) return App.fail(null, res);
        App.toast(res.data.message);
        load();
    }

    $('#rows').addEventListener('click', (e) => {
        const b = e.target.closest('[data-act]');
        if (!b) return;
        const u = rows.find((r) => r.id === +b.closest('tr').dataset.id);
        b.dataset.act === 'edit' ? edit(u) : remove(u);
    });
    $('#add').addEventListener('click', () => edit());
    $('#role').addEventListener('change', () => {
        page = 1;
        load();
    });
    $('#q').addEventListener('input', App.debounce(() => {
        page = 1;
        load();
    }));

    load();
});
