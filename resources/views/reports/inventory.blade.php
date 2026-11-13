@extends('layouts.app')

@section('title', 'Inventory valuation')
@section('subtitle', 'Stock on hand and low-stock exposure')

@section('content')
<div class="page-head">
    <div><h1>Inventory valuation</h1><p>Current stock at weighted-average cost. Low-stock lines are highlighted for reordering.</p></div>
    <div class="page-actions"><a class="btn btn-secondary" href="{{ route('reports.index') }}">All reports</a><button class="btn" onclick="window.print()">Print</button></div>
</div>

<div class="stat-grid">
    <div class="stat"><div class="stat-label">Stock lines</div><div class="stat-value">{{ $products->count() }}</div><div class="stat-meta">Active and inactive products</div></div>
    @if(auth()->user()->canViewCost())
        <div class="stat green"><div class="stat-label">Value at cost</div><div class="stat-value">AED {{ number_format($value, 2) }}</div><div class="stat-meta">Weighted average</div></div>
        <div class="stat gold"><div class="stat-label">Value at retail</div><div class="stat-value">AED {{ number_format($retail, 2) }}</div><div class="stat-meta">Selling price × on hand</div></div>
    @endif
    <div class="stat red"><div class="stat-label">Low stock</div><div class="stat-value">{{ $low->count() }}</div><div class="stat-meta">At or below reorder level</div></div>
</div>

<div class="card">
    <div class="card-head"><h3>Stock position</h3><span class="small muted">{{ $products->count() }} products</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Part</th><th>Product</th><th>Bin</th><th class="text-right">On hand</th><th class="text-right">Reorder</th>@if(auth()->user()->canViewCost())<th class="text-right">Avg cost</th><th class="text-right">Value</th>@endif<th>Status</th></tr></thead>
            <tbody>
            @foreach($products as $product)
                <tr>
                    <td class="main-cell mono">{{ $product->primary_part_number }}</td>
                    <td>{{ $product->name }}<div class="sub">{{ $product->category?->name }}</div></td>
                    <td>{{ $product->bin_location ?: '—' }}</td>
                    <td class="text-right">{{ (float) $product->stock_qty }}</td>
                    <td class="text-right">{{ (float) $product->reorder_level }}</td>
                    @if(auth()->user()->canViewCost())
                        <td class="text-right">AED {{ number_format((float) $product->cost_price, 2) }}</td>
                        <td class="text-right">AED {{ number_format((float) $product->stock_qty * (float) $product->cost_price, 2) }}</td>
                    @endif
                    <td>
                        @if(!$product->track_stock)<span class="badge badge-gray">Service</span>
                        @elseif((float) $product->stock_qty <= 0)<span class="badge badge-red">Out</span>
                        @elseif($product->isLowStock())<span class="badge badge-gold">Reorder</span>
                        @else<span class="badge badge-green">OK</span>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-info mt-2">Stock value is based on the moving weighted-average cost at the last receipt. A physical stocktake should be performed before closing the books.</div>
@endsection
