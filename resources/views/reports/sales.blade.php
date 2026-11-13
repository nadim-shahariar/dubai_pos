@extends('layouts.app')

@section('title', 'Sales report')
@section('subtitle', $from->format('d M Y').' – '.$to->format('d M Y'))

@section('content')
<div class="page-head">
    <div><h1>Sales report</h1><p>Completed invoices in the selected period. Returns are shown separately and are not netted into gross sales.</p></div>
    <div class="page-actions"><a class="btn btn-secondary" href="{{ route('reports.index') }}">All reports</a><button class="btn" onclick="window.print()">Print</button></div>
</div>

<form method="GET" class="card card-pad mb-2">
    <div class="inline-form">
        <div class="field"><label>From</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}"></div>
        <div class="field"><label>To</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}"></div>
        <button class="btn btn-secondary" type="submit">Run report</button>
    </div>
</form>

<div class="stat-grid">
    <div class="stat"><div class="stat-label">Invoices</div><div class="stat-value">{{ $totals['count'] }}</div><div class="stat-meta">{{ $from->format('d M') }} – {{ $to->format('d M') }}</div></div>
    <div class="stat green"><div class="stat-label">Gross sales</div><div class="stat-value">AED {{ number_format($totals['total'], 2) }}</div><div class="stat-meta">Including VAT</div></div>
    <div class="stat gold"><div class="stat-label">Discounts</div><div class="stat-value">AED {{ number_format($totals['discount'], 2) }}</div><div class="stat-meta">Line + invoice</div></div>
    <div class="stat red"><div class="stat-label">Returns</div><div class="stat-value">AED {{ number_format($totals['returns'], 2) }}</div><div class="stat-meta">{{ $returns->count() }} credit notes</div></div>
</div>

<div class="card mb-2">
    <div class="card-head"><h3>Daily summary</h3><span class="badge badge-teal">AED</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Date</th><th>Invoices</th><th class="text-right">Gross</th><th class="text-right">Discounts</th><th class="text-right">Net</th><th class="text-right">VAT</th></tr></thead>
            <tbody>
            @forelse($daily as $day)
                <tr><td class="main-cell">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d M Y') }}</td><td>{{ $day['count'] }}</td><td class="text-right">AED {{ number_format($day['total'], 2) }}</td><td class="text-right">- AED {{ number_format($day['discount'], 2) }}</td><td class="text-right">AED {{ number_format($day['net'], 2) }}</td><td class="text-right">AED {{ number_format($day['vat'], 2) }}</td></tr>
            @empty
                <tr><td colspan="6"><div class="empty">No sales in this period.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-head"><h3>Invoice register</h3><span class="small muted">{{ $sales->count() }} invoices</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Cashier</th><th>Status</th><th class="text-right">Total</th><th class="text-right">VAT</th></tr></thead>
            <tbody>
            @foreach($sales->sortByDesc('sold_at') as $sale)
                <tr><td class="main-cell mono">{{ $sale->invoice_number }}</td><td>{{ $sale->sold_at->format('d M Y H:i') }}</td><td>{{ $sale->customer_name }}</td><td>{{ $sale->user?->name }}</td><td>{{ $sale->statusLabel() }}</td><td class="text-right">AED {{ number_format((float) $sale->total, 2) }}</td><td class="text-right">AED {{ number_format((float) $sale->vat_total, 2) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
