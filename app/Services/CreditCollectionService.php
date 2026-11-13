<?php

namespace App\Services;

use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use App\Support\InvoiceNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditCollectionService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function collect(User $user, Sale $sale, array $data): CustomerPayment
    {
        if (! $user->canCollectCredit()) {
            throw ValidationException::withMessages(['payment' => 'Your role cannot receive credit payments.']);
        }

        $method = (string) ($data['method'] ?? 'cash');
        if (! in_array($method, ['cash', 'card', 'bank_transfer'], true)) {
            throw ValidationException::withMessages(['method' => 'Choose cash, card or bank transfer.']);
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter a payment amount greater than zero.']);
        }

        if (! $sale->customer_id) {
            throw ValidationException::withMessages(['sale' => 'This invoice is not linked to a customer account.']);
        }

        if ($sale->status === 'voided') {
            throw ValidationException::withMessages(['sale' => 'A voided invoice cannot receive payment.']);
        }

        $shift = Shift::openFor($user);
        if ($method === 'cash' && ! $shift && $user->isCashier()) {
            throw ValidationException::withMessages(['shift' => 'Open a shift before receiving cash.']);
        }

        return DB::transaction(function () use ($user, $sale, $amount, $method, $shift, $data) {
            $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $balance = round((float) $locked->balance_due, 2);

            if ($balance <= 0.009) {
                throw ValidationException::withMessages(['amount' => 'This invoice is already fully paid.']);
            }

            if ($amount > $balance + 0.001) {
                throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the remaining balance of AED '.number_format($balance, 2).'.']);
            }

            $payment = CustomerPayment::create([
                'receipt_number' => InvoiceNumber::next('collection'),
                'customer_id' => $locked->customer_id,
                'sale_id' => $locked->id,
                'user_id' => $user->id,
                'shift_id' => $shift?->id,
                'method' => $method,
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => now(),
            ]);

            $newBalance = round($balance - $amount, 2);

            $locked->update([
                'paid_total' => round((float) $locked->paid_total + $amount, 2),
                'balance_due' => $newBalance,
                'payment_status' => $newBalance <= 0.009 ? 'paid' : 'partial',
            ]);

            $this->audit->log('credit_payment.received', $payment, [
                'receipt_number' => $payment->receipt_number,
                'invoice_number' => $locked->invoice_number,
                'amount' => $amount,
                'remaining_balance' => $newBalance,
                'method' => $method,
            ], $user);

            return $payment->fresh(['customer', 'sale', 'user']);
        });
    }
}
