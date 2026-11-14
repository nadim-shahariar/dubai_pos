@extends('layouts.app')
@section('title', 'Receive multiple stock items')
@section('subtitle', 'Select products or individual variants and record a dated receipt')
@section('content')
<div class="page-head"><div><h1>Bulk stock receive</h1><p>Select the items received, enter quantity and buying price, then save them together.</p></div><div class="page-actions"><a class="btn btn-secondary" href="{{ route('inventory.receipts.index') }}">Receipt history</a><a class="btn btn-secondary" href="{{ route('inventory.index') }}">Inventory</a></div></div>
<form method="POST" action="{{ route('inventory.receipts.store') }}" id="bulk-receive-form">
    @csrf<input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">
    <div class="card card-pad mb-2"><div class="form-grid">
        <div class="field"><label for="received-on">Received date *</label><input id="received-on" type="date" name="received_on" max="{{ now()->toDateString() }}" value="{{ old('received_on', now()->toDateString()) }}" required></div>
        <div class="field"><label for="receive-reference">Supplier invoice / reference</label><input id="receive-reference" name="reference" maxlength="255" value="{{ old('reference') }}"></div>
        <div class="field span-2"><label for="receive-notes">Notes</label><input id="receive-notes" name="notes" maxlength="2000" value="{{ old('notes') }}"></div>
    </div></div>
    <div class="card"><div class="card-head"><div class="field" style="max-width:450px"><label for="receive-filter">Find product or variant</label><input id="receive-filter" placeholder="Product, vehicle, part number…"></div><div class="page-actions"><button type="button" class="btn btn-secondary btn-sm" id="receive-select-visible">Select visible</button><button type="button" class="btn btn-ghost btn-sm" id="receive-clear">Clear selection</button><strong id="receive-selected-count">0 selected</strong></div></div>
    <div id="receive-error" class="alert alert-error hidden" role="alert"></div>
    @php($oldRows = collect(old('items', []))->keyBy(fn ($item) => $item['product_id'].'-'.($item['product_fitment_id'] ?? '')))
    <div class="table-wrap"><table class="table bulk-receive-table"><thead><tr><th>Select</th><th>Product</th><th>Variant</th><th>Part number</th><th>Current stock</th><th>Received qty *</th><th>Buying price / unit *</th></tr></thead><tbody>
    @foreach($products as $product)
        @foreach($product->fitments->isNotEmpty() ? $product->fitments : [null] as $fitment)
        @php($oldRow = $oldRows->get($product->id.'-'.($fitment?->id ?? ''), []))
        <tr data-receive-row data-product-id="{{ $product->id }}" data-fitment-id="{{ $fitment?->id }}" data-search="{{ mb_strtolower($product->name.' '.$product->primary_part_number.' '.$fitment?->mapped_part_number.' '.$fitment?->brand?->name.' '.$fitment?->model?->name) }}">
            <td><input type="checkbox" data-receive-check aria-label="Receive {{ $product->name }} {{ $fitment?->model?->name }}" @checked(!empty($oldRow))></td>
            <td class="main-cell">{{ $product->name }}</td>
            <td>{{ $fitment ? $fitment->brand?->name.' '.$fitment->model?->name.' '.$fitment->year_from.'–'.$fitment->year_to : 'Single product' }}</td>
            <td class="mono">{{ ($fitment?->mapped_part_number ?: $product->primary_part_number) ?: '—' }}</td>
            <td>{{ $fitment ? ($fitment->stock_qty === null ? 'Not set' : (float) $fitment->stock_qty) : (float) $product->stock_qty }}</td>
            <td><input data-receive-qty type="number" step="0.001" min="0.001" max="999999999.999" value="{{ $oldRow['qty'] ?? '' }}" aria-label="Received quantity" disabled></td>
            <td><input data-receive-cost type="number" step="0.01" min="0" value="{{ $oldRow['unit_cost'] ?? (auth()->user()->canViewCost() ? ($fitment?->cost_price ?? $product->cost_price) : '') }}" aria-label="Buying price per unit" disabled></td>
        </tr>
        @endforeach
    @endforeach
    </tbody></table></div><div class="card-body"><p class="small muted">Only selected rows are updated. Every variant keeps its own stock. The receipt date records when goods arrived; the audit trail retains when this receipt was entered.</p><button type="submit" class="btn" id="receive-submit">Receive selected items</button></div></div>
    <div id="receive-payload"></div>
</form>
@push('scripts')
<script>
(() => {
    const form = document.getElementById('bulk-receive-form');
    const rows = Array.from(form.querySelectorAll('[data-receive-row]'));
    const sync = () => {
        let count = 0;
        rows.forEach(row => { const checked = row.querySelector('[data-receive-check]').checked; if (checked) count++;
            row.querySelectorAll('[data-receive-qty],[data-receive-cost]').forEach(input => { input.disabled = !checked; input.required = checked; });
            row.classList.toggle('selected-row', checked);
        });
        document.getElementById('receive-selected-count').textContent = count + ' selected';
    };
    rows.forEach(row => row.querySelector('[data-receive-check]').addEventListener('change', sync));
    document.getElementById('receive-filter').addEventListener('input', event => {
        const term = event.target.value.toLowerCase().trim(); rows.forEach(row => row.hidden = !row.dataset.search.includes(term));
    });
    document.getElementById('receive-select-visible').addEventListener('click', () => { rows.filter(row => !row.hidden).forEach(row => row.querySelector('[data-receive-check]').checked = true); sync(); });
    document.getElementById('receive-clear').addEventListener('click', () => { rows.forEach(row => row.querySelector('[data-receive-check]').checked = false); sync(); });
    form.addEventListener('submit', event => {
        const selected = rows.filter(row => row.querySelector('[data-receive-check]').checked);
        if (!selected.length || selected.length > 200) { event.preventDefault(); const box = document.getElementById('receive-error'); box.textContent = 'Select between 1 and 200 items.'; box.classList.remove('hidden'); return; }
        const payload = document.getElementById('receive-payload'); payload.innerHTML = '';
        selected.forEach((row, index) => {
            const values = { product_id: row.dataset.productId, product_fitment_id: row.dataset.fitmentId, qty: row.querySelector('[data-receive-qty]').value, unit_cost: row.querySelector('[data-receive-cost]').value };
            Object.entries(values).forEach(([key,value]) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = `items[${index}][${key}]`; input.value = value; payload.appendChild(input); });
        });
        document.getElementById('receive-submit').disabled = true;
    });
    sync();
})();
</script>
@endpush
@endsection
