@extends('layouts.app')

@section('title', 'Gross profit')
@section('subtitle', $from->format('d M Y').' – '.$to->format('d M Y'))

@section('content')
<div class="page-head">
    <div><h1>Gross profit by part</h1><p>Net sales minus the cost snapshot at the time of sale. Cost-pending Quick Add products can distort the ranking until completed.</p></div>
    <div class="page-actions"><a class="btn btn-secondary" href="{{ route('reports.index') }}">All reports</a><button class="btn" onclick="window.print()">Print</button></div>
</div>

<form method="GET" class="card card-pad mb-2">
    <div class="inline-form">
        <div class="field"><label>From</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}"></div>
        <div class="field"><label>To</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}"></div>
        <button class="btn btn-secondary" type="submit">Run report</button>
    </div>
</form>

@php($totalNet = $rows->sum('net'))
@php($totalCost = $rows->sum('cost'))
@php($totalProfit = $totalNet - $totalCost)

<div class="stat-grid">
    <div class="stat"><div class="stat-label">Net sales</div><div class="stat-value">AED {{ number_format($totalNet, 2) }}</div><div class="stat-meta">Excluding VAT</div></div>
    <div class="stat"><div class="stat-label">Cost of goods sold</div><div class="stat-value">AED {{ number_format($totalCost, 2) }}</div><div class="stat-meta">Cost snapshot</div></div>
    <div class="stat green"><div class="stat-label">Gross profit</div><div class="stat-value">AED {{ number_format($totalProfit, 2) }}</div><div class="stat-meta">Before overheads</div></div>
    <div class="stat gold"><div class="stat-label">Gross margin</div><div class="stat-value">{{ $totalNet > 0 ? number_format(($totalProfit / $totalNet) * 100, 1) : '0.0' }}%</div><div class="stat-meta">Blended</div></div>
</div>

<div class="card">
    <div class="card-head"><h3>Part profitability</h3><span class="small muted">{{ $rows->count() }} parts</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Part</th><th>Product</th><th class="text-right">Qty sold</th><th class="text-right">Net</th><th class="text-right">Cost</th><th class="text-right">Gross profit</th><th class="text-right">Margin</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="main-cell mono">{{ $row->primary_part_number }}</td>
                    <td>{{ $row->name }}</td>
                    <td class="text-right">{{ number_format((float) $row->qty, 0) }}</td>
                    <td class="text-right">AED {{ number_format((float) $row->net, 2) }}</td>
                    <td class="text-right">AED {{ number_format((float) $row->cost, 2) }}</td>
                    <td class="text-right main-cell {{ (float) $row->profit < 0 ? 'text-danger' : 'text-success' }}">AED {{ number_format((float) $row->profit, 2) }}</td>
                    <td class="text-right"><span class="badge {{ (float) $row->margin < 10 ? 'badge-red' : ((float) $row->margin < 20 ? 'badge-gold' : 'badge-green') }}">{{ number_format((float) $row->margin, 1) }}%</span></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty">No sales in this period.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
