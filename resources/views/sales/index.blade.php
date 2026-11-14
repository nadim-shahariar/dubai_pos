@extends('layouts.app')

@section('title', 'Sales')
@section('subtitle', 'Invoices, payments and credit notes')

@section('content')
<div class="page-head">
    <div><h1>Sales</h1><p>Every completed counter transaction. Cashiers see their own invoices; managers and accountants see all.</p></div>
    <div class="page-actions">@if(auth()->user()->canSell())<a class="btn" href="{{ route('pos.index') }}">Open POS</a>@endif</div>
</div>

<div class="stat-grid">
    <div class="stat"><div class="stat-label">Invoices in range</div><div class="stat-value">{{ $totals['count'] }}</div><div class="stat-meta">{{ $from->format('d M') }} – {{ $to->format('d M Y') }}</div></div>
    <div class="stat green"><div class="stat-label">Gross sales</div><div class="stat-value">AED {{ number_format($totals['total'], 2) }}</div><div class="stat-meta">Including VAT</div></div>
    <div class="stat gold"><div class="stat-label">VAT</div><div class="stat-value">AED {{ number_format($totals['vat'], 2) }}</div><div class="stat-meta">Output tax in range</div></div>
    <div class="stat"><div class="stat-label">Net sales</div><div class="stat-value">AED {{ number_format($totals['net'], 2) }}</div><div class="stat-meta">Excluding VAT</div></div>
</div>

<p class="catalogue-shortcuts no-print">Click a row to select it · <kbd>F3</kbd> Search · <kbd>↑/↓</kbd> Select invoice · <kbd>Enter</kbd> Open · <kbd>Alt+P</kbd> Print invoice · <kbd>Alt+R</kbd> Receipt</p>
<div class="card">
    <div class="card-head">
        <form method="GET" class="inline-form" style="flex:1">
            <div class="field" style="max-width:150px"><label>From</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}"></div>
            <div class="field" style="max-width:150px"><label>To</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}"></div>
            <div class="field" style="max-width:250px"><label>Search</label><input id="sales-search" name="q" value="{{ $q }}" placeholder="Invoice, customer, TRN"></div>
            <div class="field" style="max-width:150px"><label>Status</label><select name="status"><option value="">All</option><option value="completed" @selected(request('status')==='completed')>Completed</option><option value="partially_refunded" @selected(request('status')==='partially_refunded')>Part refunded</option><option value="refunded" @selected(request('status')==='refunded')>Refunded</option></select></div>
            <button class="btn btn-secondary" type="submit">Filter</button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Cashier</th><th>Payment</th><th>Status</th><th class="text-right">Total</th><th></th></tr></thead>
            <tbody>
            @forelse($sales as $sale)
                <tr data-sale-row>
                    <td class="main-cell mono"><a data-sale-open href="{{ route('sales.show', $sale) }}">{{ $sale->invoice_number }}</a></td>
                    <td class="nowrap">{{ $sale->sold_at->format('d M Y') }}<div class="sub">{{ $sale->sold_at->format('H:i') }}</div></td>
                    <td><div class="main-cell">{{ $sale->customer_name }}</div><div class="sub">{{ $sale->customer_trn ?: 'Walk-in / no TRN' }}</div></td>
                    <td>{{ $sale->user?->name }}</td>
                    <td><span class="badge badge-gray">{{ $sale->paymentLabel() }}</span></td>
                    <td><span class="badge {{ $sale->status === 'completed' ? 'badge-green' : ($sale->status === 'refunded' ? 'badge-red' : 'badge-gold') }}">{{ $sale->statusLabel() }}</span></td>
                    <td class="text-right main-cell">AED {{ number_format((float) $sale->total, 2) }}</td>
                    <td class="text-right nowrap no-print"><a class="btn btn-secondary btn-sm" href="{{ route('sales.show', $sale) }}">Open</a> <a data-invoice-print class="btn btn-sm" target="_blank" rel="noopener" href="{{ route('sales.invoice', [$sale, 'print' => 1]) }}">Print invoice</a> <a data-receipt-print class="btn btn-secondary btn-sm" target="_blank" rel="noopener" href="{{ route('sales.receipt', [$sale, 'print' => 1]) }}">Receipt</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty"><strong>No invoices in this range.</strong>Try a different date range.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($sales->hasPages())
    <div class="pagination">
        <span class="info">Page {{ $sales->currentPage() }} of {{ $sales->lastPage() }}</span>
        @if($sales->previousPageUrl())<a href="{{ $sales->previousPageUrl() }}">← Previous</a>@else<span class="disabled">← Previous</span>@endif
        @if($sales->nextPageUrl())<a href="{{ $sales->nextPageUrl() }}">Next →</a>@else<span class="disabled">Next →</span>@endif
    </div>
@endif
@endsection
