<?php

namespace App\Http\Controllers;

use App\Models\Product;
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
            ->with('category')
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
        $movements = $product->stockMovements()->with('user')->latest('created_at')->paginate(20);

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
        ]);

        \DB::transaction(function () use ($product, $data, $request, $stock, $audit) {
            $locked = Product::lockForUpdate()->findOrFail($product->id);
            $before = (float) $locked->stock_qty;
            $beforeCost = (float) $locked->cost_price;
            $stock->receive($locked, $request->user(), (float) $data['qty'], (float) $data['unit_cost'], $data['reference'] ?? null);
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

    public function movements(Request $request)
    {
        $movements = StockMovement::with(['product', 'user'])
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->latest('created_at')
            ->paginate(30);

        return view('inventory.movements', compact('movements'));
    }
}
