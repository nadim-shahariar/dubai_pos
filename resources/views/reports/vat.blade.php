@extends('layouts.app')

@section('title', 'VAT report')
@section('subtitle', $from->format('d M Y').' – '.$to->format('d M Y'))

@section('content')
<div class="page-head">
    <div><h1>VAT summary</h1><p>Output VAT from completed invoices, less VAT reversed on credit notes. Review with the accountant before filing.</p></div>
    <div class="page-actions"><a class="btn btn-secondary" href="{{ route('reports.index') }}">All reports</a><button class="btn" onclick="window.print()">Print</button></div>
</div>

<form method="GET" class="card card-pad mb-2">
    <div class="inline-form">
        <div class="field"><label>From</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}"></div>
        <div class="field"><label>To</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}"></div>
        <button class="btn btn-secondary" type="submit">Run report</button>
    </div>
</form>

@php($salesVat = $salesByRate->sum('vat'))
@php($returnsVat = $returnsByRate->sum('vat'))
@php($outputVat = $salesVat - $returnsVat)

<div class="stat-grid">
    <div class="stat"><div class="stat-label">Taxable sales</div><div class="stat-value">AED {{ number_format($salesByRate->sum('net'), 2) }}</div><div class="stat-meta">Net of VAT</div></div>
    <div class="stat gold"><div class="stat-label">Output VAT</div><div class="stat-value">AED {{ number_format($salesVat, 2) }}</div><div class="stat-meta">On invoices</div></div>
    <div class="stat red"><div class="stat-label">VAT reversed</div><div class="stat-value">AED {{ number_format($returnsVat, 2) }}</div><div class="stat-meta">Credit notes</div></div>
    <div class="stat green"><div class="stat-label">Net output VAT</div><div class="stat-value">AED {{ number_format($outputVat, 2) }}</div><div class="stat-meta">Period position</div></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head"><h3>Sales by VAT rate</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Rate</th><th class="text-right">Taxable</th><th class="text-right">VAT</th><th class="text-right">Gross</th></tr></thead>
                <tbody>
                @forelse($salesByRate as $row)
                    <tr><td class="main-cell">{{ number_format((float) $row->tax_rate, 2) }}%</td><td class="text-right">AED {{ number_format((float) $row->net, 2) }}</td><td class="text-right">AED {{ number_format((float) $row->vat, 2) }}</td><td class="text-right">AED {{ number_format((float) $row->gross, 2) }}</td></tr>
                @empty
                    <tr><td colspan="4"><div class="empty">No taxable sales in this period.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h3>Credit notes</h3><span class="badge badge-gray">{{ $returns->count() }}</span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Credit note</th><th>Original invoice</th><th>Date</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                @forelse($returns as $return)
                    <tr><td class="main-cell mono">{{ $return->credit_note_number }}</td><td class="mono">{{ $return->sale?->invoice_number }}</td><td>{{ $return->returned_at->format('d M Y') }}</td><td class="text-right">AED {{ number_format((float) $return->total, 2) }}</td></tr>
                @empty
                    <tr><td colspan="4"><div class="empty">No credit notes in this period.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-info mt-2">
    <strong>Worksheet note:</strong> this is output VAT only. Input VAT from supplier invoices is not recorded in this MVP. Give this report and the purchase invoices to the accountant for the net VAT position. Records should be retained for at least five years.
</div>
@endsection
