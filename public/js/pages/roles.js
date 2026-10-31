App.ready.then(() => {
    const { $, esc, num, icon, field, input } = App;
    let roles = [];
    let groups = {};
    let menus = {};

    const isSuper = (r) => r.name === 'superadmin';
    const allKeys = () => Object.values(groups).flatMap((g) => Object.keys(g));
    const permLabel = (key) => Object.values(groups).find((g) => key in g)?.[key] ?? key;
    const has = (r, key) => isSuper(r) || r.permissions.includes(key);
    const sees = (r, menu) => isSuper(r) || (r.menus ?? Object.keys(menus)).includes(menu);

    async function load() {
        const res = await App.api('/roles');
        if (!res.ok) return;
        ({ roles, permissions: groups, menus } = res.data);
        const total = allKeys().length;
        const menuTotal = Object.keys(menus).length;

        $('#roles').innerHTML = roles.map((r) => {
            const count = isSuper(r) ? total : r.permissions.length;
            return `<article class="card role ${isSuper(r) ? 'super' : ''}" data-id="${r.id}">
                <div class="role-h">
                    <span class="role-ic">${icon(isSuper(r) ? 'shield' : 'users')}</span>
                    <div class="acts">${isSuper(r)
                        ? `<span class="tag off" title="The super admin always has every permission">${icon('lock')} LOCKED</span>`
                        : `<button class="act" data-act="edit" title="Edit">${icon('edit')}</button><button class="act del" data-act="del" title="Delete">${icon('trash')}</button>`}
                    </div>
                </div>
                <h3>${esc(r.label)}</h3>
                <p>${esc(r.description || 'No description')}</p>
                <div class="role-meter" title="${count} of ${total} permissions"><i style="width:${(count / total) * 100}%"></i></div>
                <div class="role-f">
                    <span>${isSuper(r) ? 'All permissions + roles' : `${count} of ${total} permissions`}</span>
                    <span>${num(r.users_count)} ${r.users_count === 1 ? 'user' : 'users'}</span>
                </div>
                <div class="role-f">
                    <span>${isSuper(r) ? 'Every menu' : `${(r.menus ?? []).length} of ${menuTotal} menus shown`}</span>
                </div>
            </article>`;
        }).join('');

        $('#matrix').innerHTML = `<thead><tr><th>Permission</th>${roles.map((r) => `<th class="c">${esc(r.label)}</th>`).join('')}</tr></thead>
            <tbody>${Object.entries(groups).map(([group, perms]) => `
                <tr class="grp"><td colspan="${roles.length + 1}">${esc(group)}</td></tr>
                ${Object.entries(perms).map(([key, label]) => `<tr>
                    <td><b>${esc(label)}</b><small class="muted code">${esc(key)}</small></td>
                    ${roles.map((r) => `<td class="c">${has(r, key) ? `<span class="yes">${icon('check')}</span>` : '<span class="no">—</span>'}</td>`).join('')}
                </tr>`).join('')}`).join('')}
                <tr class="grp"><td colspan="${roles.length + 1}">Sidebar menu</td></tr>
                ${Object.entries(menus).map(([key, [label, needs]]) => `<tr>
                    <td><b>${esc(label)}</b><small class="muted code">${needs ? `needs ${esc(needs.split(',').join(' or '))}` : 'no permission needed'}</small></td>
                    ${roles.map((r) => {
                        const visible = sees(r, key) && (!needs || needs.split(',').some((p) => has(r, p)));
                        return `<td class="c">${visible ? `<span class="yes">${icon('eye')}</span>` : '<span class="no">—</span>'}</td>`;
                    }).join('')}
                </tr>`).join('')}
                <tr class="grp"><td colspan="${roles.length + 1}">System</td></tr>
                <tr>
                    <td><b>Manage roles &amp; permissions</b><small class="muted code">roles.manage · super admin only</small></td>
                    ${roles.map((r) => `<td class="c">${isSuper(r) ? `<span class="yes">${icon('check')}</span>` : '<span class="no">—</span>'}</td>`).join('')}
                </tr>
            </tbody>`;
    }

    function edit(role = null) {
        App.modal({
            title: role ? `Edit ${role.label}` : 'New role',
            submit: role ? 'Save role' : 'Create role',
            size: 'lg',
            body: `<div class="form">
                ${field('Role name', 'label', input('label', 'required maxlength="50" placeholder="e.g. Inventory Clerk"'))}
                ${field('Description', 'description', input('description', 'maxlength="255" placeholder="What this role is for"'))}
            </div>
            <div class="perm-h">
                <b>Permissions</b>
                <span><button type="button" class="link" data-all="1">Select all</button> · <button type="button" class="link" data-all="0">Clear</button></span>
            </div>
            <small class="f-err" data-err="permissions"></small>
            <div class="perm-groups">${Object.entries(groups).map(([group, perms]) => `
                <fieldset class="perm-group">
                    <legend>${esc(group)}</legend>
                    ${Object.entries(perms).map(([key, label]) => `<label class="perm">
                        <span><b>${esc(label)}</b><small class="code">${esc(key)}</small></span>
                        <span class="switch"><input type="checkbox" data-perm="${esc(key)}" ${role?.permissions.includes(key) ? 'checked' : ''}></span>
                    </label>`).join('')}
                </fieldset>`).join('')}
            </div>
            <div class="perm-h">
                <b>Sidebar menu</b>
                <span><button type="button" class="link" data-menus="1">Show all</button> · <button type="button" class="link" data-menus="0">Hide all</button></span>
            </div>
            <small class="muted">Turn a menu off to hide it from this role. A hidden page can't be opened by typing its address either.</small>
            <small class="f-err" data-err="menus"></small>
            <div class="menu-grid">${Object.entries(menus).map(([key, [label, needs]]) => `<label class="perm" data-needs="${esc(needs)}">
                <span><b>${esc(label)}</b><small class="need"></small></span>
                <span class="switch"><input type="checkbox" data-menu-key="${esc(key)}" ${!role || sees(role, key) ? 'checked' : ''}></span>
            </label>`).join('')}
            </div>`,
            onOpen: (form) => {
                App.fill(form, role ?? {});
                // A menu only appears when the role also holds one of the permissions behind it.
                const hint = () => form.querySelectorAll('[data-needs]').forEach((row) => {
                    const needs = row.dataset.needs ? row.dataset.needs.split(',') : [];
                    const missing = needs.length && !needs.some((p) => form.querySelector(`[data-perm="${p}"]`)?.checked);
                    row.classList.toggle('blocked', !!missing);
                    row.querySelector('.need').textContent = missing ? `Needs “${needs.map(permLabel).join('” or “')}”` : '';
                });
                form.addEventListener('click', (e) => {
                    const b = e.target.closest('[data-all],[data-menus]');
                    if (b?.dataset.all) form.querySelectorAll('[data-perm]').forEach((c) => (c.checked = b.dataset.all === '1'));
                    if (b?.dataset.menus) form.querySelectorAll('[data-menu-key]').forEach((c) => (c.checked = b.dataset.menus === '1'));
                    hint();
                });
                form.addEventListener('change', hint);
                hint();
            },
            onSubmit: async (form) => {
                const { label, description } = App.read(form);
                const permissions = [...form.querySelectorAll('[data-perm]:checked')].map((c) => c.dataset.perm);
                const shownMenus = [...form.querySelectorAll('[data-menu-key]:checked')].map((c) => c.dataset.menuKey);
                const res = await App.api(role ? `/roles/${role.id}` : '/roles', { method: role ? 'PUT' : 'POST', body: { label, description, permissions, menus: shownMenus } });
                if (!res.ok) return App.fail(form, res);
                App.toast(role ? 'Role updated.' : 'Role created.');
                load();
            },
        });
    }

    async function remove(role) {
        const ok = await App.confirm({ title: `Delete ${role.label}?`, message: role.users_count ? `${role.users_count} user(s) still have this role. Move them to another role first.` : 'This role has no users and will be removed.' });
        if (!ok) return;
        const res = await App.api(`/roles/${role.id}`, { method: 'DELETE' });
        if (!res.ok) return App.fail(null, res);
        App.toast(res.data.message);
        load();
    }

    $('#roles').addEventListener('click', (e) => {
        const b = e.target.closest('[data-act]');
        if (!b) return;
        const role = roles.find((r) => r.id === +b.closest('[data-id]').dataset.id);
        b.dataset.act === 'edit' ? edit(role) : remove(role);
    });
    $('#add').addEventListener('click', () => edit());

    load();
});
