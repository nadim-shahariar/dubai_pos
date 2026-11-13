@extends('layouts.app')

@section('title', $product->exists ? 'Edit product' : 'New product')
@section('subtitle', $product->exists ? $product->primary_part_number.' · '.$product->name : 'Add a part to the catalogue')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $product->exists ? $product->name : 'New product' }}</h1>
        <p>{{ $product->exists ? 'Update pricing, fitment and cross-references. Stock changes are recorded through Inventory.' : 'Create the core product first, then add vehicle fitments and cross-references.' }}</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="{{ route('products.index') }}">Back to catalogue</a>
        @if($product->exists)<a class="btn btn-secondary" href="{{ route('inventory.show', $product) }}">Stock &amp; movements</a>@endif
    </div>
</div>

<form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}">
    @csrf
    @if($product->exists)@method('PATCH')@endif

    <div class="card mb-2">
        <div class="card-head"><h3>Product details</h3>@if($product->exists)<span class="badge {{ $product->is_active ? 'badge-green' : 'badge-gray' }}">{{ $product->is_active ? 'Active' : 'Inactive' }}</span>@endif</div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field"><label>Primary part number *</label><input name="primary_part_number" value="{{ old('primary_part_number', $product->primary_part_number) }}" required maxlength="50" placeholder="BP-101">@error('primary_part_number')<div class="error">{{ $message }}</div>@enderror</div>
                <div class="field"><label>Barcode / EAN</label><input name="barcode" value="{{ old('barcode', $product->barcode) }}" maxlength="64" placeholder="Optional">@error('barcode')<div class="error">{{ $message }}</div>@enderror</div>
                <div class="field"><label>Product name *</label><input name="name" value="{{ old('name', $product->name) }}" required maxlength="150" placeholder="Front Brake Pad Set">@error('name')<div class="error">{{ $message }}</div>@enderror</div>
                <div class="field"><label>Arabic name</label><input name="name_ar" value="{{ old('name_ar', $product->name_ar) }}" maxlength="150" placeholder="Optional"></div>
                <div class="field"><label>Category</label><select name="category_id"><option value="">Uncategorised</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                <div class="field"><label>Part brand / manufacturer</label><input name="brand" value="{{ old('brand', $product->brand) }}" maxlength="80" placeholder="Denso, Bosch, Toyota Genuine"></div>
                <div class="field"><label>Unit</label><input name="unit" value="{{ old('unit', $product->unit ?: 'piece') }}" required maxlength="20"></div>
                <div class="field"><label>Bin / shelf location</label><input name="bin_location" value="{{ old('bin_location', $product->bin_location) }}" maxlength="50" placeholder="A-12-3"></div>
                <div class="field span-2"><label>Description</label><textarea name="description" maxlength="1000" placeholder="Specification, position, warranty notes…">{{ old('description', $product->description) }}</textarea></div>
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-head"><h3>Pricing, tax &amp; stock</h3>@if($product->cost_pending)<span class="badge badge-gold">Cost pending</span>@endif</div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field"><label>Selling price (AED) *</label><input name="selling_price" type="number" step="0.01" min="0" value="{{ old('selling_price', $product->selling_price) }}" required><div class="hint">Stored in the shop's {{ \App\Support\Pos::pricesIncludeVat() ? 'VAT-inclusive' : 'VAT-exclusive' }} price mode.</div></div>
                <div class="field"><label>VAT rate (%) *</label><input name="tax_rate" type="number" step="0.01" min="0" max="100" value="{{ old('tax_rate', $product->tax_rate ?? 5) }}" required></div>
                @if(auth()->user()->canViewCost())
                    <div class="field"><label>Cost price (AED) *</label><input name="cost_price" type="number" step="0.01" min="0" value="{{ old('cost_price', $product->cost_price) }}" required><div class="hint">Hidden from cashier screens and customer documents.</div></div>
                @else
                    <div class="field"><label>Cost price</label><input value="Hidden for your role" disabled><div class="hint">Only owners, managers and accountants can view or change cost.</div></div>
                @endif
                <div class="field"><label>Secret cost code *</label><input name="secret_cost_code" value="{{ old('secret_cost_code', $product->secret_cost_code) }}" required maxlength="20" placeholder="MAE" class="mono"><div class="hint">Manual entry only. Never derived from cost or price, never printed for customers.</div>@error('secret_cost_code')<div class="error">{{ $message }}</div>@enderror</div>
                @if(!$product->exists)
                    <div class="field"><label>Opening stock</label><input name="stock_qty" type="number" step="1" min="0" value="{{ old('stock_qty', 0) }}"><div class="hint">Creates an opening stock movement.</div></div>
                @else
                    <div class="field"><label>Current stock</label><input value="{{ (float) $product->stock_qty }}" disabled><div class="hint">Use Inventory to receive or adjust stock.</div></div>
                @endif
                <div class="field"><label>Reorder level</label><input name="reorder_level" type="number" step="1" min="0" value="{{ old('reorder_level', $product->reorder_level ?? 0) }}"></div>
                <div class="field"><label>Options</label>
                    <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->exists ? $product->is_active : true))> Active and sellable</label>
                    <label class="check mt-1"><input type="hidden" name="track_stock" value="0"><input type="checkbox" name="track_stock" value="1" @checked(old('track_stock', $product->exists ? $product->track_stock : true))> Track stock (uncheck for labour/service lines)</label>
                </div>
            </div>
        </div>
    </div>

    <div class="flex justify-between items-center">
        <a class="btn btn-ghost" href="{{ route('products.index') }}">Cancel</a>
        <button class="btn btn-lg" type="submit">{{ $product->exists ? 'Save product' : 'Create product' }}</button>
    </div>
</form>

@if($product->exists)
    <div class="grid-2 mt-3">
        <div class="card">
            <div class="card-head"><h3>Vehicle fitments</h3><span class="badge badge-teal">{{ $product->fitments->count() }}</span></div>
            <div class="card-body">
                <div class="table-wrap mb-2">
                    <table class="table">
                        <thead><tr><th>Vehicle</th><th>Years</th><th>Mapped number</th><th></th></tr></thead>
                        <tbody>
                        @forelse($product->fitments as $fitment)
                            <tr>
                                <td>{{ $fitment->brand?->name }} {{ $fitment->model?->name }}<div class="sub">{{ $fitment->engine }}</div></td>
                                <td>{{ $fitment->year_from }}–{{ $fitment->year_to }}</td>
                                <td class="mono">{{ $fitment->mapped_part_number ?: '—' }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('products.fitments.destroy', [$product, $fitment]) }}" data-confirm="Remove this fitment?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-sm text-danger" type="submit">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="empty" style="padding:18px">No fitment recorded. This product is searchable by part number only.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <form method="POST" action="{{ route('products.fitments.store', $product) }}" class="inline-form">
                    @csrf
                    <div class="field"><label>Brand</label><select name="car_brand_id" data-brand-select required><option value="">Brand</option>@foreach($brands as $brand)<option value="{{ $brand->id }}">{{ $brand->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Model</label><select name="car_model_id" data-model-select required><option value="">Model</option>@foreach($brands as $brand)@foreach($brand->models as $model)<option value="{{ $model->id }}" data-brand="{{ $brand->id }}">{{ $model->name }}</option>@endforeach @endforeach</select></div>
                    <div class="field" style="max-width:86px"><label>From</label><input name="year_from" type="number" min="1950" max="2100" required value="2018"></div>
                    <div class="field" style="max-width:86px"><label>To</label><input name="year_to" type="number" min="1950" max="2100" required value="2024"></div>
                    <div class="field"><label>Mapped no.</label><input name="mapped_part_number" maxlength="50" placeholder="Optional"></div>
                    <button class="btn btn-secondary" type="submit">Add fitment</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Cross-references</h3><span class="badge badge-gray">{{ $product->aliases->count() }}</span></div>
            <div class="card-body">
                <div class="table-wrap mb-2">
                    <table class="table">
                        <thead><tr><th>Type</th><th>Number</th><th>Brand</th><th></th></tr></thead>
                        <tbody>
                        @forelse($product->aliases as $alias)
                            <tr>
                                <td><span class="badge badge-gray">{{ strtoupper($alias->reference_type) }}</span></td>
                                <td class="mono">{{ $alias->reference_number }}</td>
                                <td>{{ $alias->brand ?: '—' }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('products.aliases.destroy', [$product, $alias]) }}" data-confirm="Remove this cross-reference?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-sm text-danger" type="submit">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="empty" style="padding:18px">No cross-references. Add OEM or competitor numbers so the counter can search them.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <form method="POST" action="{{ route('products.aliases.store', $product) }}" class="inline-form">
                    @csrf
                    <div class="field" style="max-width:120px"><label>Type</label><select name="reference_type"><option value="oem">OEM</option><option value="manufacturer">Manufacturer</option><option value="aftermarket">Aftermarket</option><option value="competitor">Competitor</option><option value="superseded">Superseded</option><option value="alias">Alias</option></select></div>
                    <div class="field"><label>Reference number</label><input name="reference_number" required maxlength="50" placeholder="04465-33450"></div>
                    <div class="field"><label>Brand</label><input name="brand" maxlength="80" placeholder="Toyota"></div>
                    <button class="btn btn-secondary" type="submit">Add reference</button>
                </form>
            </div>
        </div>
    </div>
@else
    <div class="alert alert-info mt-3">Save the product first, then add vehicle fitments and cross-references.</div>
@endif
@endsection
