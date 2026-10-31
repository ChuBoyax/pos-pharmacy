<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Sto. Domingo Infirmary</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700" rel="stylesheet">
    <link rel="stylesheet" href="@asset('css/pos.css')">
</head>
<body>
<main class="stage">
    {{-- Decoration only --}}
    <div class="deco" aria-hidden="true">
        <i class="blob b1"></i><i class="blob b2"></i><i class="blob b3"></i><i class="blob b4"></i>
        <i class="line l1"></i><i class="line l2"></i>
    </div>

    <div class="stack">
        <div class="glass" aria-hidden="true">
            <div class="glass-h">
                <span class="brand"><b>Sto. Domingo</b><small>Infirmary · Pharmacy</small></span>
                <span class="glass-mark"><svg viewBox="0 0 24 24"><path d="M12 6v12M6 12h12"/></svg></span>
            </div>
        </div>

        <section class="card">
            <header class="card-h">
                <div>
                    <h1>Sign in</h1>
                    <p>Pharmacy point of sale</p>
                </div>
                <span class="mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><rect x="9" y="3" width="6" height="18" rx="1.5"/><rect x="3" y="9" width="18" height="6" rx="1.5"/></svg>
                </span>
            </header>

            <div id="alert" class="alert" role="alert"></div>

            <form id="login" novalidate>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" autocomplete="username" placeholder="Enter your email" maxlength="255" required>
                    <div class="err" id="err-email"></div>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <div class="pw">
                        <input id="password" type="password" autocomplete="current-password" placeholder="Enter your password" maxlength="255" required>
                        <button type="button" class="toggle" id="toggle" aria-label="Show password">
                            <svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="err" id="err-password"></div>
                </div>

                <div class="card-f">
                    <small>Authorized personnel only</small>
                    <button class="btn" id="submit" type="submit">
                        <span class="spin"></span><span>Sign in</span>
                        <svg class="arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                </div>
            </form>
        </section>
    </div>

    <footer class="foot">&copy; {{ date('Y') }} Sto. Domingo Infirmary</footer>
</main>
<script src="@asset('js/auth.js')"></script>
<script src="@asset('js/login.js')"></script>
</body>
</html>
