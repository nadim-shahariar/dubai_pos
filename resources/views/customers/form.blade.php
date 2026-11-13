@extends('layouts.app')

@section('title', $customer->exists ? 'Edit customer' : 'New customer')
@section('subtitle', $customer->exists ? $customer->company_name ?: $customer->name : 'Add a trade account or individual customer')

@section('content')
<div class="page-head">
    <div><h1>{{ $customer->exists ? 'Edit customer' : 'New customer' }}</h1><p>Credit limits are enforced at the POS. Leave at zero to disable credit.</p></div>
    <div class="page-actions"><a class="btn btn-secondary" href="{{ route('customers.index') }}">Back to customers</a></div>
</div>

<form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}">
    @csrf
    @if($customer->exists)@method('PATCH')@endif
    <div class="card">
        <div class="card-head"><h3>Customer details</h3></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field"><label>Contact name *</label><input name="name" value="{{ old('name', $customer->name) }}" required maxlength="150"></div>
                <div class="field"><label>Company name</label><input name="company_name" value="{{ old('company_name', $customer->company_name) }}" maxlength="150"></div>
                <div class="field"><label>Phone</label><input name="phone" value="{{ old('phone', $customer->phone) }}" maxlength="30"></div>
                <div class="field"><label for="car-plate-number">Car plate number (optional)</label><input id="car-plate-number" name="car_plate_number" value="{{ old('car_plate_number', $customer->car_plate_number) }}" maxlength="50" placeholder="e.g. Dubai A 12345"></div>
                <div class="field"><label>Email</label><input name="email" type="email" value="{{ old('email', $customer->email) }}" maxlength="150"></div>
                <div class="field"><label>TRN (15 digits)</label><input name="trn" value="{{ old('trn', $customer->trn) }}" maxlength="15" inputmode="numeric" placeholder="100123456789012"><div class="hint">Required on a full B2B tax invoice.</div></div>
                @if(auth()->user()->canApprove())
                    <div class="field"><label>Credit limit (AED)</label><input name="credit_limit" type="number" step="0.01" min="0" value="{{ old('credit_limit', $customer->credit_limit ?? 0) }}"></div>
                    <div class="field"><label>Payment terms (days)</label><input name="payment_terms_days" type="number" min="0" max="365" value="{{ old('payment_terms_days', $customer->payment_terms_days ?? 0) }}"></div>
                @else
                    <div class="field span-2"><div class="alert alert-info mb-0">Cashiers can create a customer record, but only a manager or owner can set a credit limit.</div></div>
                @endif
                <div class="field"><label>Status</label><label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->exists ? $customer->is_active : true))> Active customer</label></div>
                <div class="field span-2"><label>Billing address</label><textarea name="address" maxlength="1000">{{ old('address', $customer->address) }}</textarea></div>
                <div class="field span-2"><label>Internal notes</label><textarea name="notes" maxlength="1000">{{ old('notes', $customer->notes) }}</textarea></div>
            </div>
        </div>
    </div>
    <div class="flex justify-between items-center mt-2">
        <a class="btn btn-ghost" href="{{ route('customers.index') }}">Cancel</a>
        <button class="btn btn-lg" type="submit">{{ $customer->exists ? 'Save customer' : 'Create customer' }}</button>
    </div>
</form>
@endsection
