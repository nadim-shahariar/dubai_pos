@extends('layouts.pos')

@section('content')
<section class="pos-panel">
    <div class="pos-panel-head">
        <h2>Find a part</h2>
        <span class="small muted">Scan · part no · vehicle · cross-ref</span>
    </div>
    <div class="pos-search">
        <div class="search-wrap">
            <input id="search-input" type="text" autocomplete="off" placeholder="Search part, vehicle, barcode or reference…" aria-label="Search products">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
        </div>
        <div class="search-hint"><span>Try <b>Camry 2020</b> or <b>BP-101</b></span><span>F3 to focus</span></div>
    </div>
    <div class="filter-row" id="category-chips">
        <button class="chip active" data-category="">All parts</button>
        @foreach($boot['categories'] as $category)
            <button class="chip" data-category="{{ $category['id'] }}">{{ $category['name'] }}</button>
        @endforeach
    </div>
    <div class="results-scroll">
        <div class="recent-title" id="results-label">Popular right now</div>
        <div class="results-grid" id="search-results">
            @foreach($recent as $product)
                <button type="button" class="product-card" data-category="{{ $product->category_id }}" data-product="{{ json_encode($product->toPosArray(auth()->user())) }}">
                    <div class="pc-top">
                        <span class="pc-part">{{ $product->primary_part_number }}</span>
                        @php($stockClass = ! $product->track_stock ? 'stock-in' : ($product->stock_qty <= 0 ? 'stock-out' : ($product->isLowStock() ? 'stock-low' : 'stock-in')))
                        <span class="stock-dot {{ $stockClass }}">{{ $product->track_stock ? (($product->stock_qty <= 0 ? 'Out' : ($product->isLowStock() ? 'Low ' : '')).(float) $product->stock_qty) : 'Service' }}</span>
                    </div>
                    <div class="pc-name">{{ $product->name }}</div>
                    <div class="pc-fit">{{ $product->fitmentHint() ?: 'Universal / no fitment recorded' }}</div>
                    <div class="pc-bottom">
                        <span class="pc-price">AED {{ number_format((float) $product->selling_price, 2) }}</span>
                        <span class="pc-code">{{ $product->secret_cost_code }}</span>
                    </div>
                </button>
            @endforeach
        </div>
        <div class="search-empty hidden" id="search-empty">
            <strong>No matching part.</strong>
            <div class="small">Try a different number, vehicle, or create it with Quick Add (F2).</div>
            <button class="btn btn-secondary btn-sm mt-2" id="empty-quick-add" type="button">Quick Add product</button>
        </div>
    </div>
</section>

<section class="pos-panel cart-panel">
    <div class="customer-bar">
        <span class="customer-avatar" id="customer-avatar">WI</span>
        <div class="customer-info">
            <div class="customer-name" id="customer-name">Walk-in Customer</div>
            <div class="customer-meta" id="customer-meta">Cash sale · no TRN</div>
        </div>
        <div class="customer-actions">
            <button class="btn btn-secondary btn-sm" id="customer-btn" type="button">Customer</button>
            <button class="btn btn-ghost btn-sm hidden" id="customer-clear" type="button">×</button>
        </div>
    </div>

    <div class="cart-scroll">
        <table class="cart-table">
            <thead><tr><th style="width:46%">Item</th><th>Qty</th><th class="text-right">Price</th></tr></thead>
            <tbody id="cart-body"></tbody>
        </table>
        <div class="cart-empty" id="cart-empty">
            <div>
                <div class="big">🛒</div>
                <strong>Cart is empty</strong>
                <div class="small">Search or scan a part to begin.</div>
            </div>
        </div>
    </div>

    <div class="cart-footer">
        <button class="btn btn-secondary" id="hold-btn" type="button">Hold (F6)</button>
        <button class="btn btn-secondary" id="holds-btn" type="button">Holds <span class="badge badge-gray" id="holds-count">{{ $boot['holds_count'] }}</span></button>
        <button class="btn btn-ghost" id="clear-btn" type="button">Clear</button>
    </div>
</section>

<aside class="pos-panel totals-panel">
    <div class="pos-panel-head"><h2>Checkout</h2><span class="small muted" id="item-count">0 items</span></div>
    <div class="totals-body">
        <div class="total-row"><span>Subtotal</span><b id="subtotal">AED 0.00</b></div>
        <div class="total-row discount"><span>Discounts</span><b id="discount">AED 0.00</b></div>
        <div class="total-row"><span>VAT <span id="vat-rate">{{ number_format($boot['tax_rate'], 2) }}%</span></span><b id="vat">AED 0.00</b></div>
        <div class="total-row grand">
            <span>Total <span class="vat-note" id="vat-note">VAT inclusive</span></span>
            <b id="total">AED 0.00</b>
        </div>
        <div class="pay-actions">
            <button class="btn pay-main" id="pay-btn" type="button" disabled>Pay · F9</button>
            <div class="pay-secondary">
                <button class="btn btn-secondary" id="invoice-discount-btn" type="button">Order discount</button>
                <button class="btn btn-secondary" id="quick-add-btn" type="button">Quick Add · F2</button>
            </div>
        </div>
        <div class="pos-footnote">
            Prices are {{ $boot['prices_include_vat'] ? 'VAT inclusive' : 'VAT exclusive' }}. Manager approval is required above {{ number_format($boot['discount_limit'], 0) }}% discount.
        </div>
    </div>
    <div style="padding:0 14px 14px">
        <button class="btn btn-secondary btn-block" id="close-shift-btn" type="button">Close shift</button>
    </div>
</aside>

<div class="modal-backdrop {{ $boot['shift'] ? '' : 'open' }}" id="shift-modal">
    <div class="modal small">
        <div class="modal-head"><div><h2>Open your shift</h2><p>Count the opening float before the first sale.</p></div></div>
        <form id="shift-form">
            <div class="modal-body">
                <div class="field"><label>Opening float (AED)</label><input type="number" step="0.01" min="0" id="opening-float" value="500.00" required></div>
                <div class="quick-summary">Every cash sale, card payment, credit sale and refund will be recorded against this shift.</div>
            </div>
            <div class="modal-foot"><button type="submit" class="btn">Open shift</button></div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="quick-add-modal">
    <div class="modal wide">
        <div class="modal-head"><div><h2>Quick Add product</h2><p>Create a sellable part without leaving the sale.</p></div><button class="modal-close" data-close="quick-add-modal" type="button">×</button></div>
        <form id="quick-add-form">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field span-2"><label>Product name *</label><input name="name" id="qa-name" required maxlength="150" placeholder="e.g. Front Brake Pad Set"></div>
                    <div class="field"><label>Primary part number *</label><input name="primary_part_number" id="qa-part" required maxlength="50" placeholder="BP-101"></div>
                    <div class="field"><label>Selling price (AED) *</label><input name="selling_price" id="qa-price" type="number" step="0.01" min="0" required></div>
                    <div class="field"><label>Secret cost code *</label><input name="secret_cost_code" id="qa-code" required maxlength="20" placeholder="MAE"><div class="hint">Manual code, not derived from cost. Never printed on customer documents.</div></div>
                    <div class="field"><label>Opening stock</label><input name="stock_qty" id="qa-stock" type="number" step="1" min="0" value="0"></div>
                    @if(auth()->user()->canViewCost())
                        <div class="field"><label>Numeric cost (AED)</label><input name="cost_price" id="qa-cost" type="number" step="0.01" min="0" value="0"><div class="hint">Hidden from cashier screens.</div></div>
                    @endif
                    <div class="field"><label>Category</label><select name="category_id" id="qa-category"><option value="">Uncategorised</option>@foreach($boot['categories'] as $category)<option value="{{ $category['id'] }}">{{ $category['name'] }}</option>@endforeach</select></div>
                    <div class="field">
                        <label>Part brand</label>
                        <input name="brand" id="qa-brand" list="qa-part-brand-list" maxlength="80" placeholder="e.g. Denso" autocomplete="off">
                        <datalist id="qa-part-brand-list">@foreach($boot['part_brands'] as $brand)<option value="{{ $brand }}"></option>@endforeach</datalist>
                        <div class="hint">Start typing to pick a saved brand, or type a new one and save it with the product.</div>
                    </div>
                    <div class="field span-2">
                        <div class="flex items-center justify-between gap-1 mb-1">
                            <label style="margin-bottom:0">Vehicle variants / fitments</label>
                            <button type="button" class="btn btn-secondary btn-sm" data-add-variant="qa-variants">+ Add variant</button>
                        </div>
                        <div class="table-wrap" style="border:1px solid var(--line);border-radius:9px">
                            <table class="table" id="qa-variants" data-variant-table style="margin:0">
                                <thead><tr><th>Vehicle brand</th><th>Model</th><th>Year from</th><th>Year to</th><th>Mapped no.</th><th></th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="hint">Optional. Add every vehicle that fits this part as a separate row. Existing brands and models appear as suggestions; new names are saved to the database.</div>
                    </div>
                </div>
                <div class="quick-summary" id="qa-status">Duplicate checks run when you submit. The product is added to the cart immediately after creation.</div>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-secondary" data-close="quick-add-modal">Cancel</button><button type="submit" class="btn">Create &amp; add</button></div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="customer-modal">
    <div class="modal">
        <div class="modal-head"><div><h2>Select customer</h2><p>Walk-in, company, or credit account.</p></div><button class="modal-close" data-close="customer-modal" type="button">×</button></div>
        <div class="modal-body">
            <div class="field mb-2"><label>Search name, phone or TRN</label><input id="customer-search" placeholder="Start typing…"></div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <span class="small muted">Company customers with credit limits</span>
                <button class="mini-link" id="walk-in-btn" type="button">Use walk-in</button>
            </div>
            <div id="customer-results"><div class="empty" style="padding:18px 0">Search for a customer.</div></div>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="discount-modal">
    <div class="modal small">
        <div class="modal-head"><div><h2 id="discount-title">Line discount</h2><p id="discount-sub">Apply a discount to this item.</p></div><button class="modal-close" data-close="discount-modal" type="button">×</button></div>
        <div class="modal-body">
            <div class="form-grid">
                <div class="field"><label>Type</label><select id="discount-type"><option value="percent">Percent (%)</option><option value="amount">Amount (AED)</option></select></div>
                <div class="field"><label>Value</label><input id="discount-value" type="number" min="0" step="0.01" value="0"></div>
            </div>
            <div class="quick-summary" id="discount-hint">Cashier limit is {{ number_format($boot['discount_limit'], 0) }}%. Above this, a manager PIN is required at payment.</div>
        </div>
        <div class="modal-foot"><button class="btn btn-secondary" data-close="discount-modal" type="button">Cancel</button><button class="btn" id="discount-apply" type="button">Apply</button></div>
    </div>
</div>

<div class="modal-backdrop" id="override-modal">
    <div class="modal small">
        <div class="modal-head"><div><h2>Override unit price</h2><p>The catalogue price is not changed.</p></div><button class="modal-close" data-close="override-modal" type="button">×</button></div>
        <div class="modal-body">
            <div class="field mb-2"><label>New unit price (AED)</label><input id="override-price" type="number" min="0" step="0.01"></div>
            <div class="field"><label>Reason *</label><input id="override-reason" maxlength="255" placeholder="Price match / damaged packaging / trade customer"></div>
            <div class="quick-summary">A price below cost, or a cashier override, requires a manager PIN at payment.</div>
        </div>
        <div class="modal-foot"><button class="btn btn-secondary" data-close="override-modal" type="button">Cancel</button><button class="btn" id="override-apply" type="button">Apply override</button></div>
    </div>
</div>

<div class="modal-backdrop" id="holds-modal">
    <div class="modal">
        <div class="modal-head"><div><h2>Held carts</h2><p>Resume a parked sale on this terminal.</p></div><button class="modal-close" data-close="holds-modal" type="button">×</button></div>
        <div class="modal-body" id="holds-list"><div class="empty">No held carts.</div></div>
        <div class="modal-foot"><button class="btn btn-secondary" data-close="holds-modal" type="button">Close</button><button class="btn" id="save-hold-btn" type="button">Hold current cart</button></div>
    </div>
</div>

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
            <div class="field mt-2"><label>Manager PIN <span class="muted">(only if a discount or override needs approval)</span></label><input id="approval-pin" type="password" inputmode="numeric" placeholder="••••" autocomplete="off"></div>
            <div class="field"><label>Note (optional)</label><input id="sale-note" maxlength="1000" placeholder="Internal note for this sale"></div>
        </div>
        <div class="modal-foot"><button class="btn btn-secondary" data-close="payment-modal" type="button">Cancel</button><button class="btn btn-lg" id="complete-sale-btn" type="button">Complete sale</button></div>
    </div>
</div>

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

<div class="modal-backdrop" id="close-shift-modal">
    <div class="modal small">
        <div class="modal-head"><div><h2>Close shift</h2><p>Count the drawer. The system calculates the expected cash.</p></div><button class="modal-close" data-close="close-shift-modal" type="button">×</button></div>
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
