@extends('layouts.app')

@section('title', 'Roles & Permissions')
@section('can', 'roles.manage')

@section('bar-title', 'Roles & permissions')
@section('bar-subtitle', "Decide what each role can see and do. Changes apply on the user's next page load.")
@section('bar-actions')
    <button class="btn primary" id="add"><svg class="i"><use href="#i-plus"/></svg>New role</button>
@endsection

@section('content')
    <section class="roles" id="roles"></section>

    <div class="card">
        <div class="card-h"><div><h3>Permission matrix</h3><p>Who can do what, at a glance</p></div></div>
        <div class="table-scroll">
            <table class="tbl matrix" id="matrix"></table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="@asset('js/pages/roles.js')"></script>
@endpush
