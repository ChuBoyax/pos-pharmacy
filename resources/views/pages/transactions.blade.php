@extends('layouts.app')

@section('title', 'Transactions')
@section('can', 'pos.sell,sales.view_all')

@section('content')
    <div class="head">
        <div>
            <div class="eyebrow">Sales</div>
            <h1>Transactions</h1>
            <p>Browse, reprint and void past sales.</p>
        </div>
        <div class="head-r">
            <a href="{{ route('pos') }}" class="btn primary"><svg class="i"><use href="#i-plus"/></svg>New sale</a>
        </div>
    </div>

    <div class="card">
        <div class="toolbar">
            <label class="in-search"><svg class="i"><use href="#i-search"/></svg><input class="input" id="q" type="search" placeholder="Invoice no. or customer"></label>
            <select class="input" id="status">
                <option value="">All statuses</option>
                <option value="paid">Paid</option>
                <option value="voided">Voided</option>
            </select>
            <select class="input" id="method">
                <option value="">All payments</option>
                <option value="cash">Cash</option>
                <option value="gcash">GCash</option>
                <option value="card">Card</option>
            </select>
            <input class="input" type="date" id="from" aria-label="From date">
            <input class="input" type="date" id="to" aria-label="To date">
        </div>
        <div class="table-scroll">
            <table class="tbl">
                <thead><tr><th>Invoice</th><th>Date & time</th><th>Customer</th><th>Cashier</th><th class="r">Items</th><th>Payment</th><th class="r">Total</th><th>Status</th><th></th></tr></thead>
                <tbody id="rows"></tbody>
            </table>
        </div>
        <div class="pager" id="pager"></div>
    </div>
@endsection

@push('scripts')
    <script src="/js/pages/transactions.js"></script>
@endpush
