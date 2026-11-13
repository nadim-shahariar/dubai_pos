@extends('layouts.app')

@section('title', 'Stock · '.$product->primary_part_number)
@section('subtitle', $product->name)

@section('content')
<div class="page-head">
    <div><h1>{{ $product->name }}</h1><p><span class="mono">{{ $product->primary_part_number }}</span> · {{ $product->brand ?: 'No brand' }} · {{ $product->bin_location ?: 'No bin' }}</p></div>
    <div class="page-actions"><a class="btn btn-secondary" href="{{ route('inventory.index') }}">Back to inventory</a><a class="btn btn-secondary" href="{{ route('products.edit', $product) }}">Edit product</a></div>
</div>

<div class="stat-grid">
    <div class="stat"><div class="stat-label">On hand</div><div class="stat-value">{{ (float) $product->stock_qty }}</div><div class="stat-meta">Reorder at {{ (float) $product->reorder_level }}</div></div>
    @if(auth()->user()->canViewCost())
        <div class="stat green"><div class="stat-label">Average cost</div><div class="stat-value">AED {{ number_format((float) $product->cost_price, 2) }}</div><div class="stat-meta">Weighted average</div></div>
        <div class="stat gold"><div class="stat-label">Stock value</div><div class="stat-value">AED {{ number_format((float) $product->stock_qty * (float) $product->cost_price, 2) }}</div><div class="stat-meta">At average cost</div></div>
    @endif
    <div class="stat"><div class="stat-label">Selling price</div><div class="stat-value">AED {{ number_format((float) $product->selling_price, 2) }}</div><div class="stat-meta">Secret code <span class="code-chip">{{ $product->secret_cost_code }}</span></div></div>
</div>


@if($product->fitments->count())
    <div class="card mt-3">
        <div class="card-head"><h3>Variant stock</h3><span class="small muted">Each variant can hold its own stock</span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Vehicle variant</th><th>Part number</th><th class="text-right">Variant stock</th><th style="width:260px">Set counted stock</th></tr></thead>
                <tbody>
                @foreach($product->fitments as $fitment)
                    <tr>
                        <td><div class="main-cell">{{ $fitment->brand?->name }} {{ $fitment->model?->name }}</div><div class="sub">{{ $fitment->year_from }}–{{ $fitment->year_to }}</div></td>
                        <td class="mono">{{ $fitment->mapped_part_number }}</td>
                        <td class="text-right main-cell">{{ $fitment->stock_qty === null ? 'Inherit product' : number_format((float) $fitment->stock_qty, 3) }}</td>
                        <td>
                            <form method="POST" action="{{ route('inventory.variant-stock', [$product, $fitment]) }}" class="inline-form">
                                @csrf
                                @method('PATCH')
                                <input name="stock_qty" type="number" step="0.001" min="0" value="{{ $fitment->stock_qty }}" placeholder="Set stock" required style="max-width:120px">
                                <button class="btn btn-secondary btn-sm" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body"><div class="small muted">Setting variant stock posts an adjustment movement and changes the product total by the difference. A variant marked <strong>Inherit product</strong> uses the product-level stock until you set its own quantity.</div></div>
    </div>
@endif

<div class="grid-2">
    <div class="card">
        <div class="card-head"><h3>Receive stock</h3><span class="badge badge-teal">Purchase / goods in</span></div>
        <div class="card-body">
            <form method="POST" action="{{ route('inventory.receive', $product) }}">
                @csrf
                <div class="form-grid">
                    <div class="field"><label>Quantity received *</label><input name="qty" type="number" step="1" min="0.001" required></div>
                    <div class="field"><label>Unit cost (AED) *</label><input name="unit_cost" type="number" step="0.01" min="0" value="{{ auth()->user()->canViewCost() ? (float) $product->cost_price : '' }}" required></div>
                    <div class="field span-2"><label>Reference</label><input name="reference" maxlength="255" placeholder="Supplier invoice / GRN reference"></div>
                </div>
                <div class="form-actions"><button class="btn" type="submit">Receive &amp; update average cost</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>Adjust stock</h3><span class="badge badge-gold">Exception</span></div>
        <div class="card-body">
            <form method="POST" action="{{ route('inventory.adjust', $product) }}">
                @csrf
                <div class="form-grid">
                    <div class="field"><label>New counted quantity *</label><input name="new_qty" type="number" step="1" min="0" value="{{ (float) $product->stock_qty }}" required></div>
                    <div class="field"><label>Reason</label><input name="note" maxlength="255" placeholder="Damage, stocktake, data correction…"></div>
                </div>
                <div class="alert alert-info mt-2">The difference is posted as an adjustment movement and audited. It does not change the selling price.</div>
                <div class="form-actions"><button class="btn btn-secondary" type="submit">Post adjustment</button></div>
            </form>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-head"><h3>Movement history</h3><a class="small" href="{{ route('inventory.movements') }}">Full ledger →</a></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>When</th><th>Type</th><th>In</th><th>Out</th><th>Balance</th>@if(auth()->user()->canViewCost())<th>Unit cost</th>@endif<th>Reference</th><th>User</th></tr></thead>
            <tbody>
            @forelse($movements as $movement)
                <tr>
                    <td class="nowrap">{{ $movement->created_at?->format('d M Y H:i') }}</td>
                    <td><span class="badge badge-gray">{{ str_replace('_', ' ', $movement->type) }}</span></td>
                    <td>{{ (float) $movement->qty_in ?: '—' }}</td>
                    <td>{{ (float) $movement->qty_out ?: '—' }}</td>
                    <td class="main-cell">{{ (float) $movement->balance_after }}</td>
                    @if(auth()->user()->canViewCost())<td>AED {{ number_format((float) $movement->unit_cost, 2) }}</td>@endif
                    <td class="small">{{ $movement->note ?: $movement->reference_type }}</td>
                    <td class="small">{{ $movement->user?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty">No stock movements yet.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($movements->hasPages())
    <div class="pagination">
        @if($movements->previousPageUrl())<a href="{{ $movements->previousPageUrl() }}">← Previous</a>@endif
        <span class="current">{{ $movements->currentPage() }}</span>
        @if($movements->nextPageUrl())<a href="{{ $movements->nextPageUrl() }}">Next →</a>@endif
    </div>
@endif
@endsection
