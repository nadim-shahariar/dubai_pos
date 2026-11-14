<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockReceipt;
use App\Models\User;
use App\Support\InvoiceNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkStockReceiptService
{
    public function receive(User $user, array $data): StockReceipt
    {
        abort_unless($user->canManageInventory(), 403);
        $lines = collect($data['items'])->map(fn ($row) => [
            'product_id' => (int) $row['product_id'],
            'product_fitment_id' => empty($row['product_fitment_id']) ? null : (int) $row['product_fitment_id'],
            'qty' => round((float) $row['qty'], 3), 'unit_cost' => round((float) $row['unit_cost'], 2),
        ])->sortBy(fn ($row) => sprintf('%012d-%012d', $row['product_id'], $row['product_fitment_id'] ?? 0))->values();
        if ($lines->pluck('product_id')->isEmpty() || $lines->unique(fn ($row) => $row['product_id'].'-'.$row['product_fitment_id'])->count() !== $lines->count()) {
            throw ValidationException::withMessages(['items' => 'Select each product or variant only once.']);
        }
        $hash = hash('sha256', json_encode([$data['received_on'], $data['reference'] ?? null, $data['notes'] ?? null, $lines->all()]));

        return DB::transaction(function () use ($user, $data, $lines, $hash) {
            $products = Product::whereIn('id', $lines->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $existing = StockReceipt::where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->user_id === $user->id, 403);
                if ($existing->payload_hash !== $hash) {
                    throw ValidationException::withMessages(['request_key' => 'This receipt was already saved with different details. Open a new receipt.']);
                }

                return $existing;
            }
            $receipt = StockReceipt::create([
                'receipt_number' => InvoiceNumber::next('stock_receipt'), 'request_key' => $data['request_key'],
                'payload_hash' => $hash, 'received_on' => $data['received_on'], 'user_id' => $user->id,
                'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null,
            ]);
            foreach ($lines as $index => $row) {
                $product = $products->get($row['product_id']);
                if (! $product || ! $product->is_active || ! $product->track_stock) {
                    throw ValidationException::withMessages(['items' => 'Select an active stock-tracked product.']);
                }
                $fitment = null;
                if ($row['product_fitment_id']) {
                    $fitment = $product->fitments()->whereKey($row['product_fitment_id'])->lockForUpdate()->first();
                    if (! $fitment) {
                        throw ValidationException::withMessages(['items' => 'A selected variant does not belong to its product.']);
                    }
                }
                if (! $fitment && $product->fitments()->exists()) {
                    throw ValidationException::withMessages(['items' => 'Choose an individual vehicle variant to receive stock.']);
                }
                if ($row['qty'] <= 0 || $row['unit_cost'] < 0) {
                    throw ValidationException::withMessages(['items' => 'Received quantities must be positive and costs cannot be negative.']);
                }
                $movement = app(StockService::class)->receive($product, $user, $row['qty'], $row['unit_cost'], $receipt->receipt_number.' · '.$data['received_on'].' · '.($data['reference'] ?? ''), $fitment);
                // Keep the ledger's recording time; the business receipt date is stored on the header.
                $movement->update(['reference_id' => $receipt->id]);
                $receipt->items()->create($row + ['product_name' => $product->name, 'variant_label' => $fitment ? trim($fitment->brand?->name.' '.$fitment->model?->name.' '.$fitment->year_from.'–'.$fitment->year_to) : null, 'part_number' => $fitment?->mapped_part_number ?: $product->primary_part_number, 'balance_after' => $fitment ? $fitment->stock_qty : $product->stock_qty]);
            }
            app(AuditService::class)->log('inventory.bulk_received', $receipt, ['received_on' => $receipt->received_on->toDateString(), 'lines' => $lines->count()], $user);

            return $receipt;
        });
    }
}
