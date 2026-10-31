App.ready.then(async (user) => {
    const { $ } = App;

    const showUser = (u) => {
        $('#p-name').textContent = u.name;
        $('#p-role').textContent = u.role;
        $('#p-avatar').textContent = App.initials(u.name);
        $('#name').textContent = u.name;
        $('#avatar').textContent = App.initials(u.name);
    };
    showUser(user);
    App.fill($('#profile'), user);

    // Submit a settings form, keep the button busy meanwhile, and report the result.
    function handle(form, send) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('[type=submit]');
            btn.disabled = true;
            App.clearErrors(form);
            try {
                await send(App.read(form));
            } finally {
                btn.disabled = false;
            }
        });
    }

    handle($('#profile'), async (data) => {
        const res = await App.api('/profile', { method: 'PUT', body: data });
        if (!res.ok) return App.fail($('#profile'), res);
        showUser(res.data.user);
        App.toast('Profile saved.');
    });

    handle($('#password'), async (data) => {
        const res = await App.api('/profile/password', { method: 'PUT', body: data });
        if (!res.ok) return App.fail($('#password'), res);
        $('#password').reset();
        App.toast('Password updated.');
    });

    const store = $('#store'); // removed from the page for non-admins
    if (store) {
        App.fill(store, await App.settings());
        handle(store, async (data) => {
            const res = await App.api('/settings', { method: 'PUT', body: data });
            if (!res.ok) return App.fail(store, res);
            App.settings(true);
            App.toast('Store settings saved.');
        });
    }
});
