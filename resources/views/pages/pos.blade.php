@extends('layouts.app')

@section('title', 'Point of Sale')
@section('can', 'pos.sell')
@section('page-class', 'fill')

@section('content')
    <div class="head">
        <div>
            <div class="eyebrow">Point of Sale</div>
            <h1>New sale</h1>
            <p>Search or scan a product, then charge the customer.</p>
        </div>
    </div>

    <div class="pos">
        <section class="pos-left">
            <div class="pos-search">
                <label class="in-search">
                    <svg class="i"><use href="#i-search"/></svg>
                    <input class="input" id="q" type="search" placeholder="Search medicine or scan barcode" autocomplete="off">
                    <kbd>/</kbd>
                </label>
            </div>
            <div class="chips-wrap" id="chips-wrap">
                <button type="button" class="chip-nav prev" id="chip-prev" aria-label="Scroll categories left" hidden><svg class="i"><use href="#i-chevron"/></svg></button>
                <div class="chips" id="chips"><button class="on" data-c="">All</button></div>
                <button type="button" class="chip-nav next" id="chip-next" aria-label="Scroll categories right" hidden><svg class="i"><use href="#i-chevron"/></svg></button>
            </div>
            <div class="products" id="products"></div>
        </section>

        <aside class="card cart">
            <div class="cart-h">
                <h3>Current sale <span class="muted" id="cart-count"></span></h3>
                <button class="btn sm" id="clear" type="button">Clear</button>
            </div>
            <div class="cart-items" id="cart"></div>

            <form class="cart-f" id="checkout" novalidate>
                <details class="more" id="more">
                    <summary>Discount, customer &amp; prescription</summary>
                    <div class="form">
                    <label class="f">
                        <span>Discount</span>
                        <select class="input" name="discount_type" id="discount">
                            <option value="none">None</option>
                            <option value="senior">Senior citizen</option>
                            <option value="pwd">PWD</option>
                        </select>
                    </label>
                    <label class="f">
                        <span id="customer-label">Customer (optional)</span>
                        <input class="input" name="customer_name" placeholder="Walk-in customer">
                        <small class="f-err" data-err="customer_name"></small>
                    </label>
                    <label class="f full" id="id-field" hidden>
                        <span>Senior / PWD ID number</span>
                        <input class="input" name="discount_id_no" placeholder="e.g. SC-0012345">
                        <small class="f-err" data-err="discount_id_no"></small>
                    </label>
                    <label class="f full" id="rx-field" hidden>
                        <span>Prescription reference <span class="tag rx">RX</span></span>
                        <input class="input" name="prescription_ref" placeholder="Doctor / prescription no.">
                        <small class="f-err" data-err="prescription_ref"></small>
                    </label>
                    </div>
                </details>

                <div class="sum" id="sum"></div>

                <div>
                    <div class="lbl-sm">Payment method</div>
                    <div class="pay" id="pay">
                        <button type="button" data-m="cash" class="on"><svg class="i"><use href="#i-cash"/></svg>Cash</button>
                        <button type="button" data-m="gcash"><svg class="i"><use href="#i-phone"/></svg>GCash</button>
                        <button type="button" data-m="card"><svg class="i"><use href="#i-card"/></svg>Card</button>
                    </div>
                </div>

                <div id="pay-cash" class="cash">
                    <label class="tender">
                        <span>Amount tendered</span>
                        {{-- inputmode="none": use the on-screen keypad, so phones don't pop up their keyboard --}}
                        <input class="num" name="amount_paid" id="tendered" type="text" inputmode="none" autocomplete="off" placeholder="₱0.00">
                    </label>
                    <small class="f-err" data-err="amount_paid"></small>
                    <div class="quick" id="quick"></div>
                    <div class="keypad" id="keypad">
                        <button type="button" data-k="7">7</button><button type="button" data-k="8">8</button><button type="button" data-k="9">9</button>
                        <button type="button" data-k="back" class="fn" aria-label="Backspace"><svg class="i"><use href="#i-backspace"/></svg></button>
                        <button type="button" data-k="4">4</button><button type="button" data-k="5">5</button><button type="button" data-k="6">6</button>
                        <button type="button" data-k="clear" class="fn">C</button>
                        <button type="button" data-k="1">1</button><button type="button" data-k="2">2</button><button type="button" data-k="3">3</button>
                        <button type="button" data-k="00">00</button>
                        <button type="button" data-k="0" class="wide">0</button><button type="button" data-k=".">.</button>
                        <button type="button" data-k="exact" class="exact">Exact</button>
                    </div>
                    <div class="change" id="change"><span>Change</span><span class="num">₱0.00</span></div>
                </div>

                <label class="f" id="pay-ref" hidden>
                    <span>Reference number</span>
                    <input class="input" name="reference_no" placeholder="Transaction / approval code">
                    <small class="f-err" data-err="reference_no"></small>
                </label>

                <button class="btn primary block" id="charge" type="submit" disabled>Charge ₱0.00</button>
            </form>
        </aside>
    </div>
@endsection

@push('scripts')
    <script src="@asset('js/pages/pos.js')"></script>
@endpush
