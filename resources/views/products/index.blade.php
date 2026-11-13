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
                        @elseif($product->fitments->isNotEmpty())
                            @foreach($product->fitments as $variant)
                                <div class="small"><strong>{{ $variant->mapped_part_number ?: ($variant->brand?->name.' '.$variant->model?->name) }}</strong>: {{ $variant->stock_qty === null ? 'Not set' : (float) $variant->stock_qty }}</div>
                            @endforeach
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
