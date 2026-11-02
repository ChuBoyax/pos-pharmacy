@php
    use App\Models\Role;

    // Route => icon. Labels and required permissions come from Role::MENUS.
    // 'inventory' is one sidebar item for the medicines / stock / suppliers tabs.
    $nav = [
        'dashboard' => 'grid', 'pos' => 'cart', 'transactions' => 'receipt', 'inventory' => 'box',
        'reports' => 'chart', 'users' => 'users', 'roles' => 'shield', 'settings' => 'cog',
    ];
    // Not a role-assignable menu: only the super admin ever sees it.
    $menus = Role::MENUS + ['roles' => ['Roles & Permissions', 'roles.manage']];
    $page = Route::currentRouteName();
    // The rail is narrow, so long names get a short caption (the full name stays in the tooltip).
    $short = ['pos' => 'Sales', 'roles' => 'Roles'];
    $inventoryTabs = ['medicines', 'stock', 'suppliers'];
    // [url, permissions, menu key] per tab, so app.js can point Inventory at the first one allowed.
    $tabLinks = array_map(fn ($t) => [route($t), $menus[$t][1], $t], $inventoryTabs);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · PharmaPOS</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="@asset('css/app.css')">
    <link rel="stylesheet" href="@asset('css/pages.css')">
</head>
<body data-can="@yield('can')" @isset(Role::MENUS[$page]) data-menu="{{ $page }}" @endisset>
@include('partials.icons')

<div class="splash" id="splash"><i></i></div>

<div class="shell">
    <aside class="side" id="side">
        <a class="logo" href="{{ route('dashboard') }}" title="Sto. Domingo Infirmary · Pharmacy" aria-label="Sto. Domingo Infirmary Pharmacy">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="3" width="6" height="18" rx="1.5"/><rect x="3" y="9" width="18" height="6" rx="1.5"/></svg>
        </a>

        <nav class="nav">
            @foreach ($nav as $route => $icon)
                @if ($route === 'inventory')
                    {{-- Points at the first tab the user may open; app.js fills in the href. --}}
                    <a href="{{ route('medicines') }}" title="Inventory" id="nav-inventory"
                       data-tabs="{{ json_encode($tabLinks) }}"
                       @class(['on' => request()->routeIs(...$inventoryTabs)])>
                        <svg class="i"><use href="#i-box"/></svg><span class="lbl">Inventory</span>
                        <span class="badge stock-badge" hidden></span>
                    </a>
                    @continue
                @endif
                @php [$label, $can] = $menus[$route]; @endphp
                <a href="{{ route($route) }}" title="{{ $label }}" aria-label="{{ $label }}" @if ($can) data-can="{{ $can }}" @endif @isset(Role::MENUS[$route]) data-menu="{{ $route }}" @endisset @class(['on' => request()->routeIs($route)])>
                    <svg class="i"><use href="#i-{{ $icon }}"/></svg><span class="lbl">{{ $short[$route] ?? $label }}</span>
                </a>
            @endforeach
        </nav>

        <div class="side-foot">
            <a class="side-link" href="{{ route('stock') }}?status=expiring" data-can="stock.manage" aria-label="Inventory alerts" title="Inventory alerts">
                <span class="side-ic"><svg class="i"><use href="#i-bell"/></svg><span class="dot" id="alert-dot" hidden></span></span>
                <span class="lbl">Alerts</span>
            </a>
            <button class="user-btn" id="user-btn" aria-haspopup="true" aria-label="Account menu">
                <span class="avatar" id="avatar">?</span>
            </button>
        </div>
    </aside>

    {{-- Outside the sidebar, whose scrolling would clip it. --}}
    <div class="dropdown" id="menu-dd">
        <div class="dd-h"><b id="dd-name"></b><small id="dd-role"></small></div>
        {{-- Opens the Settings page, so it goes wherever the role hides that menu. --}}
        <button onclick="location.href='{{ route('settings') }}'" data-menu="settings"><svg class="i"><use href="#i-user"/></svg>My account</button>
        <button class="danger" id="logout"><svg class="i"><use href="#i-logout"/></svg>Log out</button>
    </div>

    <div class="wrap">
        {{-- Pages without a title (POS) have no bar, except on small screens where it holds the menu button. --}}
        <header @class(['bar', 'bare' => ! View::hasSection('bar-title')])>
            <button class="icon-btn menu-btn" id="menu" aria-label="Open menu"><svg class="i"><use href="#i-menu"/></svg></button>
            @hasSection('bar-title')
                <div class="bar-title">
                    <h1>@yield('bar-title')</h1>
                    <p>@yield('bar-subtitle')</p>
                </div>
            @endif
            @hasSection('bar-actions')
                <div class="bar-actions">@yield('bar-actions')</div>
            @endif
        </header>

        <main class="page @yield('page-class')">
            @yield('content')
        </main>
    </div>
</div>

<div class="toasts" id="toasts"></div>
<div id="print-area"></div>

<script src="@asset('js/auth.js')"></script>
<script src="@asset('js/app.js')"></script>
@stack('scripts')
</body>
</html>
