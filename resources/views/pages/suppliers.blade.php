@extends('layouts.app')

@section('title', 'Suppliers')
@section('can', 'suppliers.manage')

@section('bar-title', 'Suppliers')
@section('bar-subtitle', 'Distributors and manufacturers you order stock from.')
@section('inv-actions')
    <button class="btn" id="compare"><svg class="i"><use href="#i-scale"/></svg>Compare prices</button>
    <button class="btn primary" id="add"><svg class="i"><use href="#i-plus"/></svg>Add supplier</button>
@endsection

@section('content')
    @include('partials.inventory-tabs')

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
    <script src="@asset('js/pages/suppliers.js')"></script>
@endpush
