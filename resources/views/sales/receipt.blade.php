@php($company = \App\Support\Pos::company())
@php($trn = \App\Support\Pos::trn())
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $sale->invoice_number }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="print-shell">
<div class="print-actions no-print">
    <a class="btn btn-secondary" href="{{ route('sales.show', $sale) }}">← Back</a>
    <button class="btn" onclick="window.print()">Print receipt</button>
</div>
<div class="receipt-80">
    @if($sale->status === 'voided')
        <div class="r-center" style="border:2px solid #b42318;padding:4px;margin-bottom:6px"><strong>VOIDED</strong></div>
    @endif
    <div class="r-center">
        <h1>{{ $company }}</h1>
        <div class="r-small">{{ \App\Support\Pos::address() }}</div>
        <div class="r-small">TRN: {{ $trn }} · {{ \App\Support\Pos::phone() }}</div>
        <hr>
        <strong>TAX INVOICE / فاتورة ضريبية</strong>
    </div>
    <div>Invoice: {{ $sale->invoice_number }}</div>
    <div>Date: {{ $sale->sold_at->format('d/m/Y H:i') }}</div>
    <div>Cashier: {{ $sale->user?->name }}</div>
    <div>Customer: {{ $sale->customer_name }}</div>
    @if($sale->vehicle)<div>Vehicle: {{ $sale->vehicle }}</div>@endif
    <hr>
    @foreach($sale->items as $item)
        <div><strong>{{ $item->variant_sku ?: $item->primary_part_number }}</strong> {{ $item->name }}</div>
        @if($item->variant_sku && $item->variant_sku !== $item->primary_part_number)<div class="r-small">Product {{ $item->primary_part_number }}</div>@endif
        @if($item->variant_label)<div class="r-small">{{ $item->variant_label }} @if($item->variant_sku) · {{ $item->variant_sku }} @endif</div>@endif
        <table><tr><td>{{ (float) $item->qty }} x {{ number_format((float) $item->unit_price, 2) }}</td><td class="r-right">{{ number_format((float) $item->line_total, 2) }}</td></tr>
        @if((float) $item->discount_amount > 0)<tr><td>Discount</td><td class="r-right">-{{ number_format((float) $item->discount_amount, 2) }}</td></tr>@endif</table>
    @endforeach
    <hr>
    <table>
        <tr><td>Subtotal</td><td class="r-right">{{ number_format((float) $sale->subtotal, 2) }}</td></tr>
        <tr><td>Discount</td><td class="r-right">-{{ number_format((float) $sale->discount_total, 2) }}</td></tr>
        <tr><td>Net</td><td class="r-right">{{ number_format((float) $sale->net_total, 2) }}</td></tr>
        <tr><td>VAT 5%</td><td class="r-right">{{ number_format((float) $sale->vat_total, 2) }}</td></tr>
        <tr><td class="r-total">TOTAL</td><td class="r-right r-total">{{ number_format((float) $sale->total, 2) }}</td></tr>
    </table>
    <hr>
    @foreach($sale->payments as $payment)<div>{{ ucfirst(str_replace('_',' ', $payment->method)) }}: {{ number_format((float) $payment->amount, 2) }}</div>@endforeach
    <div>Change: {{ number_format((float) $sale->change_total, 2) }}</div>
    <div>Balance: {{ number_format((float) $sale->balance_due, 2) }}</div>
    <hr>
    <div class="r-center r-small">{{ \App\Support\Pos::receiptFooter() }}</div>
    <div class="r-center r-barcode">*{{ $sale->invoice_number }}*</div>
</div>
</body>
</html>
