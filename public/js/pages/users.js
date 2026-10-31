App.ready.then(async (me) => {
    const { $, esc, num, icon, field, input, select, initials } = App;
    const isSuper = App.can('roles.manage');
    const tones = { superadmin: ['t-bad', 'bad'], admin: ['t-brand', 'info'], pharmacist: ['t-info', 'ok'], cashier: ['t-ok', 'warn'] };
    const tone = (role) => tones[role] ?? ['t-warn', 'mute'];
    let page = 1;
    let rows = [];

    const res = await App.api('/roles');
    if (!res.ok) return;
    const roles = res.data.roles;
    const grantable = Object.values(res.data.permissions).reduce((n, g) => n + Object.keys(g).length, 0);
    // Only a super admin can hand out (or take away) the super admin role.
    const assignable = roles.filter((r) => isSuper || r.name !== 'superadmin');

    $('#role-cards').innerHTML = roles.map((r) => `<div class="card mini">
        <small>${esc(r.label)}</small>
        <b>${num(r.users_count)} <span style="font-size:13px;font-weight:500">${r.users_count === 1 ? 'user' : 'users'}</span></b>
        <span>${r.name === 'superadmin' ? 'Full access to everything' : `${r.permissions.length} of ${grantable} permissions`}</span>
    </div>`).join('');
    $('#role-filter').insertAdjacentHTML('beforeend', roles.map((r) => `<option value="${esc(r.name)}">${esc(r.label)}</option>`).join(''));

    async function load() {
        $('#rows').innerHTML = App.loadingRows(6);
        const res = await App.api('/users', { query: { page, search: $('#q').value.trim(), role: $('#role-filter').value } });
        if (!res.ok) return;
        rows = res.data.data;

        $('#rows').innerHTML = rows.length
            ? rows.map((u) => {
                const locked = u.role === 'superadmin' && !isSuper;
                return `<tr data-id="${u.id}">
                <td><div class="cell"><span class="av ${tone(u.role)[0]}">${initials(u.name)}</span><div><b>${esc(u.name)} ${u.id === me.id ? '<span class="tag off">YOU</span>' : ''}</b><small>${esc(u.email)}</small></div></div></td>
                <td><span class="pill ${tone(u.role)[1]}">${esc(u.assigned_role?.label ?? u.role)}</span></td>
                <td>${u.is_active ? '<span class="pill ok">Active</span>' : '<span class="pill mute">Deactivated</span>'}</td>
                <td class="r num">${num(u.sales_count)}</td>
                <td>${App.date(u.created_at)}</td>
                <td><div class="acts">
                    ${locked ? `<span class="muted" title="Only a super admin can change this account">${icon('lock')}</span>` : `
                    <button class="act" data-act="edit" title="Edit">${icon('edit')}</button>
                    ${u.id === me.id ? '' : `<button class="act del" data-act="del" title="Delete">${icon('trash')}</button>`}`}
                </div></td>
            </tr>`;
            }).join('')
            : App.emptyRow(6, 'No users found.', 'users');

        App.pager($('#pager'), res.data, (p) => {
            page = p;
            load();
        });
    }

    function edit(user = null) {
        const self = user?.id === me.id;
        App.modal({
            title: user ? `Edit ${user.name}` : 'Add user',
            submit: user ? 'Save changes' : 'Create account',
            body: `<div class="form">
                ${field('Full name', 'name', input('name', 'required'), { full: true })}
                ${field('Email', 'email', input('email', 'type="email" required autocomplete="off"'), { full: true })}
                ${field('Role', 'role', select('role', assignable.map((r) => [r.name, r.label]), self ? 'disabled' : ''), self ? { hint: 'You cannot change your own role.' } : {})}
                ${field(user ? 'New password' : 'Password', 'password', input('password', `type="password" autocomplete="new-password" ${user ? 'placeholder="Leave blank to keep current"' : 'required'}`), { hint: 'At least 8 characters.' })}
                <div class="f full"><label class="switch"><input type="checkbox" name="is_active" checked ${self ? 'disabled' : ''}> Account active (can sign in)</label></div>
            </div>`,
            onOpen: (form) => App.fill(form, user ? { ...user, password: '' } : { role: 'cashier', is_active: true }),
            onSubmit: async (form) => {
                // Disabled fields aren't submitted, so send your own role and status back unchanged.
                const body = self ? { ...App.read(form), role: user.role, is_active: true } : App.read(form);
                const res = await App.api(user ? `/users/${user.id}` : '/users', { method: user ? 'PUT' : 'POST', body });
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
    $('#role-filter').addEventListener('change', () => {
        page = 1;
        load();
    });
    $('#q').addEventListener('input', App.debounce(() => {
        page = 1;
        load();
    }));

    load();
});
