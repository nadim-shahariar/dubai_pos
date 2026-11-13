<?php

namespace App\Support;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class InvoiceNumber
{
    public static function next(string $type = 'invoice', ?string $period = null): string
    {
        $period ??= now()->format('Y');
        $prefix = Pos::invoicePrefix();
        $documentPrefix = match ($type) {
            'credit_note' => 'CN-'.$prefix,
            'return' => 'RET-'.$prefix,
            default => $prefix,
        };

        return DB::transaction(function () use ($type, $period, $documentPrefix) {
            $sequence = DocumentSequence::query()
                ->where('type', $type)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create([
                    'type' => $type,
                    'period' => $period,
                    'next_number' => 1,
                ]);
            }

            $number = (int) $sequence->next_number;
            $sequence->increment('next_number');

            return sprintf('%s-%s-%06d', $documentPrefix, $period, $number);
        });
    }
}
