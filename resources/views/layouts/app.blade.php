@php
    use App\Models\Role;

    // Route => icon. Labels and required permissions come from Role::MENUS.
    $nav = [
        'Main' => ['dashboard' => 'grid', 'pos' => 'cart', 'transactions' => 'receipt'],
        'Inventory' => ['medicines' => 'pill', 'stock' => 'box', 'suppliers' => 'truck'],
        'Management' => ['reports' => 'chart', 'users' => 'users', 'roles' => 'shield', 'settings' => 'cog'],
    ];
    // Not a role-assignable menu: only the super admin ever sees it.
    $menus = Role::MENUS + ['roles' => ['Roles & Permissions', 'roles.manage']];
    $page = Route::currentRouteName();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · PharmaPOS</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="/css/app.css">
    <link rel="stylesheet" href="/css/pages.css">
    <script>
        try {
            document.documentElement.dataset.theme = localStorage.getItem('pos_theme') || 'light';
            document.documentElement.dataset.side = localStorage.getItem('pos_side') || '';
        } catch (e) {}
    </script>
</head>
<body data-can="@yield('can')" @isset(Role::MENUS[$page]) data-menu="{{ $page }}" @endisset>
@include('partials.icons')

<div class="splash" id="splash"><i></i></div>

<div class="shell">
    <button class="side-toggle" id="collapse" aria-label="Collapse sidebar" title="Collapse sidebar"><svg class="i"><use href="#i-chevron"/></svg></button>
    <aside class="side" id="side">
        <div class="logo">
            <b><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9.25" y="4" width="5.5" height="16" rx="1.4"/><rect x="4" y="9.25" width="16" height="5.5" rx="1.4"/></svg></b>
            <div><strong>Sto. Domingo</strong><small>Infirmary · Pharmacy</small></div>
        </div>

        @foreach ($nav as $group => $links)
            <div class="nav-group" data-group="{{ Str::slug($group) }}">
                <button type="button" class="nav-label" aria-expanded="true">
                    {{ $group }}<svg class="i"><use href="#i-down"/></svg>
                </button>
                <div class="nav-wrap">
                    <nav class="nav">
                        @foreach ($links as $route => $icon)
                            @php [$label, $can] = $menus[$route]; @endphp
                            <a href="{{ route($route) }}" title="{{ $label }}" @if ($can) data-can="{{ $can }}" @endif @isset(Role::MENUS[$route]) data-menu="{{ $route }}" @endisset @class(['on' => request()->routeIs($route)])>
                                <svg class="i"><use href="#i-{{ $icon }}"/></svg><span class="lbl">{{ $label }}</span>
                                @if ($route === 'stock')
                                    <span class="badge" id="stock-badge" hidden></span>
                                @endif
                            </a>
                        @endforeach
                    </nav>
                </div>
            </div>
        @endforeach
    </aside>
    <script>
        // Restore folded nav groups before first paint so they don't flash open.
        try {
            JSON.parse(localStorage.getItem('pos_nav_closed') || '[]').forEach((g) => {
                const el = document.querySelector(`.nav-group[data-group="${g}"]`);
                el?.classList.add('closed');
                el?.querySelector('.nav-label').setAttribute('aria-expanded', 'false');
            });
        } catch (e) {}
    </script>

    <div class="wrap">
        <header class="bar">
            <button class="icon-btn menu-btn" id="menu" aria-label="Open menu"><svg class="i"><use href="#i-menu"/></svg></button>
            <div class="bar-r">
                <button class="icon-btn" id="theme" aria-label="Toggle theme">
                    <svg class="i theme-ic-sun"><use href="#i-sun"/></svg><svg class="i theme-ic-moon"><use href="#i-moon"/></svg>
                </button>
                <a class="icon-btn" href="{{ route('stock') }}?status=expiring" data-can="stock.manage" aria-label="Inventory alerts" title="Inventory alerts">
                    <svg class="i"><use href="#i-bell"/></svg><span class="dot" id="alert-dot" hidden></span>
                </a>
                <div class="user">
                    <button class="user-btn" id="user-btn" aria-haspopup="true">
                        <div class="avatar" id="avatar">?</div>
                        <span><b id="name">—</b><small id="role"></small></span>
                    </button>
                    <div class="dropdown" id="menu-dd">
                        <button onclick="location.href='{{ route('settings') }}'"><svg class="i"><use href="#i-user"/></svg>My account</button>
                        <button class="danger" id="logout"><svg class="i"><use href="#i-logout"/></svg>Log out</button>
                    </div>
                </div>
            </div>
        </header>

        <main class="page @yield('page-class')">
            @yield('content')
        </main>
    </div>
</div>

<div class="toasts" id="toasts"></div>
<div id="print-area"></div>

<script src="/js/auth.js"></script>
<script src="/js/app.js"></script>
@stack('scripts')
</body>
</html>
