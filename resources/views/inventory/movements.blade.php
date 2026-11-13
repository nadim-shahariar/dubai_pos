@extends('layouts.app')

@section('title', 'Stock movement ledger')
@section('subtitle', 'Every quantity change in the shop')

@section('content')
<div class="page-head">
    <div><h1>Movement ledger</h1><p>Append-only record of sales, receipts, returns, adjustments and opening stock.</p></div>
    <div class="page-actions"><a class="btn btn-secondary" href="{{ route('inventory.index') }}">Back to inventory</a></div>
</div>

<div class="card">
    <div class="card-head"><h3>Recent movements</h3><span class="small muted">{{ $movements->total() }} entries</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>When</th><th>Part</th><th>Type</th><th>In</th><th>Out</th><th>Balance</th>@if(auth()->user()->canViewCost())<th>Cost</th>@endif<th>Reference</th><th>User</th></tr></thead>
            <tbody>
            @forelse($movements as $movement)
                <tr>
                    <td class="nowrap">{{ $movement->created_at?->format('d M Y H:i') }}</td>
                    <td><div class="main-cell mono">{{ $movement->product?->primary_part_number }}</div><div class="sub">{{ $movement->product?->name }}</div></td>
                    <td><span class="badge badge-gray">{{ str_replace('_', ' ', $movement->type) }}</span></td>
                    <td>{{ (float) $movement->qty_in ?: '—' }}</td>
                    <td>{{ (float) $movement->qty_out ?: '—' }}</td>
                    <td class="main-cell">{{ (float) $movement->balance_after }}</td>
                    @if(auth()->user()->canViewCost())<td>AED {{ number_format((float) $movement->unit_cost, 2) }}</td>@endif
                    <td class="small">{{ $movement->note ?: $movement->reference_type }}</td>
                    <td class="small">{{ $movement->user?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="empty">No movements yet.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@if($movements->hasPages())
    <div class="pagination">
        <span class="info">Page {{ $movements->currentPage() }} of {{ $movements->lastPage() }}</span>
        @if($movements->previousPageUrl())<a href="{{ $movements->previousPageUrl() }}">← Previous</a>@endif
        @if($movements->nextPageUrl())<a href="{{ $movements->nextPageUrl() }}">Next →</a>@endif
    </div>
@endif
@endsection
