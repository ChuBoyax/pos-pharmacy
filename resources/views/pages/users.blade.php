@extends('layouts.app')

@section('title', 'Users')
@section('roles', 'admin')

@section('content')
    <div class="head">
        <div>
            <div class="eyebrow">Management</div>
            <h1>Users & roles</h1>
            <p>Staff accounts and what each one can access.</p>
        </div>
        <div class="head-r">
            <button class="btn primary" id="add"><svg class="i"><use href="#i-plus"/></svg>Add user</button>
        </div>
    </div>

    <section class="kpis three">
        <div class="card mini"><small>Admin</small><b>Full access</b><span>Everything, including users and store settings</span></div>
        <div class="card mini"><small>Pharmacist</small><b>Inventory & reports</b><span>Sell, manage medicines, stock and suppliers, void sales</span></div>
        <div class="card mini"><small>Cashier</small><b>Sales only</b><span>Ring up sales and view their own transactions</span></div>
    </section>

    <div class="card">
        <div class="toolbar">
            <label class="in-search"><svg class="i"><use href="#i-search"/></svg><input class="input" id="q" type="search" placeholder="Name or email"></label>
            <select class="input" id="role">
                <option value="">All roles</option>
                <option value="admin">Admin</option>
                <option value="pharmacist">Pharmacist</option>
                <option value="cashier">Cashier</option>
            </select>
        </div>
        <div class="table-scroll">
            <table class="tbl">
                <thead><tr><th>User</th><th>Role</th><th>Status</th><th class="r">Sales</th><th>Member since</th><th></th></tr></thead>
                <tbody id="rows"></tbody>
            </table>
        </div>
        <div class="pager" id="pager"></div>
    </div>
@endsection

@push('scripts')
    <script src="/js/pages/users.js"></script>
@endpush
