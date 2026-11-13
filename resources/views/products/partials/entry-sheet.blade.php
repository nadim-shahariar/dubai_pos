@php
    $sheetConfig = [
        'endpoint' => route('api.pos.products.batch'),
        'csrf' => csrf_token(),
        'taxRate' => \App\Support\Pos::vatRate(),
        'categories' => $categories->pluck('name')->values(),
        'partBrands' => $partBrands->values(),
        'vehicleBrands' => $brands->pluck('name')->values(),
        'vehicleModels' => $brands->flatMap(fn ($brand) => $brand->models->pluck('name'))->unique()->values(),
    ];
@endphp
<form class="product-entry-sheet" data-product-sheet data-sheet-config="{{ json_encode($sheetConfig) }}" novalidate>
    <div class="sheet-toolbar">
        <div><strong>Product entry sheet</strong><p>One product per row. Use Enter / Tab to move between cells. Paste cells copied from Excel. Part numbers are optional.</p></div>
        <div class="sheet-tools"><button class="btn btn-secondary btn-sm" type="button" data-sheet-add>+ Add row</button><button class="btn btn-secondary btn-sm" type="button" data-sheet-more>More columns</button></div>
    </div>
    <div class="sheet-message hidden" data-sheet-message role="status" aria-live="polite"></div>
    <div class="sheet-scroll" tabindex="0" aria-label="Product entry table, scroll horizontally for more columns">
        <table class="entry-sheet-table"><thead data-sheet-head></thead><tbody data-sheet-body></tbody></table>
    </div>
    <div class="sheet-bottom"><span data-sheet-count>0 product rows</span><span>Buying price and stock are required, including 0. Use Variants for independent vehicle stock.</span></div>
    <div class="sheet-footer"><button type="button" class="btn btn-secondary" data-sheet-reset>Clear / new batch</button><button type="submit" class="btn" data-sheet-save>Save all products</button></div>
</form>
@once
@push('head')
<link rel="stylesheet" href="{{ asset('css/product-sheet.css') }}?v={{ filemtime(public_path('css/product-sheet.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/product-sheet.js') }}?v={{ filemtime(public_path('js/product-sheet.js')) }}" defer></script>
@endpush
@endonce
