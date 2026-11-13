@extends('layouts.app')

@section('title', 'Customers')
@section('subtitle', 'Walk-in, trade and credit accounts')

@section('content')
<div class="page-head">
    <div><h1>Customers</h1><p>Company accounts, credit limits and purchase history. Walk-in sales do not need a customer record.</p></div>
    <div class="page-actions"><a class="btn" href="{{ route('customers.create') }}">+ Add customer</a></div>
</div>

<div class="card">
    <div class="card-head">
        <form method="GET" class="inline-form" style="flex:1">
            <div class="field" style="max-width:420px"><label>Search customers</label><input name="q" value="{{ $q }}" placeholder="Name, company, phone or TRN…"></div>
            <button class="btn btn-secondary" type="submit">Search</button>
            @if($q)<a class="btn btn-ghost" href="{{ route('customers.index') }}">Clear</a>@endif
        </form>
        <span class="small muted">{{ $customers->total() }} customers</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Customer</th><th>Phone</th><th>TRN</th><th>Credit limit</th><th>Outstanding</th><th>Invoices</th><th></th></tr></thead>
            <tbody>
            @forelse($customers as $customer)
                <tr>
                    <td><div class="main-cell">{{ $customer->company_name ?: $customer->name }}</div><div class="sub">{{ $customer->company_name ? $customer->name : ($customer->email ?: 'Individual') }}</div></td>
                    <td>{{ $customer->phone ?: '—' }}</td>
                    <td class="mono">{{ $customer->trn ?: '—' }}</td>
                    <td>AED {{ number_format((float) $customer->credit_limit, 2) }}</td>
                    <td>
                        @if((float) $customer->outstanding > 0)
                            <span class="badge badge-gold">AED {{ number_format((float) $customer->outstanding, 2) }}</span>
                        @else
                            <span class="badge badge-green">Clear</span>
                        @endif
                    </td>
                    <td>{{ $customer->sales_count }}</td>
                    <td class="text-right"><a class="btn btn-secondary btn-sm" href="{{ route('customers.edit', $customer) }}">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty"><strong>No customers found.</strong>Add a trade customer or use walk-in at the POS.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($customers->hasPages())
    <div class="pagination">
        <span class="info">Page {{ $customers->currentPage() }} of {{ $customers->lastPage() }}</span>
        @if($customers->previousPageUrl())<a href="{{ $customers->previousPageUrl() }}">← Previous</a>@else<span class="disabled">← Previous</span>@endif
        @if($customers->nextPageUrl())<a href="{{ $customers->nextPageUrl() }}">Next →</a>@else<span class="disabled">Next →</span>@endif
    </div>
@endif
@endsection
