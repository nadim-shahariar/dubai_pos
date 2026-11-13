<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductFitment;
use App\Models\StockMovement;
use App\Services\AuditService;
use App\Services\StockService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $filter = $request->query('filter');
        $products = Product::query()
            ->with(['category', 'fitments.brand', 'fitments.model'])
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('primary_part_number', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('bin_location', 'like', "%{$q}%");
            }))
            ->when($filter === 'low', fn ($query) => $query->whereColumn('stock_qty', '<=', 'reorder_level'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $totalUnits = (float) Product::where('track_stock', true)->sum('stock_qty');
        $totalValue = (float) Product::where('track_stock', true)->sum(\DB::raw('stock_qty * cost_price'));
        $lowCount = Product::where('track_stock', true)->whereColumn('stock_qty', '<=', 'reorder_level')->count();

        return view('inventory.index', compact('products', 'q', 'filter', 'totalUnits', 'totalValue', 'lowCount'));
    }

    public function show(Product $product)
    {
        $product->load(['category', 'fitments.brand', 'fitments.model']);
        $movements = $product->stockMovements()->with(['user', 'fitment'])->latest('created_at')->paginate(20);

        return view('inventory.show', compact('product', 'movements'));
    }

    public function adjust(Request $request, Product $product, StockService $stock, AuditService $audit)
    {
        $data = $request->validate([
            'new_qty' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        \DB::transaction(function () use ($product, $data, $request, $stock, $audit) {
            $locked = Product::lockForUpdate()->findOrFail($product->id);
            if ($locked->fitments()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['new_qty' => 'Adjust the counted stock of the individual variant instead.']);
            }
            $before = (float) $locked->stock_qty;
            $stock->adjust($locked, $request->user(), (float) $data['new_qty'], $data['note'] ?? 'Stock adjustment');
            $audit->log('stock.adjusted', $locked, ['before' => $before, 'after' => (float) $data['new_qty'], 'note' => $data['note'] ?? null], $request->user());
        });

        return back()->with('success', 'Stock adjusted.');
    }

    public function receive(Request $request, Product $product, StockService $stock, AuditService $audit)
    {
        $data = $request->validate([
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'product_fitment_id' => ['nullable', 'integer'],
        ]);

        \DB::transaction(function () use ($product, $data, $request, $stock, $audit) {
            $locked = Product::lockForUpdate()->findOrFail($product->id);
            $before = (float) $locked->stock_qty;
            $beforeCost = (float) $locked->cost_price;
            $fitment = null;
            if (! empty($data['product_fitment_id'])) {
                $fitment = $locked->fitments()->whereKey($data['product_fitment_id'])->lockForUpdate()->first();
                if (! $fitment) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['product_fitment_id' => 'Choose a variant belonging to this product.']);
                }
                $fitment->stock_qty = $fitment->stock_qty ?? 0;
            }
            if (! $fitment && $locked->fitments()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['product_fitment_id' => 'Select the variant receiving stock.']);
            }
            $stock->receive($locked, $request->user(), (float) $data['qty'], (float) $data['unit_cost'], $data['reference'] ?? null, $fitment);
            $audit->log('stock.received', $locked, [
                'before_qty' => $before,
                'received' => (float) $data['qty'],
                'before_cost' => $beforeCost,
                'new_cost' => (float) $locked->cost_price,
                'reference' => $data['reference'] ?? null,
            ], $request->user());
        });

        return back()->with('success', 'Stock received and average cost updated.');
    }

    public function variantStock(Request $request, Product $product, ProductFitment $fitment, StockService $stock, AuditService $audit)
    {
        abort_unless($fitment->product_id === $product->id, 404);

        $data = $request->validate([
            'stock_qty' => ['required', 'numeric', 'min:0'],
        ]);

        \DB::transaction(function () use ($product, $fitment, $data, $request, $stock, $audit) {
            $lockedProduct = Product::lockForUpdate()->findOrFail($product->id);
            $lockedFitment = ProductFitment::lockForUpdate()->findOrFail($fitment->id);
            $before = (float) ($lockedFitment->stock_qty ?? 0);
            // A null quantity means shared stock; initialize it before posting the variant movement.
            $lockedFitment->stock_qty = $before;
            $target = round((float) $data['stock_qty'], 3);
            $delta = round($target - $before, 3);

            if (abs($delta) <= 0.0001) {
                $lockedFitment->stock_qty = $target;
                $lockedFitment->save();
            } else {
                $stock->apply(
                    $lockedProduct,
                    $request->user(),
                    $delta > 0 ? 'adjustment_in' : 'adjustment_out',
                    $delta > 0 ? $delta : 0,
                    $delta < 0 ? abs($delta) : 0,
                    (float) ($lockedFitment->cost_price ?? $lockedProduct->cost_price),
                    'variant_stock',
                    null,
                    'Variant stock set to '.number_format($target, 3),
                    $lockedFitment,
                );
            }

            $audit->log('variant_stock.updated', $lockedFitment, [
                'before' => $before,
                'after' => $target,
                'part_number' => $lockedFitment->mapped_part_number,
            ], $request->user());
        });

        return back()->with('success', 'Variant stock updated.');
    }

    public function movements(Request $request)
    {
        $movements = StockMovement::with(['product', 'user', 'fitment'])
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->latest('created_at')
            ->paginate(30);

        return view('inventory.movements', compact('movements'));
    }
}
