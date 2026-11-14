<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockReceipt;
use App\Services\BulkStockReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StockReceiptController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);
        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $receipts = StockReceipt::with('user')->withCount('items')->whereDate('received_on', '>=', $from)->whereDate('received_on', '<=', $to)->orderByDesc('received_on')->latest('id')->paginate(25)->withQueryString();

        return view('inventory.receipts.index', compact('receipts', 'from', 'to'));
    }

    public function create()
    {
        $products = Product::active()->where('track_stock', true)->with(['fitments.brand', 'fitments.model'])->orderBy('name')->get();
        $requestKey = (string) Str::uuid();

        return view('inventory.receipts.create', compact('products', 'requestKey'));
    }

    public function store(Request $request, BulkStockReceiptService $service)
    {
        $data = $request->validate([
            'received_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'request_key' => ['required', 'uuid'], 'reference' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_fitment_id' => ['nullable', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:999999999.999'],
            'items.*.unit_cost' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ]);
        $receipt = $service->receive($request->user(), $data);

        return redirect()->route('inventory.receipts.show', $receipt)->with('success', 'Selected stock received successfully.');
    }

    public function show(StockReceipt $stockReceipt)
    {
        $stockReceipt->load(['items.product', 'items.fitment.brand', 'items.fitment.model', 'user']);

        return view('inventory.receipts.show', ['receipt' => $stockReceipt]);
    }
}
