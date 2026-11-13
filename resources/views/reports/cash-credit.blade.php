@extends('layouts.app')

@section('title', 'Cash & Credit')
@section('subtitle', 'Cash sales, credit sales, monthly balances and collections')

@section('content')
<div class="page-head">
    <div><h1>Cash &amp; Credit Report</h1><p>See what was paid at the counter, what was sold on credit, what has been collected and what customers still owe.</p></div>
    <div class="page-actions"><button class="btn btn-secondary" type="button" onclick="window.print()">Print / PDF</button></div>
</div>

<div class="card">
    <div class="card-head">
        <form method="GET" class="inline-form" style="flex:1">
            <div class="field" style="max-width:160px"><label>From</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}"></div>
            <div class="field" style="max-width:160px"><label>To</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}"></div>
            <button class="btn btn-secondary" type="submit">Filter</button>
        </form>
        <span class="small muted">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</span>
    </div>
</div>

<div class="stat-grid">
    <div class="stat"><div class="stat-label">Sales in period</div><div class="stat-value">AED {{ number_format($totals['sales'], 2) }}</div><div class="stat-meta">{{ $totals['invoices'] }} invoices</div></div>
    <div class="stat green"><div class="stat-label">Paid at sale</div><div class="stat-value">AED {{ number_format($totals['cash_at_sale'], 2) }}</div><div class="stat-meta">Cash, card and bank</div></div>
    <div class="stat gold"><div class="stat-label">Credit sales</div><div class="stat-value">AED {{ number_format($totals['credit_sold'], 2) }}</div><div class="stat-meta">Invoiced on account</div></div>
    <div class="stat"><div class="stat-label">Collected in period</div><div class="stat-value">AED {{ number_format($totals['collected'], 2) }}</div><div class="stat-meta">Later credit collections</div></div>
</div>

<div class="grid-2 mt-3">
    <div class="card">
        <div class="card-head"><h3>Paid at sale</h3><span class="badge badge-green">AED {{ number_format($totals['cash_at_sale'], 2) }}</span></div>
        <div class="card-body">
            @forelse($totals['at_sale_by_method'] as $method => $amount)
                <div class="total-row"><span>{{ ucfirst(str_replace('_', ' ', $method)) }}</span><b>AED {{ number_format($amount, 2) }}</b></div>
            @empty
                <p class="small muted mb-0">No counter payments in this period.</p>
            @endforelse
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h3>Credit collected</h3><span class="badge badge-gold">AED {{ number_format($totals['collected'], 2) }}</span></div>
        <div class="card-body">
            @forelse($totals['collections_by_method'] as $method => $amount)
                <div class="total-row"><span>{{ ucfirst(str_replace('_', ' ', $method)) }}</span><b>AED {{ number_format($amount, 2) }}</b></div>
            @empty
                <p class="small muted mb-0">No credit collections in this period.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-head"><h3>Monthly cash / credit movement</h3><span class="small muted">Credit balance is the balance still open on invoices from that month</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Month</th><th class="text-right">Invoices</th><th class="text-right">Paid at sale</th><th class="text-right">Credit sold</th><th class="text-right">Collected</th><th class="text-right">Credit left now</th></tr></thead>
            <tbody>
            @foreach($months as $month)
                <tr>
                    <td class="main-cell">{{ $month['month'] }}</td>
                    <td class="text-right">{{ $month['invoices'] }}</td>
                    <td class="text-right">AED {{ number_format($month['cash'], 2) }}</td>
                    <td class="text-right text-gold">AED {{ number_format($month['credit'], 2) }}</td>
                    <td class="text-right text-success">AED {{ number_format($month['collected'], 2) }}</td>
                    <td class="text-right main-cell">AED {{ number_format($month['balance'], 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-head"><h3>Customer credit balances</h3><span class="badge badge-gold">Outstanding AED {{ number_format($totals['outstanding'], 2) }}</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Customer</th><th class="text-right">Credit sold</th><th class="text-right">Collected</th><th class="text-right">Balance left</th><th class="text-right">Current</th><th class="text-right">1–30</th><th class="text-right">31–60</th><th class="text-right">61–90</th><th class="text-right">90+</th><th>Oldest due</th><th></th></tr></thead>
            <tbody>
            @forelse($customerRows as $row)
                <tr>
                    <td><div class="main-cell">{{ $row['customer']->company_name ?: $row['customer']->name }}</div><div class="sub">{{ $row['customer']->phone ?: 'No phone' }} · limit AED {{ number_format($row['credit_limit'], 2) }}</div></td>
                    <td class="text-right">AED {{ number_format($row['credit_sold'], 2) }}</td>
                    <td class="text-right text-success">AED {{ number_format($row['collected'], 2) }}</td>
                    <td class="text-right main-cell {{ $row['outstanding'] > 0 ? 'text-danger' : 'text-success' }}">AED {{ number_format($row['outstanding'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['buckets']['current'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['buckets']['days_1_30'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['buckets']['days_31_60'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['buckets']['days_61_90'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['buckets']['days_90_plus'], 2) }}</td>
                    <td>{{ $row['oldest_due']?->format('d M Y') ?: '—' }}</td>
                    <td class="text-right"><a class="btn btn-secondary btn-sm" href="{{ route('customers.statement', $row['customer']) }}">Statement</a></td>
                </tr>
            @empty
                <tr><td colspan="11"><div class="empty"><strong>No credit customers.</strong>Credit sales will appear here.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-head"><h3>Credit collections in period</h3><span class="small muted">{{ $collections->count() }} receipts</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Receipt</th><th>Date</th><th>Customer</th><th>Invoice</th><th>Method</th><th>Received by</th><th class="text-right">Amount</th><th></th></tr></thead>
            <tbody>
            @forelse($collections as $collection)
                <tr>
                    <td class="mono main-cell">{{ $collection->receipt_number }}</td>
                    <td>{{ $collection->paid_at->format('d M Y H:i') }}</td>
                    <td>{{ $collection->customer?->company_name ?: $collection->customer?->name }}</td>
                    <td class="mono">{{ $collection->sale?->invoice_number }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $collection->method)) }}</td>
                    <td>{{ $collection->user?->name }}</td>
                    <td class="text-right main-cell text-success">AED {{ number_format((float) $collection->amount, 2) }}</td>
                    <td class="text-right"><a class="btn btn-secondary btn-sm" target="_blank" href="{{ route('credit-payments.receipt', $collection) }}">Receipt</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty"><strong>No credit collections in this period.</strong>Receive a payment from a customer statement.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
