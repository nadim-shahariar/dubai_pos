<?php

namespace App\Services;

use App\Models\User;
use App\Support\Pos;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    /**
     * Authorise a sale that contains discounts or price overrides.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return User|null The approving manager, or null when no approval is needed.
     */
    public function authorise(User $user, array $lines, float $invoiceDiscountPercent, ?string $pin): ?User
    {
        $requiresApproval = false;
        $limit = Pos::cashierDiscountLimit();

        foreach ($lines as $line) {
            $gross = (float) ($line['qty'] ?? 0) * (float) ($line['unit_price'] ?? 0);
            $discount = (float) ($line['discount_amount'] ?? 0);
            $percent = $gross > 0 ? ($discount / $gross) * 100 : 0;
            $original = (float) ($line['original_unit_price'] ?? $line['unit_price'] ?? 0);
            $price = (float) ($line['unit_price'] ?? 0);
            $cost = (float) ($line['cost_price'] ?? 0);

            if ($percent > $limit + 0.0001) {
                $requiresApproval = true;
            }

            if ($price < $original - 0.0001) {
                if (! $user->canApprove()) {
                    $requiresApproval = true;
                }
            }

            if ($price < $cost - 0.0001) {
                $requiresApproval = true;
            }
        }

        if ($invoiceDiscountPercent > $limit + 0.0001) {
            $requiresApproval = true;
        }

        if (! $requiresApproval) {
            return null;
        }

        if ($user->canApprove()) {
            return $user;
        }

        $approver = $this->findApprover($pin);

        if (! $approver) {
            throw ValidationException::withMessages([
                'approval_pin' => 'Manager approval is required for this discount or price override. Enter a valid manager PIN.',
            ]);
        }

        return $approver;
    }

    private function findApprover(?string $pin): ?User
    {
        if (! $pin) {
            return null;
        }

        return User::query()
            ->where('is_active', true)
            ->get()
            ->first(fn (User $user) => $user->canApprove() && $user->pin && Hash::check($pin, $user->pin));
    }
}
