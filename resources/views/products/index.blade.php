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
        <a class="btn btn-secondary" data-low-stock href="{{ route('products.index', ['filter' => 'low']) }}">Low stock</a>
        <button class="btn" type="button" id="quick-add-product">+ Quick add <span class="shortcut-key">F2</span></button>
        <a class="btn btn-secondary" href="{{ route('products.create') }}" data-create-product>Add products</a>
    </div>
</div>

<p class="catalogue-shortcuts"><kbd>F2</kbd> Quick add · <kbd>F3</kbd> Search · <kbd>Alt+N</kbd> Add products · <kbd>Alt+L</kbd> Low stock · <kbd>Esc</kbd> Close Quick Add</p>
<div class="card">
    <div class="card-head">
        <form method="GET" class="inline-form" style="flex:1">
            <div class="field" style="max-width:420px"><label>Search catalogue</label><input id="catalogue-search" name="q" value="{{ $q }}" placeholder="Part number, name, barcode, cross-reference…"></div>
            <button class="btn btn-secondary" type="submit">Search</button>
            @if($q)<a class="btn btn-ghost" href="{{ route('products.index') }}">Clear</a>@endif
        </form>
        <span class="small muted">{{ $products->total() }} products</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Part</th><th>Product</th><th>Vehicle variant</th><th>Category</th><th>Stock</th><th>Price</th><th>Code</th>@if(auth()->user()->canViewCost())<th>Buying price</th>@endif<th>Quick actions</th></tr>
            </thead>
            <tbody>
            @forelse($products as $product)
                @php($variantRows = $product->fitments->isEmpty() ? collect([null]) : $product->fitments->filter(fn ($variant) => request('filter') !== 'low' || $variant->stock_qty === null || (float) $variant->stock_qty <= (float) $product->reorder_level))
                @foreach($variantRows as $variant)
                @php($quantity = $variant ? $variant->stock_qty : $product->stock_qty)
                <tr data-catalogue-product="{{ $product->id }}" data-catalogue-variant="{{ $variant?->id }}">
                    <td class="main-cell mono">{{ ($variant?->mapped_part_number ?: $product->primary_part_number) ?: '—' }}</td>
                    <td><div class="main-cell">{{ $product->name }}</div><div class="sub">{{ $product->brand ?: 'No manufacturer' }}</div></td>
                    <td>@if($variant){{ $variant->brand?->name }} {{ $variant->model?->name }}<div class="sub">{{ $variant->year_from }}–{{ $variant->year_to }}</div>@else<span class="muted">Single product</span>@endif</td>
                    <td>{{ $variant?->category?->name ?: ($product->category?->name ?? '—') }}</td>
                    <td>@if(!$product->track_stock)<span class="badge badge-gray">Service</span>@elseif($quantity === null)<span class="badge badge-gold">Not set</span>@else<span class="badge {{ (float) $quantity <= 0 ? 'badge-red' : ((float) $quantity <= (float) $product->reorder_level ? 'badge-gold' : 'badge-green') }}">{{ (float) $quantity }}</span>@endif</td>
                    <td>AED {{ number_format((float) $product->selling_price, 2) }}</td>
                    <td class="mono">{{ $product->secret_cost_code }}</td>
                    @if(auth()->user()->canViewCost())<td>AED {{ number_format((float) ($variant?->cost_price ?? $product->cost_price), 2) }}</td>@endif
                    <td class="text-right nowrap"><a class="btn btn-secondary btn-sm" href="{{ route('products.edit', $product) }}{{ $variant ? '#fitment-'.$variant->id : '' }}">Edit</a>@if(auth()->user()->canManageInventory()) <a class="btn btn-ghost btn-sm" href="{{ route('inventory.show', $product) }}{{ $variant ? '#variant-'.$variant->id : '' }}">Stock</a>@endif</td>
                </tr>
                @endforeach
            @empty
                <tr><td colspan="10"><div class="empty">No products or variants found.</div></td></tr>
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

{{-- Quick add uses the same batch table as the Add products page. --}}
<div class="modal-backdrop" id="quick-product-modal">
    <div class="modal product-sheet-modal">
        <div class="modal-head"><div><h2>Quick add product</h2><p>Enter products and variants in rows, like a spreadsheet.</p></div><button class="modal-close" type="button" data-close-quick-product aria-label="Close quick add">×</button></div>
        <div class="modal-body">@include('products.partials.entry-sheet')</div>
    </div>
</div>
@push('scripts')
<script>
(() => {
    const modal = document.getElementById('quick-product-modal');
    const close = () => {
        if (modal.querySelector('[data-product-sheet]').dataset.saved === 'true') window.location.reload();
        else modal.classList.remove('open');
    };
    document.getElementById('quick-add-product').addEventListener('click', () => {
        modal.classList.add('open');
        modal.querySelector('[data-field="name"]')?.focus();
    });
    modal.querySelector('[data-close-quick-product]').addEventListener('click', close);
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && modal.classList.contains('open')) close(); });
})();
</script>
@endpush
@endsection
