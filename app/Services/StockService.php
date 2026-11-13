<?php

namespace App\Services;

use App\Models\Product;
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
    ): StockMovement {
        $before = (float) $product->stock_qty;
        $balance = round($before + $qtyIn - $qtyOut, 3);

        if ($balance < -0.0001) {
            throw ValidationException::withMessages([
                'stock' => "Not enough stock for {$product->primary_part_number}. Available: ".number_format($before, 3).', requested: '.number_format($qtyOut, 3).'.',
            ]);
        }

        $product->stock_qty = $balance;
        $product->save();

        return StockMovement::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => $type,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'unit_cost' => $unitCost,
            'balance_after' => $balance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    public function receive(Product $product, User $user, float $qty, float $unitCost, ?string $reference = null): StockMovement
    {
        if ($qty <= 0) {
            throw ValidationException::withMessages(['qty' => 'Received quantity must be greater than zero.']);
        }

        $oldQty = (float) $product->stock_qty;
        $oldCost = (float) $product->cost_price;
        $newCost = $oldQty > 0
            ? (($oldQty * $oldCost) + ($qty * $unitCost)) / ($oldQty + $qty)
            : $unitCost;

        $product->cost_price = round($newCost, 2);
        $product->cost_pending = false;

        return $this->apply($product, $user, 'purchase_receipt', $qty, 0, $unitCost, 'stock_receipt', null, $reference);
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
