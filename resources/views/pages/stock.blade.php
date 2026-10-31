@extends('layouts.app')

@section('title', 'Stock')
@section('can', 'stock.manage')

@section('content')
    <div class="head">
        <div>
            <div class="eyebrow">Inventory</div>
            <h1>Stock & batches</h1>
            <p>Receive deliveries, track expiry dates and correct counts. Sales always use the earliest-expiring batch first.</p>
        </div>
        <div class="head-r">
            <button class="btn primary" id="receive"><svg class="i"><use href="#i-plus"/></svg>Receive stock</button>
        </div>
    </div>

    <div class="card">
        <div class="tabs" id="tabs">
            <button data-s="active" class="on">In stock</button>
            <button data-s="expiring">Expiring soon</button>
            <button data-s="expired">Expired</button>
            <button data-s="empty">Depleted</button>
            <button data-s="recent">Recent deliveries</button>
        </div>
        <div class="toolbar">
            <label class="in-search"><svg class="i"><use href="#i-search"/></svg><input class="input" id="q" type="search" placeholder="Medicine, SKU or batch no."></label>
        </div>
        <div class="table-scroll">
            <table class="tbl">
                <thead><tr><th>Medicine</th><th>Batch</th><th>Supplier</th><th>Received</th><th>Expiry</th><th>On hand</th><th class="r">Unit cost</th><th></th></tr></thead>
                <tbody id="rows"></tbody>
            </table>
        </div>
        <div class="pager" id="pager"></div>
    </div>
@endsection

@push('scripts')
    <script src="/js/pages/stock.js"></script>
@endpush
