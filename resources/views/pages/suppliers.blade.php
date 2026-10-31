@extends('layouts.app')

@section('title', 'Suppliers')
@section('roles', 'admin,pharmacist')

@section('content')
    <div class="head">
        <div>
            <div class="eyebrow">Inventory</div>
            <h1>Suppliers</h1>
            <p>Distributors and manufacturers you order stock from.</p>
        </div>
        <div class="head-r">
            <button class="btn" id="compare"><svg class="i"><use href="#i-scale"/></svg>Compare prices</button>
            <button class="btn primary" id="add"><svg class="i"><use href="#i-plus"/></svg>Add supplier</button>
        </div>
    </div>

    <div class="card">
        <div class="toolbar">
            <label class="in-search"><svg class="i"><use href="#i-search"/></svg><input class="input" id="q" type="search" placeholder="Name, contact person or email"></label>
        </div>
        <div class="table-scroll">
            <table class="tbl">
                <thead><tr><th>Supplier</th><th>Contact person</th><th>Phone</th><th>Email</th><th class="r">Products</th><th></th></tr></thead>
                <tbody id="rows"></tbody>
            </table>
        </div>
        <div class="pager" id="pager"></div>
    </div>
@endsection

@push('scripts')
    <script src="/js/pages/suppliers.js"></script>
@endpush
