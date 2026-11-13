<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\InvoiceNumber;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly AuditService $audit,
    ) {
    }

    public function create(User $user, Sale $sale, array $items, string $reason, string $refundMethod = 'cash'): SaleReturn
    {
        if ($sale->status === 'voided') {
            throw ValidationException::withMessages(['sale' => 'A voided sale cannot be returned.']);
        }

        if (! $items) {
            throw ValidationException::withMessages(['items' => 'Select at least one item to return.']);
        }

        return DB::transaction(function () use ($user, $sale, $items, $reason, $refundMethod) {
            $saleItems = SaleItem::query()
                ->where('sale_id', $sale->id)
                ->whereIn('id', collect($items)->pluck('sale_item_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $returnLines = [];
            $subtotalCents = 0;
            $vatCents = 0;
            $totalCents = 0;

            foreach ($items as $item) {
                $saleItem = $saleItems->get($item['sale_item_id'] ?? null);
                if (! $saleItem) {
                    throw ValidationException::withMessages(['items' => 'One of the return lines is not on this invoice.']);
                }

                $qty = round((float) ($item['qty'] ?? 0), 3);
                $alreadyReturned = $saleItem->returnedQty();
                $available = (float) $saleItem->qty - $alreadyReturned;

                if ($qty <= 0 || $qty > $available + 0.0001) {
                    throw ValidationException::withMessages([
                        'items' => "Invalid return quantity for {$saleItem->primary_part_number}. Returnable: ".number_format(max(0, $available), 3).'.',
                    ]);
                }

                $ratio = (float) $saleItem->qty > 0 ? $qty / (float) $saleItem->qty : 0;
                $lineNet = Money::cents(round((float) $saleItem->net_amount * $ratio, 2));
                $lineVat = Money::cents(round((float) $saleItem->vat_amount * $ratio, 2));
                $lineTotal = Money::cents(round((float) $saleItem->line_total * $ratio, 2));

                $subtotalCents += $lineNet;
                $vatCents += $lineVat;
                $totalCents += $lineTotal;

                $returnLines[] = [
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'qty' => $qty,
                    'unit_price' => (float) $saleItem->unit_price,
                    'net_amount' => Money::decimal($lineNet),
                    'vat_amount' => Money::decimal($lineVat),
                    'total' => Money::decimal($lineTotal),
                    'restock' => (bool) ($item['restock'] ?? true),
                    'condition' => (string) ($item['condition'] ?? 'resellable'),
                    'cost_price' => (float) $saleItem->cost_price,
                ];
            }

            $return = SaleReturn::create([
                'credit_note_number' => InvoiceNumber::next('credit_note'),
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'user_id' => $user->id,
                'reason' => $reason,
                'refund_method' => $refundMethod,
                'subtotal' => Money::decimal($subtotalCents),
                'vat_total' => Money::decimal($vatCents),
                'total' => Money::decimal($totalCents),
                'status' => 'completed',
                'returned_at' => now(),
            ]);

            foreach ($returnLines as $line) {
                $return->items()->create([
                    'sale_item_id' => $line['sale_item_id'],
                    'product_id' => $line['product_id'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'net_amount' => $line['net_amount'],
                    'vat_amount' => $line['vat_amount'],
                    'total' => $line['total'],
                    'restock' => $line['restock'],
                    'condition' => $line['condition'],
                    'created_at' => now(),
                ]);

                if ($line['restock'] && $line['product_id']) {
                    $product = Product::find($line['product_id']);
                    if ($product && $product->track_stock) {
                        $this->stock->apply(
                            $product,
                            $user,
                            'sale_return',
                            (float) $line['qty'],
                            0,
                            (float) $line['cost_price'],
                            'return',
                            $return->id,
                            'Credit note '.$return->credit_note_number,
                        );
                    }
                }
            }

            $returnedQty = (float) $sale->items()->withSum('returnItems', 'qty')->get()->sum(fn ($item) => (float) ($item->return_items_sum_qty ?? 0));
            $soldQty = (float) $sale->items()->sum('qty');
            $sale->update([
                'status' => $returnedQty + 0.0001 >= $soldQty ? 'refunded' : 'partially_refunded',
            ]);

            $this->audit->log('return.completed', $return, [
                'credit_note' => $return->credit_note_number,
                'sale' => $sale->invoice_number,
                'total' => $return->total,
                'reason' => $reason,
            ], $user);

            return $return->fresh(['items', 'sale', 'customer', 'user']);
        });
    }
}
