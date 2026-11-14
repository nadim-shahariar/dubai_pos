@extends('layouts.app')

@section('title', 'Credit statement')
@section('subtitle', ($customer->company_name ?: $customer->name).' · outstanding credit')

@section('body-class', 'statement-page')
@section('content')
<div class="statement-print-heading"><strong>{{ \App\Support\Pos::company() }}</strong><div>{{ \App\Support\Pos::address() }} · {{ \App\Support\Pos::phone() }} · TRN {{ \App\Support\Pos::trn() }}</div><div>Customer account statement · Printed {{ now()->format('d M Y H:i') }}</div></div>
<div class="page-head">
    <div>
        <h1>{{ $customer->company_name ?: $customer->name }}</h1>
        <p>{{ $customer->phone ?: 'No phone' }} · {{ $customer->email ?: 'No email' }} · TRN {{ $customer->trn ?: 'not provided' }}</p>
    </div>
    <div class="page-actions no-print">
        <a class="btn btn-secondary" href="{{ route(auth()->user()->canAccessRoute('customers.index') ? 'customers.index' : auth()->user()->homeRoute()) }}">Back to customers</a>
        <button class="btn" type="button" onclick="window.print()">Print statement</button>
    </div>
</div>

<div class="stat-grid">
    <div class="stat"><div class="stat-label">Total invoiced</div><div class="stat-value">AED {{ number_format($totals['invoiced'], 2) }}</div><div class="stat-meta">{{ $customer->sales->count() }} invoices</div></div>
    <div class="stat green"><div class="stat-label">Paid at sale</div><div class="stat-value">AED {{ number_format($totals['paid_at_sale'], 2) }}</div><div class="stat-meta">Cash, card and bank</div></div>
    <div class="stat gold"><div class="stat-label">Credit sold</div><div class="stat-value">AED {{ number_format($totals['credit_sold'], 2) }}</div><div class="stat-meta">Original credit amount</div></div>
    <div class="stat {{ $totals['outstanding'] > 0 ? 'red' : 'green' }}"><div class="stat-label">Balance remaining</div><div class="stat-value">AED {{ number_format($totals['outstanding'], 2) }}</div><div class="stat-meta">Collected later: AED {{ number_format($totals['collected'], 2) }}</div></div>
</div>

<div class="card mt-3">
    <div class="card-head"><h3>Account details</h3><span class="badge {{ $totals['outstanding'] > 0 ? 'badge-gold' : 'badge-green' }}">{{ $totals['outstanding'] > 0 ? 'Balance due' : 'Account clear' }}</span></div>
    <div class="card-body">
        <div class="grid-3">
            <div><div class="small muted">Credit limit</div><div class="main-cell">AED {{ number_format((float) $customer->credit_limit, 2) }}</div></div>
            <div><div class="small muted">Payment terms</div><div class="main-cell">{{ $customer->payment_terms_days }} days</div></div>
            <div><div class="small muted">Available credit</div><div class="main-cell">AED {{ number_format(max(0, (float) $customer->credit_limit - $totals['outstanding']), 2) }}</div></div>
        </div>
        @if($customer->address)<div class="small muted mt-2">{{ $customer->address }}</div>@endif
    </div>
</div>

<div class="card mt-3">
    <div class="card-head"><h3>Monthly credit</h3><span class="small muted">Credit left is the open balance on invoices from that month</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Month</th><th class="text-right">Invoices</th><th class="text-right">Invoiced</th><th class="text-right">Credit sold</th><th class="text-right">Collected</th><th class="text-right">Credit left</th></tr></thead>
            <tbody>
            @forelse($monthly as $month)
                <tr>
                    <td class="main-cell">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month['month'])->format('M Y') }}</td>
                    <td class="text-right">{{ $month['invoices'] }}</td>
                    <td class="text-right">AED {{ number_format($month['invoiced'], 2) }}</td>
                    <td class="text-right text-gold">AED {{ number_format($month['credit_sold'], 2) }}</td>
                    <td class="text-right text-success">AED {{ number_format($month['collected'], 2) }}</td>
                    <td class="text-right main-cell">AED {{ number_format($month['balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><strong>No invoices.</strong></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-3" id="credit-invoices">
    <div class="card-head"><h3>Credit invoices</h3><span class="small muted">Receive partial or full payment against each invoice</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th class="text-right">Total</th><th class="text-right">Paid at sale</th><th class="text-right">Credit</th><th class="text-right">Collected</th><th class="text-right">Balance</th><th>Status</th><th class="statement-action no-print"></th></tr></thead>
            <tbody>
            @forelse($invoices as $row)
                <tr>
                    <td class="mono main-cell">{{ $row['sale']->invoice_number }}</td>
                    <td>{{ $row['sale']->sold_at->format('d M Y') }}</td>
                    <td>
                        {{ $row['due_date']->format('d M Y') }}
                        @if($row['days_overdue'] > 0)<div class="sub text-danger">{{ $row['days_overdue'] }} days overdue</div>@endif
                    </td>
                    <td class="text-right">AED {{ number_format((float) $row['sale']->total, 2) }}</td>
                    <td class="text-right">AED {{ number_format($row['paid_at_sale'], 2) }}</td>
                    <td class="text-right">AED {{ number_format($row['credit_sold'], 2) }}</td>
                    <td class="text-right text-success">AED {{ number_format($row['collected'], 2) }}</td>
                    <td class="text-right main-cell {{ $row['balance'] > 0 ? 'text-danger' : 'text-success' }}">AED {{ number_format($row['balance'], 2) }}</td>
                    <td><span class="badge {{ $row['balance'] <= 0 ? 'badge-green' : ($row['collected'] > 0 ? 'badge-gold' : 'badge-red') }}">{{ $row['balance'] <= 0 ? 'Paid' : ($row['collected'] > 0 ? 'Partial' : 'Credit') }}</span></td>
                    <td class="text-right nowrap statement-action no-print">
                        @if(auth()->user()->canAccessRoute('sales.invoice'))<a class="btn btn-secondary btn-sm" target="_blank" href="{{ route('sales.invoice', $row['sale']) }}">Invoice</a>@endif
                        @if($row['balance'] > 0 && auth()->user()->canCollectCredit())
                            <button class="btn btn-sm" type="button" data-collect data-sale="{{ $row['sale']->id }}" data-invoice="{{ $row['sale']->invoice_number }}" data-balance="{{ number_format($row['balance'], 2, '.', '') }}">Receive</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10"><div class="empty"><strong>No credit invoices.</strong></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-head"><h3>Collections</h3><span class="small muted">{{ $customer->collections->count() }} receipts</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Receipt</th><th>Date</th><th>Invoice</th><th>Method</th><th>Received by</th><th class="text-right">Amount</th><th class="statement-action no-print"></th></tr></thead>
            <tbody>
            @forelse($customer->collections as $payment)
                <tr>
                    <td class="mono main-cell">{{ $payment->receipt_number }}</td>
                    <td>{{ $payment->paid_at->format('d M Y H:i') }}</td>
                    <td class="mono">{{ $payment->sale?->invoice_number }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</td>
                    <td>{{ $payment->user?->name }}</td>
                    <td class="text-right main-cell text-success">AED {{ number_format((float) $payment->amount, 2) }}</td>
                    <td class="text-right"><a class="btn btn-secondary btn-sm" target="_blank" href="{{ route('credit-payments.receipt', $payment) }}">Receipt</a></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty"><strong>No collections yet.</strong>Use Receive on a credit invoice.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop" id="collect-modal">
    <div class="modal small">
        <div class="modal-head">
            <div><h2>Receive credit payment</h2><p id="collect-subtitle">Select an invoice.</p></div>
            <button class="modal-close" type="button" data-close-collect>×</button>
        </div>
        <form method="POST" action="{{ route('customers.collections.store', $customer) }}">
            @csrf
            <input type="hidden" name="sale_id" id="collect-sale-id">
            <div class="modal-body">
                <div class="quick-summary" id="collect-balance">Remaining balance: AED 0.00</div>
                <div class="field"><label>Amount received (AED) *</label><input type="number" name="amount" id="collect-amount" min="0.01" step="0.01" required></div>
                <div class="field"><label>Payment method *</label><select name="method" required><option value="cash">Cash</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option></select></div>
                <div class="field"><label>Reference</label><input name="reference" maxlength="100" placeholder="Cheque, transfer or card reference"></div>
                <div class="field"><label>Note</label><input name="notes" maxlength="1000" placeholder="Optional internal note"></div>
            </div>
            <div class="modal-foot"><button class="btn btn-secondary" type="button" data-close-collect>Cancel</button><button class="btn" type="submit">Receive payment</button></div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('collect-modal');
    const amount = document.getElementById('collect-amount');
    const balance = document.getElementById('collect-balance');
    const subtitle = document.getElementById('collect-subtitle');
    const saleInput = document.getElementById('collect-sale-id');

    document.querySelectorAll('[data-collect]').forEach(button => {
        button.addEventListener('click', () => {
            const remaining = Number(button.dataset.balance || 0);
            saleInput.value = button.dataset.sale;
            amount.value = remaining.toFixed(2);
            amount.max = remaining.toFixed(2);
            balance.textContent = 'Remaining balance: AED ' + remaining.toLocaleString('en-AE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            subtitle.textContent = button.dataset.invoice + ' · partial or full payment';
            modal.classList.add('open');
            setTimeout(() => amount.focus(), 100);
        });
    });
    document.querySelectorAll('[data-close-collect]').forEach(button => button.addEventListener('click', () => modal.classList.remove('open')));
    modal.addEventListener('click', event => { if (event.target === modal) modal.classList.remove('open'); });
})();
</script>
@endpush
@endsection
