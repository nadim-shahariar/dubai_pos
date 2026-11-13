@php($company = \App\Support\Pos::company())
@php($trn = \App\Support\Pos::trn())
@php($address = \App\Support\Pos::address())
@php($phone = \App\Support\Pos::phone())
@php($previous = (float) $payment->sale->balance_due + (float) $payment->amount)
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $payment->receipt_number }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="print-shell">
<div class="print-actions no-print">
    <a class="btn btn-secondary" href="{{ route('customers.statement', $payment->customer) }}">← Back to statement</a>
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
            <h2>Payment Receipt</h2>
            <div class="ar">سند قبض</div>
            <div class="small" style="margin-top:6px">Credit collection</div>
        </div>
    </div>

    <div class="inv-meta">
        <div class="inv-meta-box">
            <h3>Receipt details</h3>
            <div><strong>Receipt no:</strong> {{ $payment->receipt_number }}</div>
            <div><strong>Date:</strong> {{ $payment->paid_at->format('d/m/Y H:i') }}</div>
            <div><strong>Against invoice:</strong> {{ $payment->sale->invoice_number }}</div>
            <div><strong>Method:</strong> {{ ucfirst(str_replace('_', ' ', $payment->method)) }}</div>
            @if($payment->reference)<div><strong>Reference:</strong> {{ $payment->reference }}</div>@endif
        </div>
        <div class="inv-meta-box">
            <h3>Received from</h3>
            <div><strong>{{ $payment->customer->company_name ?: $payment->customer->name }}</strong></div>
            <div>{{ $payment->customer->address ?: 'Dubai, UAE' }}</div>
            <div>TRN: {{ $payment->customer->trn ?: 'Not provided' }}</div>
            <div>Phone: {{ $payment->customer->phone ?: '—' }}</div>
        </div>
    </div>

    <table class="inv-table">
        <thead><tr><th>Description</th><th class="num">Amount (AED)</th></tr></thead>
        <tbody>
            <tr><td>Credit payment received for invoice {{ $payment->sale->invoice_number }}</td><td class="num">{{ number_format((float) $payment->amount, 2) }}</td></tr>
        </tbody>
    </table>

    <div class="inv-totals">
        <table>
            <tr><td class="label">Balance before payment</td><td class="val">AED {{ number_format($previous, 2) }}</td></tr>
            <tr><td class="label">Payment received</td><td class="val">- AED {{ number_format((float) $payment->amount, 2) }}</td></tr>
            <tr class="grand"><td>Balance remaining</td><td class="val">AED {{ number_format((float) $payment->sale->balance_due, 2) }}</td></tr>
        </table>
    </div>

    <div class="inv-meta" style="margin-top:18px">
        <div class="inv-meta-box">
            <h3>Received by</h3>
            <div>{{ $payment->user?->name }}</div>
            @if($payment->shift)<div class="small">Shift opened {{ $payment->shift->opened_at->format('d/m/Y H:i') }}</div>@endif
            @if($payment->notes)<div class="small">Note: {{ $payment->notes }}</div>@endif
        </div>
        <div class="inv-meta-box">
            <h3>Customer signature</h3>
            <div style="height:48px"></div>
            <div class="small">Received the above amount on account.</div>
        </div>
    </div>

    <div class="small muted" style="margin-top:20px;text-align:center">This is a payment receipt, not a tax invoice. The original tax invoice remains {{ $payment->sale->invoice_number }}.</div>
</div>
</body>
</html>
