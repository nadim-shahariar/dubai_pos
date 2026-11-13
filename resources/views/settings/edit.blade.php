@extends('layouts.app')

@section('title', 'Settings')
@section('subtitle', 'Company, VAT and invoice configuration')

@section('content')
<div class="page-head">
    <div><h1>Settings</h1><p>These values drive every invoice, receipt and new sale. Only the owner can change them.</p></div>
</div>

<form method="POST" action="{{ route('settings.update') }}">
    @csrf @method('PATCH')
    <div class="grid-2">
        <div class="card">
            <div class="card-head"><h3>Company &amp; tax</h3></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="field span-2"><label>Legal / trading name *</label><input name="company_name" value="{{ old('company_name', $settings['company_name']) }}" required maxlength="150"></div>
                    <div class="field"><label>TRN (15 digits) *</label><input name="company_trn" value="{{ old('company_trn', $settings['company_trn']) }}" required maxlength="15" inputmode="numeric"></div>
                    <div class="field"><label>Phone</label><input name="company_phone" value="{{ old('company_phone', $settings['company_phone']) }}" maxlength="30"></div>
                    <div class="field span-2"><label>Address *</label><textarea name="company_address" required maxlength="500">{{ old('company_address', $settings['company_address']) }}</textarea></div>
                    <div class="field"><label>Email</label><input name="company_email" type="email" value="{{ old('company_email', $settings['company_email']) }}" maxlength="150"></div>
                    <div class="field"><label>VAT rate (%) *</label><input name="vat_rate" type="number" step="0.01" min="0" max="100" value="{{ old('vat_rate', $settings['vat_rate']) }}" required></div>
                    <div class="field"><label>Price mode</label><label class="check"><input type="hidden" name="prices_include_vat" value="0"><input type="checkbox" name="prices_include_vat" value="1" @checked(old('prices_include_vat', $settings['prices_include_vat']) == '1')> Shelf prices include VAT</label><div class="hint">Retail in the UAE is usually VAT-inclusive.</div></div>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-head"><h3>Invoices &amp; approval</h3></div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="field"><label>Invoice prefix *</label><input name="invoice_prefix" value="{{ old('invoice_prefix', $settings['invoice_prefix']) }}" required maxlength="10"></div>
                        <div class="field"><label>Cashier discount limit (%) *</label><input name="cashier_discount_limit" type="number" step="0.01" min="0" max="100" value="{{ old('cashier_discount_limit', $settings['cashier_discount_limit']) }}" required></div>
                        <div class="field span-2"><label>Receipt / invoice footer *</label><textarea name="receipt_footer" required maxlength="500">{{ old('receipt_footer', $settings['receipt_footer']) }}</textarea></div>
                    </div>
                    <div class="alert alert-info mt-2">Manager approval PIN for this demo is <strong>1234</strong>. Change it in the user record before production.</div>
                </div>
            </div>
            <div class="card">
                <div class="card-head"><h3>Demo users</h3></div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Role</th><th>Email</th><th>PIN</th></tr></thead>
                        <tbody>
                        <tr><td>Owner</td><td class="mono">owner@gitgadgets.test</td><td>9999</td></tr>
                        <tr><td>Manager</td><td class="mono">manager@gitgadgets.test</td><td>1234</td></tr>
                        <tr><td>Cashier</td><td class="mono">cashier@gitgadgets.test</td><td>1111</td></tr>
                        <tr><td>Storekeeper</td><td class="mono">store@gitgadgets.test</td><td>2222</td></tr>
                        <tr><td>Accountant</td><td class="mono">accounts@gitgadgets.test</td><td>3333</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="flex justify-end mt-2"><button class="btn btn-lg" type="submit">Save settings</button></div>
</form>
@endsection
