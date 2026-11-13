@extends('layouts.app')

@section('title', 'Reports')
@section('subtitle', 'Sales, VAT, margin and stock')

@section('content')
<div class="page-head">
    <div><h1>Reports</h1><p>One source of truth for the owner, manager and accountant. Every report can be printed or saved as PDF from the browser.</p></div>
</div>

<div class="grid-2">
    <a class="card card-pad" href="{{ route('reports.sales') }}" style="display:block;color:inherit">
        <span class="badge badge-teal">Sales</span>
        <h2 class="mt-1">Sales &amp; daily summary</h2>
        <p class="muted mt-1">Invoices, discounts, VAT, returns and a day-by-day view of the counter.</p>
        <span class="small text-teal">Open report →</span>
    </a>
    <a class="card card-pad" href="{{ route('reports.profit') }}" style="display:block;color:inherit">
        <span class="badge badge-green">Margin</span>
        <h2 class="mt-1">Gross profit by part</h2>
        <p class="muted mt-1">Net sales minus cost of goods sold, ranked by profit and margin.</p>
        <span class="small text-teal">Open report →</span>
    </a>
    <a class="card card-pad" href="{{ route('reports.cash-credit') }}" style="display:block;color:inherit">
        <span class="badge badge-gold">Credit</span>
        <h2 class="mt-1">Cash &amp; credit sales</h2>
        <p class="muted mt-1">Cash/card vs credit sales, monthly movement, customer balances, aging and collections.</p>
        <span class="small text-teal">Open report →</span>
    </a>
    <a class="card card-pad" href="{{ route('reports.vat') }}" style="display:block;color:inherit">
        <span class="badge badge-gold">VAT</span>
        <h2 class="mt-1">VAT summary &amp; detail</h2>
        <p class="muted mt-1">Output VAT by rate, credit notes and the period worksheet for the accountant.</p>
        <span class="small text-teal">Open report →</span>
    </a>
    <a class="card card-pad" href="{{ route('reports.inventory') }}" style="display:block;color:inherit">
        <span class="badge badge-navy">Stock</span>
        <h2 class="mt-1">Inventory valuation</h2>
        <p class="muted mt-1">Units on hand, value at cost, retail value and low-stock exposure.</p>
        <span class="small text-teal">Open report →</span>
    </a>
</div>

<div class="card mt-3">
    <div class="card-head"><h3>What the owner should review</h3></div>
    <div class="card-body">
        <div class="grid-3">
            <div><strong>Daily</strong><p class="small muted mb-0">Sales, cash variance, discounts, overrides and stock-outs.</p></div>
            <div><strong>Weekly</strong><p class="small muted mb-0">Gross profit by category, dead stock, credit aging and supplier costs.</p></div>
            <div><strong>Monthly</strong><p class="small muted mb-0">VAT worksheet, stock value, margin trend and catalogue fitment coverage.</p></div>
        </div>
    </div>
</div>
@endsection
