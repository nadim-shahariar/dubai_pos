<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductFitment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VoidSaleService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly AuditService $audit,
    ) {
    }

    public function void(User $user, Sale $sale, ?string $pin, ?string $reason = null): Sale
    {
        $approver = $user;
        if (! $user->canApprove()) {
            $approver = $this->findApprover($pin);
            if (! $approver) {
                throw ValidationException::withMessages([
                    'approval_pin' => 'A manager or owner PIN is required to void an invoice.',
                ]);
            }
        }

        if ($sale->status !== 'completed') {
            throw ValidationException::withMessages(['sale' => 'Only a completed invoice can be voided.']);
        }

        if ($sale->returns()->exists()) {
            throw ValidationException::withMessages(['sale' => 'This invoice has a credit note/return. Use the return flow instead.']);
        }

        if ($sale->collections()->exists()) {
            throw ValidationException::withMessages(['sale' => 'This invoice has credit collections. Reverse those payments first.']);
        }

        return DB::transaction(function () use ($user, $sale, $approver, $reason) {
            $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'completed') {
                throw ValidationException::withMessages(['sale' => 'This invoice is no longer completed.']);
            }

            $locked->load(['items', 'payments', 'collections', 'returns']);

            foreach ($locked->items as $item) {
                if (! $item->product_id) {
                    continue;
                }
                $product = Product::query()->lockForUpdate()->find($item->product_id);
                if (! $product || ! $product->track_stock) {
                    continue;
                }

                $fitment = null;
                if ($item->variant_sku || $item->product_fitment_id) {
                    $fitment = ProductFitment::query()
                        ->where('product_id', $product->id)
                        ->when($item->product_fitment_id, fn ($q) => $q->whereKey($item->product_fitment_id), fn ($q) => $q->where('mapped_part_number', $item->variant_sku))
                        ->lockForUpdate()
                        ->first();
                }

                $this->stock->apply(
                    $product,
                    $user,
                    'sale_void',
                    (float) $item->qty,
                    0,
                    (float) $item->cost_price,
                    'void',
                    $locked->id,
                    'Void invoice '.$locked->invoice_number.($reason ? ': '.$reason : ''),
                    $fitment,
                );
            }

            $locked->update([
                'status' => 'voided',
                'payment_status' => 'voided',
                'balance_due' => 0,
                'approved_by' => $approver->id,
                'notes' => trim(($locked->notes ? $locked->notes.' ' : '').'VOIDED'.($reason ? ': '.$reason : '')),
            ]);

            $this->audit->log('sale.voided', $locked, [
                'invoice_number' => $locked->invoice_number,
                'reason' => $reason,
                'voided_by' => $user->name,
                'approved_by' => $approver->name,
            ], $user);

            return $locked->fresh(['items', 'payments', 'customer']);
        });
    }

    private function findApprover(?string $pin): ?User
    {
        if (! $pin) {
            return null;
        }

        return User::query()
            ->where('is_active', true)
            ->whereIn('role', ['owner', 'manager'])
            ->get()
            ->first(fn (User $user) => $user->pin && Hash::check($pin, $user->pin));
    }
}
