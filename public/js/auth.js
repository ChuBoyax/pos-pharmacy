const Auth = {
    token: () => localStorage.getItem('pos_token'),
    save(token, user) {
        localStorage.setItem('pos_token', token);
        localStorage.setItem('pos_user', JSON.stringify(user));
    },
    clear() {
        localStorage.removeItem('pos_token');
        localStorage.removeItem('pos_user');
    },
    async api(path, options = {}) {
        const res = await fetch('/api' + path, {
            ...options,
            headers: {
                Accept: 'application/json',
                // File uploads (FormData) set their own multipart boundary.
                ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
                ...(this.token() ? { Authorization: 'Bearer ' + this.token() } : {}),
                ...(options.headers || {}),
            },
        });
        const data = await res.json().catch(() => ({}));
        return { ok: res.ok, status: res.status, data };
    },
};
