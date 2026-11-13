<?php

namespace App\Http\Controllers;

use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $products = Product::query()
            ->with(['category', 'fitments.brand', 'fitments.model'])
            ->withCount(['fitments', 'aliases'])
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('primary_part_number', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('barcode', 'like', "%{$q}%")
                        ->orWhere('brand', 'like', "%{$q}%")
                        ->orWhereHas('aliases', fn ($a) => $a->where('reference_number', 'like', "%{$q}%"));
                });
            })
            ->when($request->query('filter') === 'low', fn ($query) => $query->whereColumn('stock_qty', '<=', 'reorder_level'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('products.index', compact('products', 'q'));
    }

    public function create()
    {
        return view('products.form', [
            'product' => new Product(),
            'categories' => Category::orderBy('name')->get(),
            'brands' => CarBrand::with('models')->orderBy('name')->get(),
            'partBrands' => $this->partBrands(),
        ]);
    }

    public function store(Request $request, StockService $stock, AuditService $audit)
    {
        $data = $this->validated($request);
        $data['primary_part_number'] = strtoupper(trim($data['primary_part_number']));
        $data['secret_cost_code'] = strtoupper(trim($data['secret_cost_code']));
        $data['cost_pending'] = ! $request->user()->canViewCost();

        $product = DB::transaction(function () use ($data, $request, $stock, $audit) {
            $product = Product::create($data);
            if ((float) $request->input('stock_qty', 0) > 0 && $product->track_stock) {
                $stock->apply($product->fresh(), $request->user(), 'opening', (float) $request->input('stock_qty'), 0, (float) $product->cost_price, 'opening', null, 'Opening stock');
            }
            $audit->log('product.created', $product, ['part' => $product->primary_part_number, 'price' => $product->selling_price], $request->user());

            return $product;
        });

        return redirect()->route('products.edit', $product)->with('success', 'Product created. Add vehicle fitments and cross-references below.');
    }

    public function edit(Product $product)
    {
        $product->load(['fitments.brand', 'fitments.model', 'aliases', 'category']);

        return view('products.form', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(),
            'brands' => CarBrand::with('models')->orderBy('name')->get(),
            'partBrands' => $this->partBrands(),
        ]);
    }

    public function update(Request $request, Product $product, AuditService $audit)
    {
        $data = $this->validated($request, $product);
        $data['primary_part_number'] = strtoupper(trim($data['primary_part_number']));
        $data['secret_cost_code'] = strtoupper(trim($data['secret_cost_code']));

        $old = $product->only(['selling_price', 'cost_price', 'secret_cost_code', 'primary_part_number']);
        $product->update($data);
        $audit->log('product.updated', $product, ['old' => $old, 'new' => $product->only(array_keys($old))], $request->user());

        return redirect()->route('products.edit', $product)->with('success', 'Product updated.');
    }

    public function storeFitment(Request $request, Product $product)
    {
        $data = $request->validate([
            'vehicle_brand' => ['required', 'string', 'max:60'],
            'vehicle_model' => ['required', 'string', 'max:80'],
            'year_from' => ['required', 'integer', 'between:1950,2100'],
            'year_to' => ['required', 'integer', 'between:1950,2100', 'gte:year_from'],
            'engine' => ['nullable', 'string', 'max:50'],
            'mapped_part_number' => ['nullable', 'string', 'max:50'],
        ]);

        $brand = CarBrand::findOrCreateByName($data['vehicle_brand']);
        $model = CarModel::findOrCreateForBrand($brand, $data['vehicle_model']);

        $product->fitments()->create([
            'car_brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'year_from' => $data['year_from'],
            'year_to' => $data['year_to'],
            'engine' => $data['engine'] ?? null,
            'mapped_part_number' => $data['mapped_part_number'] ?? null,
        ]);

        return back()->with('success', 'Fitment added. '.$brand->name.' '.$model->name.' is now saved for future suggestions.');
    }

    public function destroyFitment(Product $product, \App\Models\ProductFitment $fitment)
    {
        abort_unless($fitment->product_id === $product->id, 404);
        $fitment->delete();

        return back()->with('success', 'Fitment removed.');
    }

    public function storeAlias(Request $request, Product $product)
    {
        $data = $request->validate([
            'reference_type' => ['required', 'in:oem,manufacturer,aftermarket,competitor,superseded,alias'],
            'reference_number' => ['required', 'string', 'max:50'],
            'brand' => ['nullable', 'string', 'max:80'],
        ]);
        $product->aliases()->create($data);

        return back()->with('success', 'Cross-reference added.');
    }

    public function destroyAlias(Product $product, \App\Models\ProductAlias $alias)
    {
        abort_unless($alias->product_id === $product->id, 404);
        $alias->delete();

        return back()->with('success', 'Cross-reference removed.');
    }

    private function partBrands()
    {
        return Product::query()
            ->whereNotNull('brand')
            ->where('brand', '<>', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'primary_part_number' => ['required', 'string', 'max:50', Rule::unique('products')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:150'],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:80'],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products')->ignore($product?->id)],
            'unit' => ['required', 'string', 'max:20'],
            'tax_rate' => ['required', 'numeric', 'between:0,100'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'secret_cost_code' => ['required', 'string', 'min:2', 'max:20', Rule::unique('products')->ignore($product?->id)],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'bin_location' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'track_stock' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['track_stock'] = $request->boolean('track_stock', true);
        $data['reorder_level'] = $data['reorder_level'] ?? 0;

        if ($request->user()->canViewCost()) {
            $validated = $request->validate(['cost_price' => ['required', 'numeric', 'min:0', 'max:9999999']]);
            $data['cost_price'] = $validated['cost_price'];
        } elseif (! $product) {
            $data['cost_price'] = 0;
        }

        return $data;
    }
}
