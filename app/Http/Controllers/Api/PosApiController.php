<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PosHold;
use App\Models\Product;
use App\Models\Shift;
use App\Services\AuditService;
use App\Services\PricingService;
use App\Services\SaleService;
use App\Services\SearchService;
use App\Services\ShiftService;
use App\Services\StockService;
use App\Services\VehicleVariantService;
use App\Support\Pos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosApiController extends Controller
{
    public function search(Request $request, SearchService $search)
    {
        $term = (string) $request->query('q', '');
        $products = $search->search($term, 24);

        return response()->json([
            'data' => $products->map(fn (Product $product) => $product->toPosArray($request->user())),
        ]);
    }

    public function customerSearch(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $customers = Customer::query()
            ->where('is_active', true)
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('company_name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('trn', 'like', "%{$q}%");
                });
            })
            ->orderBy('company_name')
            ->limit(12)
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'company_name' => $customer->company_name,
                'phone' => $customer->phone,
                'trn' => $customer->trn,
                'credit_limit' => (float) $customer->credit_limit,
                'outstanding' => $customer->outstandingBalance(),
            ]);

        return response()->json(['data' => $customers]);
    }

    public function quickAdd(Request $request, StockService $stock, AuditService $audit, VehicleVariantService $variants)
    {
        abort_unless($request->user()->canSell(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'primary_part_number' => ['required', 'string', 'max:50', 'unique:products,primary_part_number'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'secret_cost_code' => ['required', 'string', 'min:2', 'max:20', 'unique:products,secret_cost_code'],
            'stock_qty' => ['nullable', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:80'],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:products,barcode'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'vehicle_brand' => ['nullable', 'string', 'max:60', 'required_with:vehicle_model'],
            'vehicle_model' => ['nullable', 'string', 'max:80', 'required_with:vehicle_brand'],
            'variants' => ['nullable', 'array'],
            'year_from' => ['nullable', 'integer', 'between:1950,2100'],
            'year_to' => ['nullable', 'integer', 'between:1950,2100', 'gte:year_from'],
            'mapped_part_number' => ['nullable', 'string', 'max:50'],
        ]);

        $product = DB::transaction(function () use ($request, $data, $stock, $audit, $variants) {
            $canViewCost = $request->user()->canViewCost();
            $cost = $canViewCost ? (float) ($data['cost_price'] ?? 0) : 0.0;

            $product = Product::create([
                'primary_part_number' => strtoupper(trim($data['primary_part_number'])),
                'name' => $data['name'],
                'selling_price' => $data['selling_price'],
                'cost_price' => $cost,
                'secret_cost_code' => strtoupper(trim($data['secret_cost_code'])),
                'stock_qty' => 0,
                'category_id' => $data['category_id'] ?? Category::firstOrCreate(['name' => 'Uncategorised'])->id,
                'brand' => $data['brand'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'cost_pending' => ! $canViewCost || (float) ($data['cost_price'] ?? 0) <= 0,
                'is_active' => true,
                'track_stock' => true,
            ]);

            $variantRows = $data['variants'] ?? [];
            if (! empty($data['vehicle_brand']) && ! empty($data['vehicle_model'])) {
                $variantRows[] = [
                    'vehicle_brand' => $data['vehicle_brand'],
                    'vehicle_model' => $data['vehicle_model'],
                    'year_from' => $data['year_from'] ?? 2000,
                    'year_to' => $data['year_to'] ?? now()->year,
                    'mapped_part_number' => $data['mapped_part_number'] ?? null,
                ];
            }
            $variants->createMany($product, $variantRows);

            if ((float) ($data['stock_qty'] ?? 0) > 0) {
                $stock->apply($product->fresh(), $request->user(), 'opening', (float) $data['stock_qty'], 0, $cost, 'quick_add', $product->id, 'Quick Add opening stock');
            }

            $audit->log('product.quick_added', $product, [
                'part' => $product->primary_part_number,
                'cost_pending' => $product->cost_pending,
            ], $request->user());

            return $product->fresh(['category', 'fitments.brand', 'fitments.model']);
        });

        return response()->json(['product' => $product->toPosArray($request->user())], 201);
    }

    public function openShift(Request $request, ShiftService $shifts)
    {
        abort_unless($request->user()->canSell(), 403);
        $data = $request->validate(['opening_float' => ['required', 'numeric', 'min:0']]);
        $shift = $shifts->open($request->user(), (float) $data['opening_float']);

        return response()->json(['shift' => ['id' => $shift->id, 'opening_float' => (float) $shift->opening_float]]);
    }

    public function closeShift(Request $request, ShiftService $shifts, AuditService $audit)
    {
        abort_unless($request->user()->canSell(), 403);
        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $shift = Shift::openFor($request->user());
        if (! $shift) {
            throw ValidationException::withMessages(['shift' => 'No open shift found.']);
        }

        $shift = $shifts->close($shift, (float) $data['counted_cash'], $data['notes'] ?? null);
        $audit->log('shift.closed', $shift, ['variance' => $shift->variance, 'counted' => $shift->counted_cash], $request->user());

        return response()->json(['shift' => [
            'id' => $shift->id,
            'expected_cash' => (float) $shift->expected_cash,
            'counted_cash' => (float) $shift->counted_cash,
            'variance' => (float) $shift->variance,
        ]]);
    }

    public function holds(Request $request)
    {
        abort_unless($request->user()->canSell(), 403);
        $holds = PosHold::with('customer')
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->latest()
            ->get()
            ->map(fn (PosHold $hold) => [
                'id' => $hold->id,
                'reference' => $hold->reference,
                'note' => $hold->note,
                'customer' => $hold->customer?->company_name ?: $hold->customer?->name,
                'items' => count($hold->cart_json['items'] ?? []),
                'created_at' => $hold->created_at->diffForHumans(),
            ]);

        return response()->json(['data' => $holds]);
    }

    public function hold(Request $request)
    {
        abort_unless($request->user()->canSell(), 403);
        $data = $request->validate([
            'cart' => ['required', 'array'],
            'cart.items' => ['required', 'array', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'exists:customers,id'],
        ]);

        $hold = PosHold::create([
            'reference' => 'HOLD-'.now()->format('ymd').'-'.strtoupper(Str::random(4)),
            'user_id' => $request->user()->id,
            'customer_id' => $data['customer_id'] ?? null,
            'cart_json' => $data['cart'],
            'note' => $data['note'] ?? null,
            'status' => 'open',
            'expires_at' => now()->addDay(),
        ]);

        return response()->json(['hold' => ['id' => $hold->id, 'reference' => $hold->reference]]);
    }

    public function resumeHold(Request $request, PosHold $hold)
    {
        abort_unless($hold->user_id === $request->user()->id || $request->user()->canApprove(), 403);
        abort_unless($hold->status === 'open', 422, 'This hold is no longer available.');
        $hold->update(['status' => 'resumed']);
        $customer = $hold->customer;

        return response()->json([
            'cart' => $hold->cart_json,
            'customer_id' => $hold->customer_id,
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'company_name' => $customer->company_name,
                'phone' => $customer->phone,
                'trn' => $customer->trn,
                'credit_limit' => (float) $customer->credit_limit,
                'outstanding' => $customer->outstandingBalance(),
            ] : null,
        ]);
    }

    public function discardHold(Request $request, PosHold $hold)
    {
        abort_unless($hold->user_id === $request->user()->id || $request->user()->canApprove(), 403);
        $hold->update(['status' => 'discarded']);

        return response()->json(['ok' => true]);
    }

    public function sale(Request $request, SaleService $sales)
    {
        abort_unless($request->user()->canSell(), 403);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:none,amount,percent'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.override_reason' => ['nullable', 'string', 'max:255'],
            'items.*.variant_label' => ['nullable', 'string', 'max:150'],
            'items.*.variant_sku' => ['nullable', 'string', 'max:50'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:cash,card,credit,bank_transfer'],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'payments.*.tendered' => ['nullable', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'invoice_discount_type' => ['nullable', 'in:none,amount,percent'],
            'invoice_discount_value' => ['nullable', 'numeric', 'min:0'],
            'approval_pin' => ['nullable', 'string', 'max:20'],
            'vehicle' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $sale = $sales->complete($request->user(), $data);

        return response()->json([
            'sale' => [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'total' => (float) $sale->total,
                'net_total' => (float) $sale->net_total,
                'vat_total' => (float) $sale->vat_total,
                'discount_total' => (float) $sale->discount_total,
                'paid_total' => (float) $sale->paid_total,
                'change_total' => (float) $sale->change_total,
                'balance_due' => (float) $sale->balance_due,
                'payment_status' => $sale->payment_status,
                'invoice_url' => route('sales.invoice', $sale),
                'receipt_url' => route('sales.receipt', $sale),
            ],
        ], 201);
    }

    public function totals(Request $request, PricingService $pricing)
    {
        abort_unless($request->user()->canSell(), 403);
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:none,amount,percent'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'invoice_discount_type' => ['nullable', 'in:none,amount,percent'],
            'invoice_discount_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $products = Product::whereIn('id', collect($data['items'])->pluck('product_id'))->get()->keyBy('id');
        $lines = collect($data['items'])->map(function ($item) use ($products) {
            $product = $products->get($item['product_id']);
            return $item + [
                'primary_part_number' => $product->primary_part_number,
                'name' => $product->name,
                'tax_rate' => $product->tax_rate,
                'cost_price' => $product->cost_price,
                'original_unit_price' => $product->selling_price,
            ];
        })->all();

        $totals = $pricing->calculate(
            $lines,
            $data['invoice_discount_type'] ?? 'none',
            $data['invoice_discount_value'] ?? 0,
            Pos::pricesIncludeVat(),
            Pos::vatRate(),
        );

        // This endpoint is used by cashier terminals. Never return cost prices.
        $totals['lines'] = collect($totals['lines'])->map(function ($line) {
            unset($line['cost_price']);
            return $line;
        })->all();

        return response()->json($totals);
    }
}
