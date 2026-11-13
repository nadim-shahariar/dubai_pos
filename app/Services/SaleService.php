<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Support\InvoiceNumber;
use App\Support\Money;
use App\Support\Pos;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly ApprovalService $approval,
        private readonly StockService $stock,
        private readonly AuditService $audit,
    ) {
    }

    public function complete(\App\Models\User $user, array $data): Sale
    {
        if (! $user->canSell()) {
            throw ValidationException::withMessages(['sale' => 'Your role cannot create sales.']);
        }

        $shift = Shift::openFor($user);
        if (! $shift) {
            throw ValidationException::withMessages(['shift' => 'Open a shift before starting a sale.']);
        }

        $items = $data['items'] ?? [];
        if (! is_array($items) || count($items) === 0) {
            throw ValidationException::withMessages(['items' => 'Add at least one item to the sale.']);
        }

        $customer = null;
        if (! empty($data['customer_id'])) {
            $customer = Customer::find($data['customer_id']);
        }

        return DB::transaction(function () use ($user, $data, $items, $shift, $customer) {
            $productIds = collect($items)->pluck('product_id')->filter()->unique()->values();
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $requested = [];
            $lines = [];

            foreach ($items as $item) {
                $product = $products->get($item['product_id'] ?? null);
                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages(['items' => 'One of the selected products is no longer available.']);
                }

                $qty = round((float) ($item['qty'] ?? 0), 3);
                if ($qty <= 0) {
                    throw ValidationException::withMessages(['items' => 'Quantity must be greater than zero.']);
                }

                $unitPrice = round((float) ($item['unit_price'] ?? $product->selling_price), 2);
                if ($unitPrice < 0) {
                    throw ValidationException::withMessages(['items' => 'Unit price cannot be negative.']);
                }

                $originalPrice = (float) $product->selling_price;
                $isOverride = abs($unitPrice - $originalPrice) >= 0.01;
                if ($isOverride && empty($item['override_reason'])) {
                    throw ValidationException::withMessages(['items' => 'A reason is required when overriding a price.']);
                }

                if ($product->track_stock) {
                    $requested[$product->id] = ($requested[$product->id] ?? 0) + $qty;
                }

                $variantCost = null;
                if (! empty($item['variant_sku'])) {
                    $variantCost = $product->fitments()
                        ->where('mapped_part_number', $item['variant_sku'])
                        ->value('cost_price');
                }

                $lines[] = [
                    'product_id' => $product->id,
                    'primary_part_number' => $product->primary_part_number,
                    'name' => $product->name,
                    'variant_label' => $item['variant_label'] ?? null,
                    'variant_sku' => $item['variant_sku'] ?? null,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'original_unit_price' => $originalPrice,
                    'discount_type' => $item['discount_type'] ?? 'none',
                    'discount_value' => (float) ($item['discount_value'] ?? 0),
                    'tax_rate' => (float) $product->tax_rate,
                    'cost_price' => $variantCost !== null ? (float) $variantCost : (float) $product->cost_price,
                    'price_override' => $isOverride,
                    'override_reason' => $item['override_reason'] ?? null,
                ];
            }

            foreach ($requested as $productId => $qty) {
                $product = $products->get($productId);
                if ((float) $product->stock_qty + 0.0001 < $qty) {
                    throw ValidationException::withMessages([
                        'stock' => "Not enough stock for {$product->primary_part_number}. Available: ".number_format((float) $product->stock_qty, 3).', requested: '.number_format($qty, 3).'.',
                    ]);
                }
            }

            $pricing = $this->pricing->calculate(
                $lines,
                $data['invoice_discount_type'] ?? 'none',
                (float) ($data['invoice_discount_value'] ?? 0),
                Pos::pricesIncludeVat(),
                Pos::vatRate(),
            );

            $discountBase = max(0, $pricing['subtotal'] - $pricing['line_discount_total']);
            $invoiceDiscountPercent = $discountBase > 0
                ? ($pricing['invoice_discount_total'] / $discountBase) * 100
                : 0;

            $approver = $this->approval->authorise(
                $user,
                $pricing['lines'],
                $invoiceDiscountPercent,
                $data['approval_pin'] ?? null,
            );

            $payments = collect($data['payments'] ?? [])
                ->map(fn ($payment) => [
                    'method' => (string) ($payment['method'] ?? 'cash'),
                    'amount' => round((float) ($payment['amount'] ?? 0), 2),
                    'tendered' => isset($payment['tendered']) ? round((float) $payment['tendered'], 2) : null,
                    'reference' => $payment['reference'] ?? null,
                ])
                ->filter(fn ($payment) => $payment['amount'] > 0 || ($payment['tendered'] ?? 0) > 0)
                ->values();

            if ($payments->isEmpty()) {
                throw ValidationException::withMessages(['payments' => 'At least one payment is required.']);
            }

            $allowedMethods = ['cash', 'card', 'credit', 'bank_transfer'];
            $paymentCents = 0;
            $creditCents = 0;
            $paidCents = 0;
            $changeCents = 0;

            foreach ($payments as $payment) {
                if (! in_array($payment['method'], $allowedMethods, true)) {
                    throw ValidationException::withMessages(['payments' => 'Unsupported payment method.']);
                }
                if ($payment['amount'] <= 0) {
                    throw ValidationException::withMessages(['payments' => 'Payment amounts must be greater than zero.']);
                }
                if ($payment['method'] === 'cash' && $payment['tendered'] !== null && $payment['tendered'] < $payment['amount']) {
                    throw ValidationException::withMessages(['payments' => 'Cash tendered cannot be less than the cash applied.']);
                }

                $cents = Money::cents($payment['amount']);
                $paymentCents += $cents;

                if ($payment['method'] === 'credit') {
                    $creditCents += $cents;
                } else {
                    $paidCents += $cents;
                }

                if ($payment['method'] === 'cash' && $payment['tendered'] !== null) {
                    $changeCents += max(0, Money::cents($payment['tendered']) - $cents);
                }
            }

            if (abs($paymentCents - Money::cents($pricing['total'])) > 1) {
                throw ValidationException::withMessages(['payments' => 'Payments must equal the sale total.']);
            }

            if ($creditCents > 0) {
                if (! $customer) {
                    throw ValidationException::withMessages(['customer_id' => 'Select a registered customer for a credit sale.']);
                }
                $outstanding = $customer->outstandingBalance();
                $newBalance = $outstanding + Money::decimal($creditCents);
                if ((float) $customer->credit_limit <= 0 || $newBalance > (float) $customer->credit_limit + 0.001) {
                    throw ValidationException::withMessages([
                        'customer_id' => 'Credit limit exceeded. Outstanding AED '.number_format($outstanding, 2).', limit AED '.number_format((float) $customer->credit_limit, 2).'.',
                    ]);
                }
            }

            $invoiceNumber = InvoiceNumber::next('invoice');

            $sale = Sale::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $customer?->id,
                'user_id' => $user->id,
                'shift_id' => $shift->id,
                'approved_by' => $approver?->id,
                'status' => 'completed',
                'payment_status' => $creditCents > 0
                    ? ($paidCents > 0 ? 'partial' : 'credit')
                    : 'paid',
                'price_mode' => $pricing['price_mode'],
                'subtotal' => $pricing['subtotal'],
                'line_discount_total' => $pricing['line_discount_total'],
                'invoice_discount_total' => $pricing['invoice_discount_total'],
                'discount_total' => $pricing['discount_total'],
                'net_total' => $pricing['net_total'],
                'vat_total' => $pricing['vat_total'],
                'total' => $pricing['total'],
                'paid_total' => Money::decimal($paidCents),
                'change_total' => Money::decimal($changeCents),
                'balance_due' => Money::decimal($creditCents),
                'customer_name' => $customer ? ($customer->company_name ?: $customer->name) : 'Walk-in Customer',
                'customer_trn' => $customer?->trn,
                'vehicle' => $data['vehicle'] ?? null,
                'notes' => $data['notes'] ?? null,
                'sold_at' => now(),
            ]);

            foreach ($pricing['lines'] as $line) {
                $sale->items()->create([
                    'product_id' => $line['product_id'],
                    'primary_part_number' => $line['primary_part_number'],
                    'name' => $line['name'],
                    'variant_label' => $line['variant_label'] ?? null,
                    'variant_sku' => $line['variant_sku'] ?? null,
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'original_unit_price' => $line['original_unit_price'],
                    'discount_type' => $line['discount_type'],
                    'discount_value' => $line['discount_value'],
                    'discount_amount' => $line['discount_amount'],
                    'tax_rate' => $line['tax_rate'],
                    'net_amount' => $line['net_amount'],
                    'vat_amount' => $line['vat_amount'],
                    'line_total' => $line['line_total'],
                    'cost_price' => $line['cost_price'],
                    'price_override' => $line['price_override'],
                    'override_reason' => $line['override_reason'],
                    'created_at' => now(),
                ]);
            }

            foreach ($payments as $payment) {
                $sale->payments()->create([
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'],
                    'created_at' => now(),
                ]);
            }

            foreach ($pricing['lines'] as $line) {
                $product = $products->get($line['product_id']);
                if (! $product->track_stock) {
                    continue;
                }
                $this->stock->apply(
                    $product,
                    $user,
                    'sale',
                    0,
                    (float) $line['qty'],
                    (float) $line['cost_price'],
                    'sale',
                    $sale->id,
                    'Invoice '.$sale->invoice_number,
                );
            }

            $this->audit->log('sale.completed', $sale, [
                'invoice_number' => $sale->invoice_number,
                'total' => $sale->total,
                'vat' => $sale->vat_total,
                'payments' => $sale->payments->count(),
                'approved_by' => $approver?->name,
            ], $user);

            return $sale->fresh(['items', 'payments', 'customer', 'user']);
        });
    }
}
