@extends('layouts.app')

@section('title', 'Catalogue')
@section('subtitle', 'Vehicle parts, fitments and cross-references')

@section('content')
<div class="page-head">
    <div>
        <h1>Parts catalogue</h1>
        <p>Search by part number, name, barcode or cross-reference. Keep fitment data complete so the counter can find the right part fast.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="{{ route('products.index', ['filter' => 'low']) }}">Low stock</a>
        <button class="btn" type="button" id="quick-add-product">+ Quick add <span class="shortcut-key">F2</span></button>
        <a class="btn btn-secondary" href="{{ route('products.create') }}">Full form</a>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <form method="GET" class="inline-form" style="flex:1">
            <div class="field" style="max-width:420px"><label>Search catalogue</label><input name="q" value="{{ $q }}" placeholder="Part number, name, barcode, cross-reference…"></div>
            <button class="btn btn-secondary" type="submit">Search</button>
            @if($q)<a class="btn btn-ghost" href="{{ route('products.index') }}">Clear</a>@endif
        </form>
        <span class="small muted">{{ $products->total() }} products</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Part</th><th>Product</th><th>Category</th><th>Stock</th><th>Price</th><th>Code</th>@if(auth()->user()->canViewCost())<th>Cost / margin</th>@endif<th>Fitments</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($products as $product)
                <tr>
                    <td class="main-cell mono">{{ $product->primary_part_number }}
                        @if($product->barcode)<div class="sub">{{ $product->barcode }}</div>@endif
                    </td>
                    <td><div class="main-cell">{{ $product->name }}</div><div class="sub">{{ $product->brand ?: 'No brand' }} · {{ $product->fitmentHint() ?: 'No fitment' }}</div></td>
                    <td>{{ $product->category?->name ?? '—' }}</td>
                    <td>
                        @if(!$product->track_stock)
                            <span class="badge badge-gray">Service</span>
                        @elseif((float) $product->stock_qty <= 0)
                            <span class="badge badge-red">Out</span>
                        @elseif($product->isLowStock())
                            <span class="badge badge-gold">Low · {{ (float) $product->stock_qty }}</span>
                        @else
                            <span class="badge badge-green">{{ (float) $product->stock_qty }}</span>
                        @endif
                    </td>
                    <td class="nowrap">AED {{ number_format((float) $product->selling_price, 2) }}</td>
                    <td><span class="code-chip">{{ $product->secret_cost_code }}</span></td>
                    @if(auth()->user()->canViewCost())
                        <td class="nowrap">AED {{ number_format((float) $product->cost_price, 2) }}<div class="sub">{{ number_format($product->margin(), 1) }}%</div></td>
                    @endif
                    <td><span class="badge badge-gray">{{ $product->fitments_count }}</span> <span class="small muted">{{ $product->aliases_count }} {{ $product->aliases_count === 1 ? 'ref' : 'refs' }}</span></td>
                    <td class="text-right"><a class="btn btn-secondary btn-sm" href="{{ route('products.edit', $product) }}">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="empty"><strong>No products found.</strong>Try a different search or add the first part.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($products->hasPages())
    <div class="pagination">
        <span class="info">Page {{ $products->currentPage() }} of {{ $products->lastPage() }}</span>
        @if($products->previousPageUrl())<a href="{{ $products->previousPageUrl() }}">← Previous</a>@else<span class="disabled">← Previous</span>@endif
        @if($products->nextPageUrl())<a href="{{ $products->nextPageUrl() }}">Next →</a>@else<span class="disabled">Next →</span>@endif
    </div>
@endif

{{-- Quick add product --}}
<div class="modal-backdrop" id="quick-product-modal">
    <div class="modal wide">
        <div class="modal-head">
            <div><h2>Quick add product</h2><p>Create the core product and its vehicle variants without leaving the catalogue. Press <strong>Enter</strong> to move to the next field.</p></div>
            <button class="modal-close" type="button" data-close-quick-product>×</button>
        </div>
        <form id="catalog-quick-form" data-edit-template="{{ route('products.edit', ['product' => '__ID__']) }}">
            <div class="modal-body">
                <div id="catalog-quick-error" class="alert alert-error hidden"></div>
                <div class="form-grid">
                    <div class="field"><label>Primary part number *</label><input name="primary_part_number" id="cq-part" required maxlength="50" placeholder="BP-101" autocomplete="off"></div>
                    <div class="field"><label>Product name *</label><input name="name" id="cq-name" required maxlength="150" placeholder="Front Brake Pad Set"></div>
                    <div class="field"><label>Selling price (AED) *</label><input name="selling_price" type="number" step="0.01" min="0" required></div>
                    <div class="field"><label>Secret cost code *</label><input name="secret_cost_code" required maxlength="20" placeholder="MAE" class="mono"></div>
                    <div class="field"><label>Opening stock</label><input name="stock_qty" type="number" step="1" min="0" value="0"></div>
                    <div class="field"><label>Category</label><select name="category_id"><option value="">Uncategorised</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Part brand</label><input name="brand" list="catalog-part-brand-options" maxlength="80" placeholder="Denso, Bosch, Toyota Genuine" autocomplete="off"><datalist id="catalog-part-brand-options">@foreach($partBrands as $partBrand)<option value="{{ $partBrand }}"></option>@endforeach</datalist></div>
                    <div class="field"><label>Barcode / EAN</label><input name="barcode" maxlength="64" placeholder="Optional"></div>
                    @if(auth()->user()->canViewCost())
                        <div class="field"><label>Buying price (AED)</label><input name="cost_price" type="number" step="0.01" min="0" placeholder="Optional"><div class="hint">Hidden from cashier screens and customer documents.</div></div>
                    @endif
                </div>

                <div class="card-head" style="padding:14px 0 8px;border:0">
                    <div><strong>Vehicle variants</strong><div class="small muted">Optional. Add every vehicle fitment for this product.</div></div>
                    <button class="btn btn-secondary btn-sm" type="button" data-add-variant="catalog-quick-variants">+ Add variant</button>
                </div>
                <div class="table-wrap" style="border:1px solid var(--line);border-radius:9px">
                    <table class="table" id="catalog-quick-variants" data-variant-table style="margin:0">
                        <thead><tr><th>Vehicle brand</th><th>Model</th><th>Year from</th><th>Year to</th><th>Part number *</th><th>Stock</th><th></th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="hint">A new vehicle brand or model is saved automatically. Part number is required for each variant.</div>
            </div>
            <div class="modal-foot">
                <button class="btn btn-secondary" type="button" data-close-quick-product>Cancel</button>
                <button class="btn" type="submit" id="cq-submit">Create product</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
window.VEHICLE_CATALOG = @json($brands->map(fn ($brand) => ['name' => $brand->name, 'models' => $brand->models->pluck('name')->values()])->values());
(() => {
    const modal = document.getElementById('quick-product-modal');
    const form = document.getElementById('catalog-quick-form');
    const errorBox = document.getElementById('catalog-quick-error');
    const open = () => {
        form.reset();
        errorBox.classList.add('hidden');
        window.resetVariantTable?.('catalog-quick-variants');
        modal.classList.add('open');
        setTimeout(() => document.getElementById('cq-part').focus(), 100);
    };
    const close = () => modal.classList.remove('open');
    document.getElementById('quick-add-product').addEventListener('click', open);
    document.querySelectorAll('[data-close-quick-product]').forEach(button => button.addEventListener('click', close));
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    document.addEventListener('keydown', event => {
        if (event.key === 'F2' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
            event.preventDefault();
            open();
        }
        if (event.key === 'Escape' && modal.classList.contains('open')) close();
    });

    form.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || !event.target.matches('input, select, textarea')) return;
        event.preventDefault();
        const fields = Array.from(form.querySelectorAll('input, select, textarea'))
            .filter(field => !field.disabled && field.offsetParent !== null);
        const index = fields.indexOf(event.target);
        if (index >= 0 && fields[index + 1]) fields[index + 1].focus();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = document.getElementById('cq-submit');
        button.disabled = true;
        errorBox.classList.add('hidden');
        const data = new FormData(form);
        const payload = Object.fromEntries(data.entries());
        payload.variants = window.collectVariantRows?.('catalog-quick-variants') || [];
        if (payload.cost_price === '') delete payload.cost_price;
        if (payload.stock_qty === '') payload.stock_qty = 0;
        try {
            const response = await fetch('{{ route('api.pos.quick-add') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) {
                const validation = result.errors ? Object.values(result.errors).flat()[0] : null;
                throw new Error(validation || result.message || 'Could not create the product.');
            }
            window.location.href = form.dataset.editTemplate.replace('__ID__', result.product.id);
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.classList.remove('hidden');
            button.disabled = false;
        }
    });
})();
</script>
@endpush

@endsection
