(async () => {
    // Already signed in? Skip straight to the dashboard.
    if (Auth.token()) {
        const { ok } = await Auth.api('/user');
        if (ok) return (location.href = '/dashboard');
        Auth.clear();
    }

    const $ = (id) => document.getElementById(id);
    const form = $('login');

    $('toggle').addEventListener('click', () => {
        $('password').type = $('password').type === 'password' ? 'text' : 'password';
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        $('alert').classList.remove('show');
        $('err-email').textContent = $('err-password').textContent = '';

        form.classList.add('loading');
        $('submit').disabled = true;

        const { ok, status, data } = await Auth.api('/login', {
            method: 'POST',
            body: JSON.stringify({ email: $('email').value, password: $('password').value }),
        });

        if (ok) {
            Auth.save(data.token, data.user);
            return (location.href = '/dashboard');
        }

        form.classList.remove('loading');
        $('submit').disabled = false;

        if (status === 422 && data.errors) {
            $('err-email').textContent = data.errors.email?.[0] ?? '';
            $('err-password').textContent = data.errors.password?.[0] ?? '';
        } else {
            $('alert').textContent = status === 429 ? 'Too many attempts. Please wait a minute.' : 'Something went wrong. Please try again.';
            $('alert').classList.add('show');
        }
    });
})();
