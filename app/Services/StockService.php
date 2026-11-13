<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductFitment;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function apply(
        Product $product,
        User $user,
        string $type,
        float $qtyIn = 0,
        float $qtyOut = 0,
        float $unitCost = 0,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
        ?ProductFitment $fitment = null,
    ): StockMovement {
        $before = (float) $product->stock_qty;
        if ($fitment) {
            if ($fitment->product_id !== $product->id) {
                throw ValidationException::withMessages(['stock' => 'Variant does not belong to this product.']);
            }
            // The selected SKU is the only source of sellable stock.
            $variantBefore = (float) ($fitment->stock_qty ?? 0);
            $variantBalance = round($variantBefore + $qtyIn - $qtyOut, 3);
            if ($variantBalance < -0.0001) {
                throw ValidationException::withMessages(['stock' => "Not enough stock for variant {$fitment->mapped_part_number}. Available: {$variantBefore}."]);
            }
            $fitment->stock_qty = $variantBalance;
            $fitment->save();
            // A cached rollup for reports only; never used to approve a variant sale.
            $balance = round((float) $product->fitments()->sum('stock_qty'), 3);
        } else {
            if ($product->fitments()->exists() && in_array($type, ['sale', 'purchase_receipt', 'adjustment_in', 'adjustment_out'], true)) {
                throw ValidationException::withMessages(['stock' => 'Choose an individual variant for this stock movement.']);
            }
            $balance = round($before + $qtyIn - $qtyOut, 3);
            if ($balance < -0.0001) {
                throw ValidationException::withMessages(['stock' => "Not enough stock for {$product->primary_part_number}."]);
            }
        }
        $product->stock_qty = $balance;
        $product->save();

        return StockMovement::create([
            'product_id' => $product->id,
            'product_fitment_id' => $fitment?->id,
            'user_id' => $user->id,
            'type' => $type,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'unit_cost' => $unitCost,
            'balance_after' => $balance,
            'variant_balance_after' => $fitment ? $variantBalance : null,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    public function receive(Product $product, User $user, float $qty, float $unitCost, ?string $reference = null, ?ProductFitment $fitment = null): StockMovement
    {
        if ($qty <= 0) {
            throw ValidationException::withMessages(['qty' => 'Received quantity must be greater than zero.']);
        }

        if ($fitment) {
            if ($fitment->product_id !== $product->id) {
                throw ValidationException::withMessages(['product_fitment_id' => 'Variant does not belong to this product.']);
            }
            $variantQty = (float) ($fitment->stock_qty ?? 0);
            $fitment->cost_price = round(($variantQty * (float) ($fitment->cost_price ?? $product->cost_price) + $qty * $unitCost) / ($variantQty + $qty), 2);
            $fitment->stock_qty = $variantQty;
        }
        $oldQty = (float) $product->stock_qty;
        $oldCost = (float) $product->cost_price;
        $newCost = $oldQty > 0
            ? (($oldQty * $oldCost) + ($qty * $unitCost)) / ($oldQty + $qty)
            : $unitCost;

        $product->cost_price = round($newCost, 2);
        $product->cost_pending = false;

        return $this->apply($product, $user, 'purchase_receipt', $qty, 0, $unitCost, 'stock_receipt', null, $reference, $fitment);
    }

    public function adjust(Product $product, User $user, float $newQty, ?string $note = null): StockMovement
    {
        $difference = round($newQty - (float) $product->stock_qty, 3);
        $isIncrease = $difference >= 0;

        return $this->apply(
            $product,
            $user,
            $isIncrease ? 'adjustment_in' : 'adjustment_out',
            $isIncrease ? $difference : 0,
            $isIncrease ? 0 : abs($difference),
            (float) $product->cost_price,
            'stock_adjustment',
            null,
            $note,
        );
    }
}
