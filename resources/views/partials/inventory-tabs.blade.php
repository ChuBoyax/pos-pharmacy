{{-- Medicines, Stock and Suppliers share one "Inventory" sidebar item; these tabs switch between them. --}}
<div class="inv-bar">
    <nav class="tabs inv-tabs" aria-label="Inventory">
        @foreach (['medicines' => 'pill', 'stock' => 'box', 'suppliers' => 'truck'] as $route => $icon)
            @php [$label, $can] = App\Models\Role::MENUS[$route]; @endphp
            <a href="{{ route($route) }}" data-can="{{ $can }}" data-menu="{{ $route }}" @class(['on' => request()->routeIs($route)])>
                <svg class="i"><use href="#i-{{ $icon }}"/></svg>{{ $label }}
                @if ($route === 'stock')
                    <span class="count stock-badge" hidden></span>
                @endif
            </a>
        @endforeach
    </nav>
    <div class="inv-actions">@yield('inv-actions')</div>
</div>
