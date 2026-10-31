@extends('layouts.app')

@section('title', 'Users')
@section('can', 'users.manage')

@section('content')
    <div class="head">
        <div>
            <div class="eyebrow">Management</div>
            <h1>Users & roles</h1>
            <p>Staff accounts and what each one can access.</p>
        </div>
        <div class="head-r">
            <a class="btn" href="{{ route('roles') }}" data-can="roles.manage"><svg class="i"><use href="#i-shield"/></svg>Roles & permissions</a>
            <button class="btn primary" id="add"><svg class="i"><use href="#i-plus"/></svg>Add user</button>
        </div>
    </div>

    <section class="kpis" id="role-cards"></section>

    <div class="card">
        <div class="toolbar">
            <label class="in-search"><svg class="i"><use href="#i-search"/></svg><input class="input" id="q" type="search" placeholder="Name or email"></label>
            <select class="input" id="role-filter">
                <option value="">All roles</option>
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
    <script src="@asset('js/pages/users.js')"></script>
@endpush
