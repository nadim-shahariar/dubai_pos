@extends('layouts.pos')

@section('main-class', 'pos-main-simple')

@section('content')
<div class="simple-pos">
    <section class="entry-card">
        <div class="entry-head">
            <div class="entry-customer">
                <label for="customer-select">Customer</label>
                <select id="customer-select">
                    <option value="">Walk-in Customer</option>
                    @foreach($boot['customers'] as $customer)
                        <option value="{{ $customer['id'] }}"
                                data-name="{{ $customer['name'] }}"
                                data-trn="{{ $customer['trn'] }}"
                                data-credit="{{ $customer['credit_limit'] }}"
                                data-outstanding="{{ $customer['outstanding'] }}">
                            {{ $customer['name'] }}@if($customer['trn']) — {{ $customer['trn'] }}@endif
                        </option>
                    @endforeach
                </select>
                <button class="btn btn-secondary btn-sm" id="new-customer-btn" type="button">+ New customer</button>
                <div class="entry-customer-meta" id="customer-meta">Cash sale · no TRN</div>
            </div>
            <div class="entry-meta">
                <span class="badge {{ $boot['shift'] ? 'badge-green' : 'badge-red' }}" id="entry-shift-badge">{{ $boot['shift'] ? 'Shift open' : 'Shift closed' }}</span>
                <span>Invoice on payment</span>
                <span>{{ auth()->user()->name }} · {{ now()->format('d M Y') }}</span>
                <button class="mini-link" id="shortcuts-btn" type="button">Shortcuts ?</button>
            </div>
        </div>

        <div class="entry-table-wrap">
            <table class="entry-table">
                <thead>
                    <tr>
                        <th style="width:20%">Product name / key</th>
                        <th style="width:15%">Variant</th>
                        <th style="width:10%">Part number</th>
                        <th style="width:8%">Buying code</th>
                        <th style="width:7%">Stock</th>
                        <th style="width:6%">Qty</th>
                        <th style="width:9%">Selling price</th>
                        <th style="width:9%">Discount</th>
                        <th style="width:9%" class="text-right">Total</th>
                        <th style="width:7%"></th>
                    </tr>
                </thead>
                <tbody id="entry-body"></tbody>
            </table>
        </div>

        <div class="entry-actions">
            <button type="button" class="btn btn-secondary" id="add-row-btn">+ Add product row</button>
            <button type="button" class="btn btn-ghost" id="clear-rows-btn">Clear all</button>
            <span class="entry-help">Type a product name, SKU, or key. Pick a suggestion, or choose <strong>Create new</strong> to add a new product inline.</span>
            <span class="entry-shortcuts"><b>F2</b> add row · <b>F3</b> product · <b>F4</b> customer · <b>F9</b> cash / card · <b>F10</b> credit · <b>?</b> help</span>
        </div>
    </section>

    <aside class="entry-summary">
        <div class="entry-summary-head">
            <h2>Invoice summary</h2>
            <span class="small muted" id="entry-count">0 items</span>
        </div>
        <div class="entry-summary-body">
            <div class="total-row"><span>Subtotal</span><b id="summary-subtotal">AED 0.00</b></div>
            <div class="total-row discount"><span>Discount</span><b id="summary-discount">AED 0.00</b></div>
            <div class="total-row"><span>VAT <span id="summary-rate">{{ number_format($boot['tax_rate'], 2) }}%</span></span><b id="summary-vat">AED 0.00</b></div>
            <div class="total-row grand"><span>Total <span class="vat-note" id="summary-vat-note">{{ $boot['prices_include_vat'] ? 'VAT inclusive' : 'VAT exclusive' }}</span></span><b id="summary-total">AED 0.00</b></div>
            <div class="pay-actions">
                <button class="btn pay-main" id="pay-btn" type="button" disabled>Cash / card sale · F9</button>
                <button class="btn btn-gold credit-main" id="credit-sale-btn" type="button" disabled>Credit sale · F10</button>
            </div>
            <div class="small muted" style="margin-top:6px">Credit sale requires a selected customer with available credit.</div>
            <div class="pos-footnote">Discounts and price changes above the cashier limit require a manager PIN at payment.</div>
        </div>
        <div class="entry-summary-foot">
            <button class="btn btn-secondary btn-block" id="close-shift-btn" type="button">Close shift</button>
        </div>
    </aside>
</div>
<div class="product-suggest" id="product-suggest"></div>

{{-- Open shift --}}
<div class="modal-backdrop {{ $boot['shift'] ? '' : 'open' }}" id="shift-modal">
    <div class="modal small">
        <div class="modal-head"><div><h2>Open your shift</h2><p>Count the opening float before the first sale.</p></div></div>
        <form id="shift-form">
            <div class="modal-body">
                <div class="field"><label>Opening float (AED)</label><input type="number" step="0.01" min="0" id="opening-float" value="500.00" required></div>
                <div class="quick-summary">Every cash sale, card payment, credit sale and refund is recorded against this shift.</div>
            </div>
            <div class="modal-foot"><button type="submit" class="btn">Open shift</button></div>
        </form>
    </div>
</div>

{{-- New customer --}}
<div class="modal-backdrop" id="customer-modal">
    <div class="modal">
        <div class="modal-head"><div><h2>New customer</h2><p>Create a customer without leaving the sale. Credit limits can be set later by a manager.</p></div><button class="modal-close" data-close="customer-modal" type="button">×</button></div>
        <form id="customer-form">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field"><label>Contact name *</label><input name="name" id="new-customer-name" required maxlength="150" placeholder="Omar Al Suwaidi"></div>
                    <div class="field"><label>Phone</label><input name="phone" maxlength="30" placeholder="+971 50 000 0000"></div>
                    <div class="field"><label>Company name</label><input name="company_name" maxlength="150" placeholder="Al Quoz Garage LLC"></div>
                    <div class="field"><label>TRN (15 digits)</label><input name="trn" maxlength="15" inputmode="numeric" placeholder="Optional"></div>
                    <div class="field span-2"><label>Email</label><input name="email" type="email" maxlength="150" placeholder="Optional"></div>
                    <div class="field span-2"><label>Billing address</label><textarea name="address" maxlength="1000" placeholder="Optional"></textarea></div>
                </div>
                <div class="quick-summary">The new customer is selected for this sale immediately. Credit limit is 0 until a manager updates the customer record.</div>
            </div>
            <div class="modal-foot"><button class="btn btn-secondary" type="button" data-close="customer-modal">Cancel</button><button class="btn" type="submit">Create &amp; select</button></div>
        </form>
    </div>
</div>

{{-- Hidden buying price --}}
<div class="modal-backdrop" id="buying-price-modal">
    <div class="modal small">
        <div class="modal-head"><div><h2>Buying price</h2><p id="buying-price-product">Hidden internal cost for this new product.</p></div><button class="modal-close" data-close="buying-price-modal" type="button">×</button></div>
        <div class="modal-body">
            <div class="field"><label>Buying price (AED)</label><input id="buying-price-input" type="password" inputmode="decimal" autocomplete="off" placeholder="••••"><div class="hint">Stored as the numeric cost. It is never shown in the sale table or on customer documents.</div></div>
            <div class="quick-summary">Only owners and managers can enter a buying price. Cashiers can create the product and leave the cost pending for review.</div>
        </div>
        <div class="modal-foot"><button class="btn btn-secondary" type="button" data-close="buying-price-modal">Cancel</button><button class="btn" type="button" id="buying-price-save">Save hidden cost</button></div>
    </div>
</div>

{{-- Shortcuts --}}
<div class="modal-backdrop" id="shortcuts-modal">
    <div class="modal small">
        <div class="modal-head"><div><h2>Keyboard shortcuts</h2><p>Work the counter without reaching for the mouse.</p></div><button class="modal-close" data-close="shortcuts-modal" type="button">×</button></div>
        <div class="modal-body">
            <div class="shortcut-list">
                <div><kbd>F2</kbd><span>Add a product row</span></div>
                <div><kbd>F3</kbd><span>Focus product search</span></div>
                <div><kbd>F4</kbd><span>Create a new customer</span></div>
                <div><kbd>Enter</kbd><span>Pick the first product suggestion</span></div>
                <div><kbd>F9</kbd><span>Cash / card sale</span></div>
                <div><kbd>F10</kbd><span>Credit sale</span></div>
                <div><kbd>Esc</kbd><span>Close the open modal</span></div>
                <div><kbd>?</kbd><span>Show this help</span></div>
            </div>
        </div>
        <div class="modal-foot"><button class="btn" type="button" data-close="shortcuts-modal">Got it</button></div>
    </div>
</div>

{{-- New product variant builder --}}
<div class="modal-backdrop" id="new-product-variant-modal">
    <div class="modal wide">
        <div class="modal-head">
            <div><h2>New product variants</h2><p id="npv-product">Add the vehicle variants that belong to this new product.</p></div>
            <button class="modal-close" data-close="new-product-variant-modal" type="button">×</button>
        </div>
        <div class="modal-body">
            <div class="flex items-center justify-between gap-1 mb-1">
                <span class="small muted">Each row becomes a saved vehicle variant with its own part number.</span>
                <button class="btn btn-secondary btn-sm" type="button" id="npv-add">+ Add variant</button>
            </div>
            <div class="new-variant-labels {{ auth()->user()->canViewCost() ? 'with-cost' : '' }}">
                <span>Vehicle brand</span><span>Model</span><span>Year from</span><span>Year to</span><span>Part number *</span><span>Stock</span>
                @if(auth()->user()->canViewCost())<span>Buying price</span>@endif
                <span></span>
            </div>
            <div id="npv-rows"></div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-secondary" type="button" data-close="new-product-variant-modal">Cancel</button>
            <button class="btn" type="button" id="npv-save">Save variants</button>
        </div>
    </div>
</div>

{{-- Variant picker --}}
<div class="modal-backdrop" id="variant-picker-modal">
    <div class="modal wide">
        <div class="modal-head">
            <div><h2>Select variants</h2><p id="variant-picker-product">Choose one or more variants for this product.</p></div>
            <button class="modal-close" data-close="variant-picker-modal" type="button">×</button>
        </div>
        <div class="modal-body">
            <div class="flex items-center justify-between gap-1 mb-1">
                <span class="small muted">Each selected variant is added as its own sale row.</span>
                <button type="button" class="mini-link" id="variant-select-all">Select all</button>
            </div>
            <div id="variant-options" class="variant-options"></div>

            <div class="new-variant-head">
                <div><strong>New vehicle variant</strong><div class="small muted">Add one or more new variants now. They are saved to this product.</div></div>
                <button class="btn btn-secondary btn-sm" type="button" id="add-new-variant">+ New variant</button>
            </div>
            <div class="new-variant-labels {{ auth()->user()->canViewCost() ? 'with-cost' : '' }}">
                <span>Vehicle brand</span><span>Model</span><span>Year from</span><span>Year to</span><span>Part number *</span><span>Stock</span>
                @if(auth()->user()->canViewCost())<span>Buying price</span>@endif
                <span></span>
            </div>
            <div id="new-variant-rows"></div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-secondary" type="button" data-close="variant-picker-modal">Cancel</button>
            <button class="btn" type="button" id="variant-apply">Save &amp; add selected</button>
        </div>
    </div>
</div>

{{-- Payment --}}
<div class="modal-backdrop" id="payment-modal">
    <div class="modal">
        <div class="modal-head"><div><h2>Take payment</h2><p>Split across cash, card, credit or bank transfer.</p></div><button class="modal-close" data-close="payment-modal" type="button">×</button></div>
        <div class="modal-body">
            <div class="modal-total"><span>Amount due</span><b id="pay-total">AED 0.00</b></div>
            <div class="payment-methods">
                <button class="method-btn active" data-method="cash" type="button">Cash</button>
                <button class="method-btn" data-method="card" type="button">Card</button>
                <button class="method-btn" data-method="credit" type="button">Credit</button>
                <button class="method-btn" data-method="bank_transfer" type="button">Bank</button>
            </div>
            <div class="payment-list" id="payment-list"></div>
            <div class="pay-balance" id="pay-balance"><span>Remaining</span><b id="pay-remaining">AED 0.00</b></div>
            <div class="change-line"><span>Change from cash</span><b id="pay-change">AED 0.00</b></div>
            <div class="field mt-2"><label>Manager PIN <span class="muted">(only if discount or price change needs approval)</span></label><input id="approval-pin" type="password" inputmode="numeric" placeholder="••••" autocomplete="off"></div>
            <div class="field"><label>Note (optional)</label><input id="sale-note" maxlength="1000" placeholder="Internal note for this sale"></div>
        </div>
        <div class="modal-foot"><button class="btn btn-secondary" data-close="payment-modal" type="button">Cancel</button><button class="btn btn-lg" id="complete-sale-btn" type="button">Complete sale</button></div>
    </div>
</div>

{{-- Complete --}}
<div class="modal-backdrop" id="complete-modal">
    <div class="modal small">
        <div class="modal-head"><div><h2>Sale complete</h2><p id="complete-sub">Invoice created.</p></div><button class="modal-close" data-close="complete-modal" type="button">×</button></div>
        <div class="modal-body text-center">
            <div style="font-size:36px;line-height:1;margin-bottom:8px">✓</div>
            <div class="stat-value" id="complete-total">AED 0.00</div>
            <div class="small muted mt-1" id="complete-invoice">—</div>
            <div class="quick-summary" id="complete-change" style="margin-top:14px"></div>
        </div>
        <div class="modal-foot" style="justify-content:center;flex-wrap:wrap">
            <a class="btn btn-secondary" id="complete-receipt" target="_blank" href="#">Print receipt</a>
            <a class="btn btn-secondary" id="complete-invoice-link" target="_blank" href="#">A4 invoice</a>
            <button class="btn" id="new-sale-btn" type="button">New sale</button>
        </div>
    </div>
</div>

{{-- Close shift --}}
<div class="modal-backdrop" id="close-shift-modal">
    <div class="modal small">
        <div class="modal-head"><div><h2>Close shift</h2><p>Count the drawer. The system calculates expected cash.</p></div><button class="modal-close" data-close="close-shift-modal" type="button">×</button></div>
        <form id="close-shift-form">
            <div class="modal-body">
                <div class="field mb-2"><label>Counted cash (AED)</label><input id="counted-cash" type="number" min="0" step="0.01" required></div>
                <div class="field"><label>Note</label><input id="close-note" maxlength="1000" placeholder="Optional explanation for a variance"></div>
            </div>
            <div class="modal-foot"><button class="btn btn-secondary" type="button" data-close="close-shift-modal">Cancel</button><button class="btn btn-danger" type="submit">Close shift</button></div>
        </form>
    </div>
</div>
@endsection
