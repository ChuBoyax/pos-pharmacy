@extends('layouts.app')

@section('title', 'Reports')
@section('can', 'reports.view')

@section('bar-title', 'Sales reports')
@section('bar-subtitle')
    <span id="range-label">Performance for the selected period.</span>
@endsection
@section('bar-actions')
    <button class="btn" id="export"><svg class="i"><use href="#i-download"/></svg>Export CSV</button>
@endsection

@section('content')
    <div class="card">
        <div class="toolbar" style="margin:0">
            <div class="seg presets" id="presets">
                <button data-p="today">Today</button>
                <button data-p="7d">7 days</button>
                <button data-p="month" class="on">This month</button>
                <button data-p="last">Last month</button>
            </div>
            <span class="spacer"></span>
            <input class="input" type="date" id="from" aria-label="From date">
            <span class="muted">to</span>
            <input class="input" type="date" id="to" aria-label="To date">
        </div>
    </div>

    <section class="kpis six" id="summary"></section>

    <div class="card">
        <div class="card-h"><div><h3>Daily net sales</h3><p>Paid transactions per day</p></div></div>
        <div id="daily-chart"></div>
    </div>

    <section class="split">
        <div class="card">
            <div class="card-h"><div><h3>Top products</h3><p>Ranked by revenue</p></div></div>
            <div class="table-scroll"><table class="tbl"><thead><tr><th>#</th><th>Product</th><th class="r">Units</th><th class="r">Revenue</th></tr></thead><tbody id="products"></tbody></table></div>
        </div>
        <div class="card">
            <div class="card-h"><div><h3>By category</h3><p>Units and revenue per category</p></div></div>
            <div class="table-scroll"><table class="tbl"><thead><tr><th>Category</th><th class="r">Units</th><th class="r">Revenue</th><th class="r">Share</th></tr></thead><tbody id="categories"></tbody></table></div>
        </div>
    </section>

    <section class="split">
        <div class="card">
            <div class="card-h"><div><h3>By cashier</h3><p>Sales rung up per staff member</p></div></div>
            <div class="table-scroll"><table class="tbl"><thead><tr><th>Staff</th><th class="r">Transactions</th><th class="r">Total</th></tr></thead><tbody id="cashiers"></tbody></table></div>
        </div>
        <div class="card">
            <div class="card-h"><div><h3>Payments & discounts</h3><p>Payment methods and statutory discounts</p></div></div>
            <div class="table-scroll"><table class="tbl"><thead><tr><th>Type</th><th class="r">Transactions</th><th class="r">Amount</th></tr></thead><tbody id="payments"></tbody></table></div>
        </div>
    </section>

    <div class="card">
        <div class="card-h"><div><h3>Daily breakdown</h3></div></div>
        <div class="table-scroll"><table class="tbl"><thead><tr><th>Date</th><th class="r">Transactions</th><th class="r">Discounts</th><th class="r">Net sales</th></tr></thead><tbody id="daily"></tbody></table></div>
    </div>
@endsection

@push('scripts')
    <script src="@asset('js/pages/reports.js')"></script>
@endpush
