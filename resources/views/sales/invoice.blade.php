@php($company = \App\Support\Pos::company())
@php($trn = \App\Support\Pos::trn())
@php($address = \App\Support\Pos::address())
@php($phone = \App\Support\Pos::phone())
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tax Invoice {{ $sale->invoice_number }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="print-shell">
<div class="print-actions no-print">
    <a class="btn btn-secondary" href="{{ route('sales.show', $sale) }}">← Back to invoice</a>
    <button class="btn" onclick="window.print()">Print / Save PDF</button>
</div>

<div class="invoice-a4">
    <div class="inv-head">
        <div class="inv-company">
            <div class="flex items-center gap-2 mb-1"><span class="brand-mark" style="width:34px;height:34px;font-size:11px">GG</span><h1>{{ $company }}</h1></div>
            <div>{{ $address }}</div>
            <div>TRN: {{ $trn }} · Tel: {{ $phone }}</div>
            <div>{{ \App\Support\Pos::email() }}</div>
        </div>
        <div class="inv-title">
            <h2>Tax Invoice</h2>
            <div class="ar">فاتورة ضريبية</div>
            <div class="small" style="margin-top:6px">Original for recipient</div>
        </div>
    </div>

    <div class="inv-meta">
        <div class="inv-meta-box">
            <h3>Invoice details</h3>
            <div><strong>No:</strong> {{ $sale->invoice_number }}</div>
            <div><strong>Date:</strong> {{ $sale->sold_at->format('d/m/Y H:i') }}</div>
            <div><strong>Cashier:</strong> {{ $sale->user?->name }}</div>
            <div><strong>Payment:</strong> {{ $sale->paymentLabel() }}</div>
        </div>
        <div class="inv-meta-box">
            <h3>Bill to</h3>
            <div><strong>{{ $sale->customer_name }}</strong></div>
            <div>{{ $sale->customer?->address ?: 'Dubai, UAE' }}</div>
            <div>TRN: {{ $sale->customer_trn ?: 'Not provided' }}</div>
            @if($sale->vehicle)<div>Vehicle: {{ $sale->vehicle }}</div>@endif
        </div>
    </div>

    <table class="inv-table">
        <thead><tr><th>#</th><th>Part no.</th><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Discount</th><th class="num">Net</th><th class="num">VAT</th><th class="num">Total</th></tr></thead>
        <tbody>
        @foreach($sale->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td class="mono">{{ $item->variant_sku ?: $item->primary_part_number }}@if($item->variant_sku && $item->variant_sku !== $item->primary_part_number)<div class="small">Product {{ $item->primary_part_number }}</div>@endif</td>
                <td>{{ $item->name }}
                    @if($item->variant_label)<div class="small">{{ $item->variant_label }} @if($item->variant_sku) · {{ $item->variant_sku }} @endif</div>@endif
                    @if($item->price_override)<div class="small">Price override: {{ $item->override_reason }}</div>@endif
                </td>
                <td class="num">{{ (float) $item->qty }}</td>
                <td class="num">{{ number_format((float) $item->unit_price, 2) }}</td>
                <td class="num">{{ number_format((float) $item->discount_amount, 2) }}</td>
                <td class="num">{{ number_format((float) $item->net_amount, 2) }}</td>
                <td class="num">{{ number_format((float) $item->vat_amount, 2) }}</td>
                <td class="num">{{ number_format((float) $item->line_total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="inv-totals">
        <table>
            <tr><td class="label">Subtotal</td><td class="val">AED {{ number_format((float) $sale->subtotal, 2) }}</td></tr>
            <tr><td class="label">Discounts</td><td class="val">- AED {{ number_format((float) $sale->discount_total, 2) }}</td></tr>
            <tr><td class="label">Net amount</td><td class="val">AED {{ number_format((float) $sale->net_total, 2) }}</td></tr>
            <tr><td class="label">VAT 5%</td><td class="val">AED {{ number_format((float) $sale->vat_total, 2) }}</td></tr>
            <tr class="grand"><td>Total</td><td class="val">AED {{ number_format((float) $sale->total, 2) }}</td></tr>
        </table>
    </div>

    <div class="inv-pay">
        <div><strong>Payment</strong><br>
            @foreach($sale->payments as $payment){{ ucfirst(str_replace('_',' ', $payment->method)) }}: AED {{ number_format((float) $payment->amount, 2) }}@if($payment->reference) ({{ $payment->reference }})@endif<br>@endforeach
            Change: AED {{ number_format((float) $sale->change_total, 2) }} · Balance: AED {{ number_format((float) $sale->balance_due, 2) }}
        </div>
        <div><strong>VAT summary</strong><br>Standard rate 5% · Taxable AED {{ number_format((float) $sale->net_total, 2) }} · VAT AED {{ number_format((float) $sale->vat_total, 2) }}<br>
            Prices are {{ $sale->price_mode }} of VAT.
        </div>
    </div>

    @if($sale->notes)<div class="inv-note"><strong>Note:</strong> {{ $sale->notes }}</div>@endif
    <div class="inv-note">
        {{ \App\Support\Pos::receiptFooter() }}<br>
        This is a computer-generated tax invoice. Please retain it for your records.
    </div>
    <div class="inv-foot">
        <span>{{ $company }} · {{ $address }}</span>
        <span>{{ $sale->invoice_number }} · Page 1 of 1</span>
    </div>
</div>
</body>
</html>
