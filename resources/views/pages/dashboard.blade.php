@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="head in">
        <div>
            <div class="eyebrow" id="today"></div>
            <h1 id="greet">Dashboard</h1>
            <p>Here's how your pharmacy is performing today.</p>
        </div>
        <div class="head-r">
            <a href="{{ route('reports') }}" class="btn hide-sm" data-can="reports.view"><svg class="i"><use href="#i-chart"/></svg>View reports</a>
            <a href="{{ route('pos') }}" class="btn primary"><svg class="i"><use href="#i-plus"/></svg>New sale</a>
        </div>
    </div>

    <section class="kpis" id="kpis">
        @for ($i = 0; $i < 4; $i++)
            <div class="card kpi"><span class="skeleton" style="width:50%"></span><span class="skeleton" style="width:70%;height:28px;margin:16px 0 10px"></span><span class="skeleton" style="width:40%"></span></div>
        @endfor
    </section>

    <section class="row r-a">
        <div class="card in" style="animation-delay:.1s">
            <div class="card-h">
                <div><h3>Revenue overview</h3><p>Paid sales over the selected period</p></div>
                <div class="seg" id="range">
                    <button data-r="day" class="on">Today</button>
                    <button data-r="week">7 days</button>
                    <button data-r="month">30 days</button>
                </div>
            </div>
            <div class="chart-sum">
                <div><small>Total revenue</small><b class="num" id="c-total">—</b></div>
                <div><small>Average</small><b class="num" id="c-avg">—</b></div>
                <div><small>Peak</small><b class="num" id="c-peak">—</b></div>
            </div>
            <div id="chart"></div>
        </div>

        <div class="card in" style="animation-delay:.15s">
            <div class="card-h"><div><h3>Sales by category</h3><p>Share of revenue this month</p></div></div>
            <div class="donut-wrap">
                <div class="donut" id="donut"></div>
                <ul class="legend" id="legend"></ul>
            </div>
        </div>
    </section>

    <section class="row r-b">
        <div class="card in" style="animation-delay:.2s">
            <div class="card-h"><div><h3>Recent transactions</h3><p>Latest sales across all counters</p></div><a href="{{ route('transactions') }}" class="link">View all</a></div>
            <div class="table-scroll">
                <table class="tbl">
                    <thead><tr><th>Invoice</th><th>Customer</th><th>Payment</th><th class="r">Amount</th><th>Status</th></tr></thead>
                    <tbody id="txns">{!! str_repeat('<tr><td colspan="5"><span class="skeleton"></span></td></tr>', 5) !!}</tbody>
                </table>
            </div>
        </div>

        <div class="card in" style="animation-delay:.25s">
            <div class="card-h"><div><h3>Inventory alerts</h3><p>Items that need attention</p></div>
                <div class="seg" id="alert-tabs"><button data-a="low" class="on">Low stock</button><button data-a="exp">Expiring</button></div>
            </div>
            <ul class="list" id="alerts"></ul>
        </div>
    </section>

    <section class="row r-c">
        <div class="card in" style="animation-delay:.3s">
            <div class="card-h"><div><h3>Top selling medicines</h3><p>By units sold in the last 7 days</p></div></div>
            <ul class="list" id="top"></ul>
        </div>
        <div class="card in" style="animation-delay:.35s">
            <div class="card-h"><div><h3>Payment methods</h3><p>How customers paid this month</p></div></div>
            <ul class="list" id="payments"></ul>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="/js/pages/dashboard.js"></script>
@endpush
