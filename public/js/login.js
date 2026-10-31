(async () => {
    // Already signed in? Skip straight to the dashboard.
    if (Auth.token()) {
        const { ok } = await Auth.api('/user');
        if (ok) return (location.href = '/dashboard');
        Auth.clear();
    }

    const $ = (id) => document.getElementById(id);
    const form = $('login');
    const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    const setError = (field, message = '') => ($(`err-${field}`).textContent = message);

    // Same rules as the server, checked first so obvious mistakes don't cost a login attempt.
    function validate() {
        const email = $('email').value.trim();
        const password = $('password').value;
        const errors = {
            email: !email ? 'Please enter your email.' : !EMAIL.test(email) ? 'Please enter a valid email address.' : '',
            password: !password ? 'Please enter your password.' : '',
        };
        Object.entries(errors).forEach(([field, message]) => setError(field, message));
        const invalid = Object.keys(errors).find((field) => errors[field]);
        if (invalid) $(invalid).focus();
        return !invalid;
    }

    $('toggle').addEventListener('click', () => {
        const show = $('password').type === 'password';
        $('password').type = show ? 'text' : 'password';
        $('toggle').setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });

    // Clear a field's error as soon as the user starts fixing it.
    ['email', 'password'].forEach((field) => $(field).addEventListener('input', () => setError(field)));

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        $('alert').classList.remove('show');
        if (!validate()) return;

        form.classList.add('loading');
        $('submit').disabled = true;

        const { ok, status, data } = await Auth.api('/login', {
            method: 'POST',
            body: JSON.stringify({ email: $('email').value.trim(), password: $('password').value }),
        });

        if (ok) {
            Auth.save(data.token, data.user);
            return (location.href = '/dashboard');
        }

        form.classList.remove('loading');
        $('submit').disabled = false;

        if (data?.errors) {
            setError('email', data.errors.email?.[0]);
            setError('password', data.errors.password?.[0]);
        } else {
            $('alert').textContent = status === 429 ? 'Too many attempts. Please wait a minute and try again.' : 'Something went wrong. Please try again.';
            $('alert').classList.add('show');
        }
    });
})();
