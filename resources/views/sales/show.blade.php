@extends('layouts.app')

@section('title', 'Invoice '.$sale->invoice_number)
@section('subtitle', $sale->customer_name.' · '.$sale->sold_at->format('d M Y H:i'))

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $sale->invoice_number }}</h1>
        <p>{{ $sale->sold_at->format('l, d F Y · H:i') }} · {{ $sale->user?->name }} · {{ $sale->paymentLabel() }}</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="{{ route('sales.index') }}">Back to sales</a>
        <a class="btn btn-secondary" target="_blank" href="{{ route('sales.receipt', $sale) }}">80 mm receipt</a>
        <a class="btn" target="_blank" href="{{ route('sales.invoice', $sale) }}">A4 invoice</a>
        @if($sale->status !== 'voided' && $sale->status !== 'refunded' && auth()->user()->canApprove())
            <a class="btn btn-gold" href="{{ route('sales.return', $sale) }}">Return / credit note</a>
        @endif
    </div>
</div>

@if($sale->status !== 'completed')
    <div class="alert {{ $sale->status === 'refunded' ? 'alert-error' : 'alert-info' }}">This invoice is marked <strong>{{ $sale->statusLabel() }}</strong>. Credit notes are listed below.</div>
@endif

<div class="split">
    <div class="card">
        <div class="card-head"><h3>Line items</h3><span class="badge badge-gray">{{ $sale->items->count() }} lines</span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Part</th><th>Qty</th><th>Unit price</th><th>Discount</th><th>Net</th><th>VAT</th><th class="text-right">Total</th>@if(auth()->user()->canViewCost())<th class="text-right">Cost / GP</th>@endif</tr></thead>
                <tbody>
                @foreach($sale->items as $item)
                    <tr>
                        <td><div class="main-cell">{{ $item->name }}</div><div class="sub mono">{{ $item->primary_part_number }} @if($item->variant_label) · {{ $item->variant_label }} @endif @if($item->price_override) · <span class="text-danger">price override</span> @endif</div></td>
                        <td>{{ (float) $item->qty }}</td>
                        <td>AED {{ number_format((float) $item->unit_price, 2) }}</td>
                        <td>{{ (float) $item->discount_amount > 0 ? '- AED '.number_format((float) $item->discount_amount, 2) : '—' }}</td>
                        <td>AED {{ number_format((float) $item->net_amount, 2) }}</td>
                        <td>AED {{ number_format((float) $item->vat_amount, 2) }}</td>
                        <td class="text-right main-cell">AED {{ number_format((float) $item->line_total, 2) }}</td>
                        @if(auth()->user()->canViewCost())
                            @php($gp = (float) $item->net_amount - ((float) $item->cost_price * (float) $item->qty))
                            <td class="text-right">AED {{ number_format((float) $item->cost_price, 2) }}<div class="sub {{ $gp >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($gp, 2) }} GP</div></td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">
            <div style="max-width:320px;margin-left:auto">
                <div class="total-row"><span>Subtotal</span><b>AED {{ number_format((float) $sale->subtotal, 2) }}</b></div>
                <div class="total-row discount"><span>Discounts</span><b>- AED {{ number_format((float) $sale->discount_total, 2) }}</b></div>
                <div class="total-row"><span>Net</span><b>AED {{ number_format((float) $sale->net_total, 2) }}</b></div>
                <div class="total-row"><span>VAT</span><b>AED {{ number_format((float) $sale->vat_total, 2) }}</b></div>
                <div class="total-row grand"><span>Total</span><b>AED {{ number_format((float) $sale->total, 2) }}</b></div>
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-head"><h3>Customer</h3></div>
            <div class="card-body">
                <div class="main-cell">{{ $sale->customer_name }}</div>
                <div class="small muted">{{ $sale->customer_trn ? 'TRN '.$sale->customer_trn : 'No TRN recorded' }}</div>
                @if($sale->vehicle)<div class="small mt-1">Vehicle: {{ $sale->vehicle }}</div>@endif
                @if($sale->customer)<div class="small mt-1">{{ $sale->customer->phone }}<br>{{ $sale->customer->address }}</div>@endif
                @if($sale->notes)<div class="alert alert-info mt-2">{{ $sale->notes }}</div>@endif
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Payments</h3><span class="badge {{ $sale->payment_status === 'paid' ? 'badge-green' : 'badge-gold' }}">{{ str_replace('_',' ', $sale->payment_status) }}</span></div>
            <div class="card-body">
                @foreach($sale->payments as $payment)
                    <div class="total-row"><span>{{ ucfirst(str_replace('_',' ', $payment->method)) }} @if($payment->reference)<span class="small muted">· {{ $payment->reference }}</span>@endif</span><b>AED {{ number_format((float) $payment->amount, 2) }}</b></div>
                @endforeach
                <div class="total-row"><span>Change</span><b>AED {{ number_format((float) $sale->change_total, 2) }}</b></div>
                <div class="total-row"><span>Balance due</span><b>AED {{ number_format((float) $sale->balance_due, 2) }}</b></div>
            </div>
        </div>

        @if($sale->returns->count())
            <div class="card">
                <div class="card-head"><h3>Credit notes</h3></div>
                <div class="card-body">
                    @foreach($sale->returns as $return)
                        <div class="flex justify-between items-center gap-2" style="padding:6px 0;border-bottom:1px solid var(--line-soft)">
                            <div><div class="main-cell mono">{{ $return->credit_note_number }}</div><div class="sub">{{ $return->returned_at->format('d M Y H:i') }} · {{ $return->reason }}</div></div>
                            <div class="text-right"><div class="main-cell">AED {{ number_format((float) $return->total, 2) }}</div><a class="small" target="_blank" href="{{ route('returns.credit-note', $return) }}">Print →</a></div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
