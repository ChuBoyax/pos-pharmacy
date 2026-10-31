<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · PharmaPOS</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="/css/pos.css">
</head>
<body>
<main class="auth">
    <section class="auth-hero">
        <div class="brand">
            <span class="brand-mark">
                <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            </span>
            PharmaPOS
        </div>
        <div class="hero-copy">
            <h1>Smarter pharmacy sales, <span>safer stock.</span></h1>
            <p>Process prescriptions and over-the-counter sales, track batches and expiry dates, and keep every shelf stocked — all from one secure system.</p>
        </div>
        <div class="hero-stats">
            <div class="chip"><div><b>Expiry alerts</b>Never sell expired meds</div></div>
            <div class="chip"><div><b>Live inventory</b>Real-time stock levels</div></div>
            <div class="chip"><div><b>Role access</b>Admin · Pharmacist · Cashier</div></div>
        </div>
    </section>

    <section class="auth-panel">
        <div class="auth-box">
            <h2>Welcome back</h2>
            <p class="sub">Sign in to your pharmacy account to continue.</p>

            <div id="alert" class="alert" role="alert"></div>

            <form id="login" novalidate>
                <div class="field">
                    <label for="email">Email address</label>
                    <div class="input">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        <input id="email" type="email" autocomplete="username" placeholder="you@pharmacy.com" required>
                    </div>
                    <div class="err" id="err-email"></div>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <div class="input">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                        <input id="password" type="password" autocomplete="current-password" placeholder="••••••••" required>
                        <button type="button" class="toggle" id="toggle" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="err" id="err-password"></div>
                </div>
                <button class="btn" id="submit" type="submit"><span class="spin"></span><span>Sign in</span></button>
            </form>

            <div class="foot">&copy; {{ date('Y') }} PharmaPOS. Authorized personnel only.</div>
        </div>
    </section>
</main>
<script src="/js/auth.js"></script>
<script src="/js/login.js"></script>
</body>
</html>
