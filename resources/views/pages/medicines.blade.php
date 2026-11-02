@extends('layouts.app')

@section('title', 'Medicines')
@section('can', 'medicines.manage')

@section('bar-title', 'Medicines')
@section('bar-subtitle', 'Your product catalogue, prices and reorder levels.')
@section('inv-actions')
    <button class="btn" id="manage-cats"><svg class="i"><use href="#i-tag"/></svg>Categories</button>
    <button class="btn primary" id="add"><svg class="i"><use href="#i-plus"/></svg>Add medicine</button>
@endsection

@section('content')
    @include('partials.inventory-tabs')

    <div class="card">
        <div class="toolbar">
            <label class="in-search"><svg class="i"><use href="#i-search"/></svg><input class="input" id="q" type="search" placeholder="Name, generic name or SKU"></label>
            <select class="input" id="cat"><option value="">All categories</option></select>
            <select class="input" id="type">
                <option value="">All types</option>
                @foreach (App\Models\Medicine::PRODUCT_TYPES as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select class="input" id="stock">
                <option value="">All stock levels</option>
                <option value="low">Low stock</option>
                <option value="out">Out of stock</option>
            </select>
        </div>
        <div class="table-scroll">
            <table class="tbl">
                <thead id="head"><tr>
                    <th data-sort="name" aria-sort="ascending"><button type="button" class="th-sort">Medicine</button></th>
                    <th data-sort="category"><button type="button" class="th-sort">Category</button></th>
                    <th data-sort="product_type"><button type="button" class="th-sort">Type</button></th>
                    <th class="r" data-sort="price"><button type="button" class="th-sort">Price</button></th>
                    <th class="r" data-sort="stock"><button type="button" class="th-sort">Stock</button></th>
                    <th>Supplier</th><th>Status</th><th></th>
                </tr></thead>
                <tbody id="rows"></tbody>
            </table>
        </div>
        <div class="pager" id="pager"></div>
    </div>
@endsection

@push('scripts')
    <script src="@asset('js/pages/medicines.js')"></script>
@endpush
