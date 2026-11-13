@extends('layouts.app')

@section('title', 'Return · '.$sale->invoice_number)
@section('subtitle', $sale->customer_name.' · '.$sale->sold_at->format('d M Y'))

@section('content')
<div class="page-head">
    <div><h1>Return / credit note</h1><p>Return only what was sold on this invoice. Restocking and VAT are handled automatically.</p></div>
    <div class="page-actions"><a class="btn btn-secondary" href="{{ route('sales.show', $sale) }}">Back to invoice</a></div>
</div>

<div class="alert alert-info">Original invoice <strong>{{ $sale->invoice_number }}</strong> · {{ $sale->customer_name }} · Total AED {{ number_format((float) $sale->total, 2) }}. A credit note will be issued for the returned value.</div>

<form method="POST" action="{{ route('sales.return.store', $sale) }}">
    @csrf
    <div class="card">
        <div class="card-head"><h3>Select items to return</h3><span class="badge badge-gray">{{ $sale->items->count() }} lines</span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Part</th><th>Sold</th><th>Already returned</th><th style="width:120px">Return qty</th><th>Condition</th><th>Restock</th><th class="text-right">Line total</th></tr></thead>
                <tbody>
                @foreach($sale->items as $index => $item)
                    @php($already = $item->returnedQty())
                    @php($available = max(0, (float) $item->qty - $already))
                    <tr>
                        <td><div class="main-cell">{{ $item->name }}</div><div class="sub mono">{{ $item->primary_part_number }}</div>
                            <input type="hidden" name="items[{{ $index }}][sale_item_id]" value="{{ $item->id }}">
                        </td>
                        <td>{{ (float) $item->qty }}</td>
                        <td>{{ $already }}</td>
                        <td><input name="items[{{ $index }}][qty]" type="number" min="0" max="{{ $available }}" step="1" value="0" class="input" @disabled($available <= 0)></td>
                        <td>
                            <select name="items[{{ $index }}][condition]" @disabled($available <= 0)>
                                <option value="resellable">Resellable</option>
                                <option value="damaged">Damaged</option>
                                <option value="defective">Defective</option>
                                <option value="wrong_part">Wrong part</option>
                            </select>
                        </td>
                        <td><label class="check"><input type="checkbox" name="items[{{ $index }}][restock]" value="1" @checked($available > 0)> Return to stock</label></td>
                        <td class="text-right main-cell">AED {{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field"><label>Reason *</label><input name="reason" required maxlength="255" placeholder="Wrong part, damaged, customer changed mind…"></div>
                <div class="field"><label>Refund method *</label><select name="refund_method"><option value="cash">Cash</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option><option value="credit">Customer credit</option></select></div>
            </div>
            <div class="alert alert-info mt-2">Only return quantities above zero are processed. The credit note number is generated automatically and the original invoice remains on record.</div>
        </div>
    </div>
    <div class="flex justify-between items-center mt-2">
        <a class="btn btn-ghost" href="{{ route('sales.show', $sale) }}">Cancel</a>
        <button class="btn btn-gold btn-lg" type="submit">Issue credit note</button>
    </div>
</form>
@endsection
