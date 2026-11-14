@php
    $company = \App\Support\Pos::company();
    $collections = $sale->collections;
    $hasCredit = $sale->payments->contains('method', 'credit');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tax Invoice {{ $sale->invoice_number }}</title>
    <link rel="stylesheet" href="{{ asset('css/invoice.css') }}?v={{ filemtime(public_path('css/invoice.css')) }}">
</head>
<body>
<nav class="invoice-actions" aria-label="Invoice actions">
    <a href="{{ route('sales.show', $sale) }}">← Back to invoice</a>
    <button type="button" onclick="window.print()">Print / Save PDF</button>
</nav>
<main class="invoice-sheet">
    <header class="invoice-header">
        <img class="invoice-logo" src="{{ asset('images/invoice-car.svg') }}" alt="Vehicle parts">
        <div class="invoice-company">
            <h1>{{ $company }}</h1>
            <div>{{ \App\Support\Pos::address() }}</div>
            <div>United Arab Emirates</div>
            <div>{{ \App\Support\Pos::phone() }} · {{ \App\Support\Pos::email() }}</div>
        </div>
    </header>
    <div class="invoice-heading"><h2>TAX INVOICE</h2><p>TRN: {{ \App\Support\Pos::trn() }}</p></div>
    <section class="invoice-meta" aria-label="Invoice details">
        <div class="invoice-customer">
            <span class="label">Customer:</span>
            <strong>{{ $sale->customer_name }}</strong>
            @if($sale->customer?->address)<div>{{ $sale->customer->address }}</div>@endif
            @if($sale->customer_trn)<div>TRN: {{ $sale->customer_trn }}</div>@endif
            @if($sale->customer?->phone)<div>{{ $sale->customer->phone }}</div>@endif
            @if($sale->person_name)<div>Person / Driver: {{ $sale->person_name }}</div>@endif
            @if($sale->car_plate_number)<div><strong>Car plate number: {{ $sale->car_plate_number }}</strong></div>@endif
            @if($sale->vehicle)<div>Vehicle: {{ $sale->vehicle }}</div>@endif
        </div>
        <div class="invoice-details"><dl>
            <div><dt>Invoice Date:</dt><dd>{{ $sale->sold_at->format('d/m/Y') }}</dd></div>
            <div><dt>Invoice No:</dt><dd>{{ $sale->invoice_number }}</dd></div>
            <div><dt>Terms:</dt><dd>{{ $hasCredit ? (($sale->customer?->payment_terms_days ?? 0).' Days Credit') : 'Immediate Payment' }}</dd></div>
            <div><dt>Salesperson:</dt><dd>{{ $sale->user?->name }}</dd></div>
        </dl></div>
    </section>
    <table class="invoice-items">
        <colgroup><col style="width:3%"><col style="width:29%"><col style="width:7%"><col style="width:11%"><col style="width:14%"><col style="width:6%"><col style="width:13%"><col style="width:17%"></colgroup>
        <thead><tr><th>#</th><th class="description">Item &amp; Description</th><th>Qty<br>Unit</th><th>Unit<br>Price</th><th>Amount<br>Excl. Tax</th><th>Tax<br>%</th><th>Tax<br>Amount</th><th>Amount<br>Incl. Tax</th></tr></thead>
        <tbody>
        @foreach($sale->items as $item)
            @php($unitExTax = $sale->price_mode === 'inclusive' ? (float) $item->unit_price / (1 + (float) $item->tax_rate / 100) : (float) $item->unit_price)
            <tr>
                <td class="row-number">{{ $loop->iteration }}</td>
                <td class="description">{{ $item->name }}<span class="line-detail">{{ $item->variant_sku ?: $item->primary_part_number }}@if($item->variant_label) · {{ $item->variant_label }}@endif</span>@if((float) $item->discount_amount > 0)<span class="line-detail">Discount: {{ number_format((float) $item->discount_amount, 2) }} AED</span>@endif</td>
                <td>{{ rtrim(rtrim(number_format((float) $item->qty, 3, '.', ''), '0'), '.') }}</td>
                <td>{{ number_format($unitExTax, 2) }} AED</td>
                <td>{{ number_format((float) $item->net_amount, 2) }} AED</td>
                <td>{{ (float) $item->tax_rate }}%</td>
                <td>{{ number_format((float) $item->vat_amount, 2) }} AED</td>
                <td>{{ number_format((float) $item->line_total, 2) }} AED</td>
            </tr>
        @endforeach
        <tr class="subtotal"><td colspan="4" class="subtotal-label">Sub Total</td><td>{{ number_format((float) $sale->net_total, 2) }} AED</td><td></td><td>{{ number_format((float) $sale->vat_total, 2) }} AED</td><td>{{ number_format((float) $sale->total, 2) }} AED</td></tr>
        </tbody>
    </table>
    <section class="invoice-settlement" aria-label="Invoice totals">
        <div class="invoice-words"><strong>Amount In Words:</strong> {{ \App\Support\AmountInWords::aed($sale->total) }}@if((float) $sale->discount_total > 0)<p>Discounts applied: {{ number_format((float) $sale->discount_total, 2) }} AED. Line amounts include allocated discounts.</p>@endif</div>
        <table class="invoice-totals">
            <tr><td>Total Amount Excl. Tax</td><td>{{ number_format((float) $sale->net_total, 2) }} AED</td></tr>
            <tr><td>Tax{{ $sale->items->pluck('tax_rate')->unique()->count() === 1 ? ' '.((float) $sale->items->first()->tax_rate).'%' : '' }}</td><td>{{ number_format((float) $sale->vat_total, 2) }} AED</td></tr>
            <tr class="grand"><td>Total Invoice Amount</td><td>{{ number_format((float) $sale->total, 2) }} AED</td></tr>
        </table>
    </section>
    @if($hasCredit || $collections->isNotEmpty())
    <section class="invoice-payments" aria-label="Credit payment history">
        <p>Total received: AED {{ number_format((float) $sale->paid_total, 2) }} · Balance: AED {{ number_format((float) $sale->balance_due, 2) }}</p>
        @foreach($collections as $collection)<p>Collection {{ $collection->receipt_number }} · {{ $collection->paid_at->format('d M Y') }} · {{ ucfirst(str_replace('_', ' ', $collection->method)) }}: AED {{ number_format((float) $collection->amount, 2) }}</p>@endforeach
    </section>
    @endif
    <div class="invoice-signature"><span>Received by</span></div>
    <footer class="invoice-footer"><div>{{ $company }} · {{ $sale->invoice_number }}</div></footer>
</main>
@if(request()->boolean('print'))<script>window.addEventListener('load', () => window.print());</script>@endif
</body>
</html>
