@extends('layouts.app')

@section('title', 'Inventory')
@section('subtitle', 'Stock on hand, valuation and movement ledger')

@section('content')
<div class="page-head">
    <div><h1>Inventory</h1><p>Every sale, receipt, return and adjustment is recorded as a stock movement. Adjustments should be the exception, not the routine.</p></div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="{{ route('inventory.movements') }}">Movement ledger</a>
        <a class="btn btn-secondary" href="{{ route('products.index', ['filter' => 'low']) }}">Low stock catalogue</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat"><div class="stat-label">Units on hand</div><div class="stat-value">{{ number_format($totalUnits, 0) }}</div><div class="stat-meta">Across stocked products</div></div>
    @if(auth()->user()->canViewCost())
        <div class="stat green"><div class="stat-label">Stock value at cost</div><div class="stat-value">AED {{ number_format($totalValue, 2) }}</div><div class="stat-meta">Weighted average cost</div></div>
    @endif
    <div class="stat {{ $lowCount ? 'gold' : '' }}"><div class="stat-label">Low stock items</div><div class="stat-value">{{ $lowCount }}</div><div class="stat-meta">At or below reorder level</div></div>
    <div class="stat"><div class="stat-label">Catalogue</div><div class="stat-value">{{ $products->total() }}</div><div class="stat-meta">Products in this view</div></div>
</div>

<div class="card">
    <div class="card-head">
        <form method="GET" class="inline-form" style="flex:1">
            <div class="field" style="max-width:420px"><label>Search stock</label><input name="q" value="{{ $q }}" placeholder="Part, name or bin…"></div>
            <button class="btn btn-secondary" type="submit">Search</button>
            <a class="btn btn-ghost" href="{{ route('inventory.index', ['filter' => 'low']) }}">Low stock only</a>
        </form>
        <span class="small muted">{{ $products->total() }} lines</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Part</th><th>Product</th><th>Bin</th><th>On hand</th><th>Reorder</th>@if(auth()->user()->canViewCost())<th>Avg cost</th><th>Value</th>@endif<th></th></tr></thead>
            <tbody>
            @forelse($products as $product)
                <tr>
                    <td class="main-cell mono">{{ $product->primary_part_number }}</td>
                    <td><div class="main-cell">{{ $product->name }}</div><div class="sub">{{ $product->category?->name }}</div></td>
                    <td>{{ $product->bin_location ?: '—' }}</td>
                    <td>
                        @if(!$product->track_stock)
                            <span class="badge badge-gray">Service</span>
                        @elseif((float) $product->stock_qty <= 0)
                            <span class="badge badge-red">Out</span>
                        @elseif($product->isLowStock())
                            <span class="badge badge-gold">{{ (float) $product->stock_qty }} low</span>
                        @else
                            <span class="badge badge-green">{{ (float) $product->stock_qty }}</span>
                        @endif
                    </td>
                    <td>{{ (float) $product->reorder_level }}</td>
                    @if(auth()->user()->canViewCost())
                        <td>AED {{ number_format((float) $product->cost_price, 2) }}</td>
                        <td>AED {{ number_format((float) $product->stock_qty * (float) $product->cost_price, 2) }}</td>
                    @endif
                    <td class="text-right"><a class="btn btn-secondary btn-sm" href="{{ route('inventory.show', $product) }}">Stock action</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty"><strong>No stock lines found.</strong></div></td></tr>
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
@endsection
