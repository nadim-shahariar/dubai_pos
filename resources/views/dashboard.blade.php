@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Live view of the counter, stock and margin')

@section('content')
<div class="page-head">
    <div>
        <h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', auth()->user()->name)[0] }}.</h1>
        <p>Here is the shop floor at a glance. Open the POS when you are ready to serve the next customer.</p>
    </div>
    <div class="page-actions">
        @if(auth()->user()->canSell())
            <a class="btn btn-secondary" href="{{ route('sales.index') }}">View invoices</a>
            <a class="btn btn-dark" href="{{ route('pos.index') }}">Open POS</a>
        @endif
    </div>
</div>

<div class="stat-grid">
    <div class="stat">
        <div class="stat-label">Sales today</div>
        <div class="stat-value">AED {{ number_format($todaySales, 2) }}</div>
        <div class="stat-meta">{{ $todayCount }} invoice{{ $todayCount === 1 ? '' : 's' }} · incl. 5% VAT</div>
    </div>
    <div class="stat green">
        <div class="stat-label">Gross profit today</div>
        <div class="stat-value">AED {{ number_format($grossProfit, 2) }}</div>
        <div class="stat-meta">Net sales minus cost of goods sold</div>
    </div>
    <div class="stat gold">
        <div class="stat-label">VAT collected today</div>
        <div class="stat-value">AED {{ number_format($todayVat, 2) }}</div>
        <div class="stat-meta">Output VAT on completed invoices</div>
    </div>
    <div class="stat {{ $lowStock ? 'red' : '' }}">
        <div class="stat-label">Low stock items</div>
        <div class="stat-value">{{ $lowStock }}</div>
        <div class="stat-meta">{{ $openShifts->count() }} open shift{{ $openShifts->count() === 1 ? '' : 's' }}</div>
    </div>
</div>

<div class="split">
    <div class="stack">
        <div class="card">
            <div class="card-head">
                <h3>Sales — last 7 days</h3>
                <span class="badge badge-teal">Dubai time</span>
            </div>
            <div class="card-body">
                <div class="trend">
                    @foreach($trend as $day)
                        <div class="trend-bar">
                            <div class="trend-value">{{ $day['total'] > 0 ? number_format($day['total'], 0) : '' }}</div>
                            <div class="trend-fill" style="height: {{ max(3, round(($day['total'] / $maxTrend) * 105)) }}px"></div>
                            <div class="trend-label">{{ $day['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3>Latest invoices</h3>
                <a class="small" href="{{ route('sales.index') }}">All sales →</a>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Invoice</th><th>Customer</th><th>Cashier</th><th>Payment</th><th class="text-right">Total</th><th></th></tr></thead>
                    <tbody>
                    @forelse($recent as $sale)
                        <tr>
                            <td class="main-cell">{{ $sale->invoice_number }}<div class="sub">{{ $sale->sold_at->format('d M, H:i') }}</div></td>
                            <td>{{ $sale->customer_name }}</td>
                            <td>{{ $sale->user?->name }}</td>
                            <td><span class="badge badge-gray">{{ $sale->paymentLabel() }}</span></td>
                            <td class="text-right main-cell">AED {{ number_format((float) $sale->total, 2) }}</td>
                            <td class="text-right"><a class="btn btn-secondary btn-sm" href="{{ route('sales.show', $sale) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty"><strong>No sales yet</strong>Open the POS and complete the first sale.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-head"><h3>Top products this month</h3></div>
            <div class="card-body" style="padding-top:10px">
                @forelse($topProducts as $product)
                    <div class="flex items-center justify-between gap-2" style="padding:9px 0;border-bottom:1px solid var(--line-soft)">
                        <div>
                            <div class="main-cell" style="font-size:12px">{{ $product->name }}</div>
                            <div class="small muted mono">{{ $product->primary_part_number }} · {{ number_format((float) $product->qty, 0) }} sold</div>
                        </div>
                        <div class="text-right">
                            <div style="font-weight:700;font-size:12px">AED {{ number_format((float) $product->revenue, 2) }}</div>
                            <div class="small text-success">Margin {{ (float) $product->net > 0 ? number_format((((float) $product->net - (float) $product->cost) / (float) $product->net) * 100, 1) : '0.0' }}%</div>
                        </div>
                    </div>
                @empty
                    <div class="empty">No product sales yet.</div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Open shifts</h3><a class="small" href="{{ route('pos.index') }}">POS →</a></div>
            <div class="card-body" style="padding-top:10px">
                @forelse($openShifts as $shift)
                    <div class="flex items-center justify-between gap-2" style="padding:8px 0;border-bottom:1px solid var(--line-soft)">
                        <div><div class="main-cell">{{ $shift->user?->name }}</div><div class="small muted">Opened {{ $shift->opened_at->diffForHumans() }}</div></div>
                        <div class="text-right"><div class="small muted">Float</div><div style="font-weight:700">AED {{ number_format((float) $shift->opening_float, 2) }}</div></div>
                    </div>
                @empty
                    <div class="empty" style="padding:18px 0">No open shift. A cashier must open one before selling.</div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Quick links</h3></div>
            <div class="card-body">
                <div class="quick-links">
                    @if(auth()->user()->canSell())<a class="quick-link" href="{{ route('pos.index') }}">Start a sale</a>@endif
                    @if(auth()->user()->canManageCatalog())<a class="quick-link" href="{{ route('products.create') }}">Add product</a>@endif
                    @if(auth()->user()->canViewReports())<a class="quick-link" href="{{ route('reports.vat') }}">VAT report</a><a class="quick-link" href="{{ route('reports.inventory') }}">Stock value</a>@endif
                    @if(auth()->user()->canManageCatalog())<a class="quick-link" href="{{ route('inventory.index') }}">Stock adjustment</a>@endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
