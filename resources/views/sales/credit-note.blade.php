@php($company = \App\Support\Pos::company())
@php($trn = \App\Support\Pos::trn())
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Credit Note {{ $return->credit_note_number }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="print-shell">
<div class="print-actions no-print">
    <a class="btn btn-secondary" href="{{ route('sales.show', $return->sale) }}">← Back to invoice</a>
    <button class="btn" onclick="window.print()">Print / Save PDF</button>
</div>
<div class="invoice-a4">
    <div class="inv-head">
        <div class="inv-company">
            <div class="flex items-center gap-2 mb-1"><span class="brand-mark" style="width:34px;height:34px;font-size:11px">GG</span><h1>{{ $company }}</h1></div>
            <div>{{ \App\Support\Pos::address() }}</div>
            <div>TRN: {{ $trn }} · Tel: {{ \App\Support\Pos::phone() }}</div>
        </div>
        <div class="inv-title">
            <h2>Credit Note</h2>
            <div class="ar">إشعار دائن</div>
            <div class="small" style="margin-top:6px">Original invoice {{ $return->sale->invoice_number }}</div>
        </div>
    </div>
    <div class="inv-meta">
        <div class="inv-meta-box"><h3>Credit note</h3><div><strong>No:</strong> {{ $return->credit_note_number }}</div><div><strong>Date:</strong> {{ $return->returned_at->format('d/m/Y H:i') }}</div><div><strong>Reason:</strong> {{ $return->reason }}</div><div><strong>Refund:</strong> {{ ucfirst($return->refund_method) }}</div></div>
        <div class="inv-meta-box"><h3>Customer</h3><div><strong>{{ $return->sale->customer_name }}</strong></div><div>TRN: {{ $return->sale->customer_trn ?: 'Not provided' }}</div><div>Original invoice: {{ $return->sale->invoice_number }} · {{ $return->sale->sold_at->format('d/m/Y') }}</div></div>
    </div>
    <table class="inv-table">
        <thead><tr><th>#</th><th>Part no.</th><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Net</th><th class="num">VAT</th><th class="num">Total credit</th></tr></thead>
        <tbody>
        @foreach($return->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td class="mono">{{ $item->saleItem?->primary_part_number }}</td>
                <td>{{ $item->saleItem?->name }}<div class="small">{{ ucfirst($item->condition) }} · {{ $item->restock ? 'Restocked' : 'Not restocked' }}</div></td>
                <td class="num">{{ (float) $item->qty }}</td>
                <td class="num">{{ number_format((float) $item->unit_price, 2) }}</td>
                <td class="num">{{ number_format((float) $item->net_amount, 2) }}</td>
                <td class="num">{{ number_format((float) $item->vat_amount, 2) }}</td>
                <td class="num">{{ number_format((float) $item->total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="inv-totals"><table>
        <tr><td class="label">Net credit</td><td class="val">AED {{ number_format((float) $return->subtotal, 2) }}</td></tr>
        <tr><td class="label">VAT reversed</td><td class="val">AED {{ number_format((float) $return->vat_total, 2) }}</td></tr>
        <tr class="grand"><td>Total credit</td><td class="val">AED {{ number_format((float) $return->total, 2) }}</td></tr>
    </table></div>
    <div class="inv-note">This credit note reduces the value of the original tax invoice. The original invoice remains on record. Refund method: {{ ucfirst($return->refund_method) }}.</div>
    <div class="inv-foot"><span>{{ $company }} · {{ \App\Support\Pos::address() }}</span><span>{{ $return->credit_note_number }} · Page 1 of 1</span></div>
</div>
</body>
</html>
