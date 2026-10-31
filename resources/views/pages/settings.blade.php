@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <div class="head">
        <div>
            <div class="eyebrow">Management</div>
            <h1>Settings</h1>
            <p>Your account and, for admins, how the store and receipts are set up.</p>
        </div>
    </div>

    <div class="settings">
        <div style="display:grid;gap:16px">
            <form class="card" id="profile" novalidate>
                <div class="profile-head">
                    <div class="avatar" id="p-avatar">?</div>
                    <div><b id="p-name"></b><small class="muted" id="p-role" style="text-transform:capitalize"></small></div>
                </div>
                <div class="card-h"><div><h3>My profile</h3><p>Name and email used to sign in</p></div></div>
                <div class="form">
                    <label class="f full"><span>Full name</span><input class="input" name="name" required><small class="f-err" data-err="name"></small></label>
                    <label class="f full"><span>Email</span><input class="input" name="email" type="email" required><small class="f-err" data-err="email"></small></label>
                </div>
                <button class="btn primary" type="submit">Save profile</button>
            </form>

            <form class="card" id="password" novalidate>
                <div class="card-h"><div><h3>Change password</h3><p>You will stay signed in here; other devices are signed out.</p></div></div>
                <div class="form">
                    <label class="f full"><span>Current password</span><input class="input" name="current_password" type="password" autocomplete="current-password"><small class="f-err" data-err="current_password"></small></label>
                    <label class="f"><span>New password</span><input class="input" name="password" type="password" autocomplete="new-password"><small class="f-err" data-err="password"></small></label>
                    <label class="f"><span>Confirm new password</span><input class="input" name="password_confirmation" type="password" autocomplete="new-password"></label>
                </div>
                <button class="btn primary" type="submit"><svg class="i"><use href="#i-lock"/></svg>Update password</button>
            </form>
        </div>

        <form class="card" id="store" novalidate data-roles="admin">
            <div class="card-h"><div><h3>Store & receipt</h3><p>Printed on every receipt</p></div><svg class="i" style="color:var(--muted)"><use href="#i-store"/></svg></div>
            <div class="form">
                <label class="f full"><span>Store name</span><input class="input" name="store_name" required><small class="f-err" data-err="store_name"></small></label>
                <label class="f full"><span>Address</span><input class="input" name="store_address"><small class="f-err" data-err="store_address"></small></label>
                <label class="f"><span>Phone</span><input class="input" name="store_phone"><small class="f-err" data-err="store_phone"></small></label>
                <label class="f"><span>VAT reg. TIN</span><input class="input" name="store_tin"><small class="f-err" data-err="store_tin"></small></label>
                <label class="f full"><span>Receipt footer</span><input class="input" name="receipt_footer"><small class="f-err" data-err="receipt_footer"></small></label>
            </div>
            <div class="card-sep"></div>
            <div class="card-h"><div><h3>Sales & inventory rules</h3><p>Used by checkout, alerts and the dashboard</p></div></div>
            <div class="form">
                <label class="f"><span>VAT rate (%)</span><input class="input" name="vat_rate" type="number" min="0" max="100" step="0.01"><small class="f-err" data-err="vat_rate"></small></label>
                <label class="f"><span>Senior / PWD discount (%)</span><input class="input" name="senior_discount_rate" type="number" min="0" max="100" step="0.01"><small class="f-err" data-err="senior_discount_rate"></small></label>
                <label class="f"><span>Daily sales target (₱)</span><input class="input" name="daily_target" type="number" min="0" step="100"><small class="f-err" data-err="daily_target"></small></label>
                <label class="f"><span>Expiry warning (days)</span><input class="input" name="expiry_warning_days" type="number" min="1" max="365"><small class="f-err" data-err="expiry_warning_days"></small></label>
            </div>
            <button class="btn primary" type="submit">Save store settings</button>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="/js/pages/settings.js"></script>
@endpush
