<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PosHold;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Services\AuditService;
use App\Services\PricingService;
use App\Services\SaleService;
use App\Services\SearchService;
use App\Services\ShiftService;
use App\Services\StockService;
use App\Services\VehicleVariantService;
use App\Services\VoidSaleService;
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
                        ->orWhere('car_plate_number', 'like', "%{$q}%")
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
                'car_plate_number' => $customer->car_plate_number,
                'credit_limit' => (float) $customer->credit_limit,
                'outstanding' => $customer->outstandingBalance(),
            ]);

        return response()->json(['data' => $customers]);
    }


    public function storeCustomer(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'car_plate_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'trn' => ['nullable', 'digits:15', 'unique:customers,trn'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = Customer::create([
            'name' => $data['name'],
            'company_name' => $data['company_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'car_plate_number' => $data['car_plate_number'] ?? null,
            'email' => $data['email'] ?? null,
            'trn' => $data['trn'] ?? null,
            'address' => $data['address'] ?? null,
            'credit_limit' => 0,
            'payment_terms_days' => 0,
            'is_active' => true,
        ]);

        $audit->log('customer.created_from_pos', $customer, ['name' => $customer->name], $request->user());

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->company_name ?: $customer->name,
                'contact' => $customer->company_name ? $customer->name : null,
                'trn' => $customer->trn,
                'car_plate_number' => $customer->car_plate_number,
                'credit_limit' => 0,
                'outstanding' => 0,
            ],
        ], 201);
    }

    public function quickAdd(Request $request, StockService $stock, AuditService $audit, VehicleVariantService $variants)
    {
        abort_unless($request->user()->canSell() || $request->user()->canManageCatalog(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'primary_part_number' => ['nullable', 'string', 'max:50', 'unique:products,primary_part_number'],
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
            'variants.*.vehicle_brand' => ['required', 'string', 'max:60'],
            'variants.*.vehicle_model' => ['required', 'string', 'max:80'],
            'variants.*.year_from' => ['nullable', 'integer', 'between:1950,2100'],
            'variants.*.year_to' => ['nullable', 'integer', 'between:1950,2100'],
            'variants.*.mapped_part_number' => ['nullable', 'string', 'max:50'],
            'variants.*.cost_price' => ['required', 'numeric', 'min:0'],
            'variants.*.stock_qty' => ['required', 'numeric', 'min:0'],
            'variants.*.category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'variants.*.category_name' => ['nullable', 'string', 'max:100'],
            'year_from' => ['nullable', 'integer', 'between:1950,2100'],
            'year_to' => ['nullable', 'integer', 'between:1950,2100', 'gte:year_from'],
            'mapped_part_number' => ['nullable', 'string', 'max:50'],
        ]);

        $product = DB::transaction(function () use ($request, $data, $stock, $audit, $variants) {
            $canViewCost = $request->user()->canViewCost();
            $cost = $canViewCost ? (float) ($data['cost_price'] ?? 0) : 0.0;

            $product = Product::create([
                'primary_part_number' => (strtoupper(trim((string) ($data['primary_part_number'] ?? ''))) ?: null),
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

            $variantStock = (float) $product->fitments()->whereNotNull('stock_qty')->sum('stock_qty');
            $openingStock = $variantStock > 0 ? $variantStock : (float) ($data['stock_qty'] ?? 0);
            if ($openingStock > 0) {
                $stock->apply($product->fresh(), $request->user(), 'opening', $openingStock, 0, $cost, 'quick_add', $product->id, 'Quick Add opening stock');
            }

            $audit->log('product.quick_added', $product, [
                'part' => $product->primary_part_number,
                'cost_pending' => $product->cost_pending,
            ], $request->user());

            return $product->fresh(['category', 'fitments.brand', 'fitments.model']);
        });

        return response()->json(['product' => $product->toPosArray($request->user())], 201);
    }


    public function addVariants(Request $request, Product $product, VehicleVariantService $variants, StockService $stock)
    {
        abort_unless($request->user()->canSell(), 403);
        abort_unless($product->is_active, 422, 'This product is not active.');

        $data = $request->validate([
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.vehicle_brand' => ['required', 'string', 'max:60'],
            'variants.*.vehicle_model' => ['required', 'string', 'max:80'],
            'variants.*.year_from' => ['nullable', 'integer', 'between:1950,2100'],
            'variants.*.year_to' => ['nullable', 'integer', 'between:1950,2100'],
            'variants.*.mapped_part_number' => ['nullable', 'string', 'max:50'],
            'variants.*.cost_price' => ['required', 'numeric', 'min:0'],
            'variants.*.stock_qty' => ['required', 'numeric', 'min:0'],
            'variants.*.category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'variants.*.category_name' => ['nullable', 'string', 'max:100'],
        ]);

        $beforeStock = (float) $product->fitments()->whereNotNull('stock_qty')->sum('stock_qty');
        $variants->createMany($product, $data['variants']);
        $afterStock = (float) $product->fitments()->whereNotNull('stock_qty')->sum('stock_qty');
        if ($afterStock > $beforeStock) {
            $stock->apply($product, $request->user(), 'opening', $afterStock - $beforeStock, 0, (float) $product->cost_price, 'variant_stock', null, 'New variant opening stock');
        }

        $fitments = $product->fitments()
            ->with(['brand', 'model', 'category'])
            ->get()
            ->map(fn ($fitment) => [
                'id' => $fitment->id,
                'label' => trim(($fitment->brand?->name ?? '').' '.($fitment->model?->name ?? '').' '.$fitment->year_from.'–'.$fitment->year_to),
                'brand' => $fitment->brand?->name,
                'model' => $fitment->model?->name,
                'year_from' => $fitment->year_from,
                'year_to' => $fitment->year_to,
                'category_id' => $fitment->category_id,
                'category_name' => $fitment->category?->name,
                'mapped_part_number' => $fitment->mapped_part_number,
                'stock_qty' => $fitment->stock_qty === null ? null : (float) $fitment->stock_qty,
            ]);

        return response()->json(['data' => $fitments]);
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
                'car_plate_number' => $customer->car_plate_number,
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


    public function recentSales(Request $request)
    {
        $sales = Sale::query()
            ->with(['customer', 'user'])
            ->withCount(['items', 'returns', 'collections'])
            ->when($request->user()->isCashier(), fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest('sold_at')
            ->limit(8)
            ->get()
            ->map(function (Sale $sale) {
                $canVoid = $sale->status === 'completed' && $sale->returns_count === 0 && $sale->collections_count === 0;

                return [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'sold_at' => $sale->sold_at->toIso8601String(),
                    'time' => $sale->sold_at->format('H:i'),
                    'date' => $sale->sold_at->format('d M'),
                    'customer_name' => $sale->customer_name,
                    'customer_id' => $sale->customer_id,
                    'items_count' => $sale->items_count,
                    'total' => (float) $sale->total,
                    'paid_total' => (float) $sale->paid_total,
                    'balance_due' => (float) $sale->balance_due,
                    'payment_status' => $sale->payment_status,
                    'status' => $sale->status,
                    'vehicle' => $sale->vehicle,
                    'notes' => $sale->notes,
                    'invoice_url' => route('sales.invoice', $sale),
                    'receipt_url' => route('sales.receipt', $sale),
                    'sale_url' => route('sales.show', $sale),
                    'can_edit' => $sale->status !== 'voided',
                    'can_void' => $canVoid,
                ];
            });

        return response()->json(['data' => $sales]);
    }

    public function updateSale(Request $request, Sale $sale, AuditService $audit)
    {
        abort_unless($request->user()->canApprove() || $sale->user_id === $request->user()->id, 403);
        abort_if($sale->status === 'voided', 422, 'A voided invoice cannot be edited.');

        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'vehicle' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldCustomer = $sale->customer_id;
        if ((int) ($data['customer_id'] ?? 0) !== (int) $oldCustomer) {
            if ((float) $sale->balance_due > 0 || $sale->collections()->exists()) {
                throw ValidationException::withMessages(['customer_id' => 'Customer cannot be changed after a credit sale or collection. Edit only the vehicle or note.']);
            }

            $customer = ! empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;
            $sale->customer_id = $customer?->id;
            $sale->customer_name = $customer ? ($customer->company_name ?: $customer->name) : 'Walk-in Customer';
            $sale->customer_trn = $customer?->trn;
        }

        $sale->vehicle = $data['vehicle'] ?? null;
        $sale->notes = $data['notes'] ?? null;
        $sale->save();

        $audit->log('sale.updated_from_pos', $sale, [
            'customer_id' => $sale->customer_id,
            'vehicle' => $sale->vehicle,
        ], $request->user());

        return response()->json(['sale' => ['id' => $sale->id, 'invoice_number' => $sale->invoice_number]]);
    }

    public function voidSale(Request $request, Sale $sale, VoidSaleService $voids)
    {
        abort_unless($request->user()->canApprove() || $sale->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'approval_pin' => ['nullable', 'string', 'max:20'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $voided = $voids->void($request->user(), $sale, $data['approval_pin'] ?? null, $data['reason'] ?? null);

        return response()->json([
            'sale' => [
                'id' => $voided->id,
                'invoice_number' => $voided->invoice_number,
                'status' => $voided->status,
                'balance_due' => (float) $voided->balance_due,
            ],
        ]);
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
            'items.*.product_fitment_id' => ['nullable', 'integer', 'min:1'],
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
            'person_name' => ['nullable', 'string', 'max:150'],
            'car_plate_number' => ['nullable', 'string', 'max:50'],
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
