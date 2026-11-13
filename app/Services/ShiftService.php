<?php

namespace App\Services;

use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftService
{
    public function open(User $user, float $openingFloat = 0): Shift
    {
        if (Shift::openFor($user)) {
            throw ValidationException::withMessages(['shift' => 'You already have an open shift. Close it before opening another.']);
        }

        return Shift::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_float' => round($openingFloat, 2),
            'status' => 'open',
        ]);
    }

    public function expectedCash(Shift $shift): float
    {
        $cashSales = (float) SalePayment::query()
            ->where('method', 'cash')
            ->whereHas('sale', fn ($q) => $q->where('shift_id', $shift->id)->where('status', '!=', 'voided'))
            ->sum('amount');

        $cashRefunds = (float) SaleReturn::query()
            ->where('refund_method', 'cash')
            ->where('returned_at', '>=', $shift->opened_at)
            ->where('returned_at', '<=', $shift->closed_at ?? now())
            ->sum('total');

        $in = (float) $shift->cashMovements()->where('direction', 'in')->sum('amount');
        $out = (float) $shift->cashMovements()->where('direction', 'out')->sum('amount');

        return round((float) $shift->opening_float + $cashSales - $cashRefunds + $in - $out, 2);
    }

    public function close(Shift $shift, float $countedCash, ?string $notes = null): Shift
    {
        if ($shift->status !== 'open') {
            throw ValidationException::withMessages(['shift' => 'This shift is already closed.']);
        }

        return DB::transaction(function () use ($shift, $countedCash, $notes) {
            $expected = $this->expectedCash($shift);
            $counted = round($countedCash, 2);

            $shift->update([
                'closed_at' => now(),
                'expected_cash' => $expected,
                'counted_cash' => $counted,
                'variance' => round($counted - $expected, 2),
                'status' => 'closed',
                'notes' => $notes,
            ]);

            return $shift->fresh();
        });
    }
}
