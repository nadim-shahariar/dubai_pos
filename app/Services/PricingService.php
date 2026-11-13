<?php

namespace App\Services;

use App\Support\Money;

class PricingService
{
    /**
     * Calculate line and invoice totals in integer fils.
     *
     * @param array<int, array<string, mixed>> $lines
     * @return array<string, mixed>
     */
    public function calculate(
        array $lines,
        string $invoiceDiscountType = 'none',
        float|int|string $invoiceDiscountValue = 0,
        ?bool $inclusive = null,
        ?float $defaultTaxRate = null,
    ): array {
        $inclusive ??= \App\Support\Pos::pricesIncludeVat();
        $defaultTaxRate ??= \App\Support\Pos::vatRate();

        $normalised = [];
        foreach (array_values($lines) as $index => $line) {
            $qty = round((float) ($line['qty'] ?? 0), 3);
            $unitPrice = Money::cents($line['unit_price'] ?? 0);
            $taxRate = (float) ($line['tax_rate'] ?? $defaultTaxRate);
            $gross = (int) round($qty * $unitPrice);
            $lineDiscount = $this->discountCents($gross, $line['discount_type'] ?? 'none', $line['discount_value'] ?? 0);
            $lineDiscount = min($lineDiscount, $gross);

            $normalised[] = [
                'index' => $index,
                'product_id' => $line['product_id'] ?? null,
                'primary_part_number' => (string) ($line['primary_part_number'] ?? ''),
                'name' => (string) ($line['name'] ?? ''),
                'qty' => $qty,
                'unit_price_cents' => $unitPrice,
                'original_unit_price_cents' => Money::cents($line['original_unit_price'] ?? $line['unit_price'] ?? 0),
                'discount_type' => $line['discount_type'] ?? 'none',
                'discount_value' => (float) ($line['discount_value'] ?? 0),
                'line_discount_cents' => $lineDiscount,
                'gross_after_line_cents' => $gross - $lineDiscount,
                'tax_rate' => $taxRate,
                'cost_price_cents' => Money::cents($line['cost_price'] ?? 0),
                'price_override' => (bool) ($line['price_override'] ?? false),
                'override_reason' => $line['override_reason'] ?? null,
                'invoice_discount_cents' => 0,
            ];
        }

        $base = array_sum(array_column($normalised, 'gross_after_line_cents'));
        $invoiceDiscount = $this->discountCents($base, $invoiceDiscountType, $invoiceDiscountValue);
        $invoiceDiscount = min($invoiceDiscount, $base);
        $allocations = $this->allocate($invoiceDiscount, array_column($normalised, 'gross_after_line_cents'));

        $resultLines = [];
        $subtotal = 0;
        $lineDiscountTotal = 0;
        $netTotal = 0;
        $vatTotal = 0;
        $total = 0;

        foreach ($normalised as $i => $line) {
            $allocated = $allocations[$i] ?? 0;
            $discountedGross = max(0, $line['gross_after_line_cents'] - $allocated);

            if ($inclusive) {
                $net = (int) round($discountedGross / (1 + ($line['tax_rate'] / 100)));
                $vat = $discountedGross - $net;
                $lineTotal = $discountedGross;
            } else {
                $net = $discountedGross;
                $vat = (int) round($net * ($line['tax_rate'] / 100));
                $lineTotal = $net + $vat;
            }

            $subtotal += $line['gross_after_line_cents'] + $line['line_discount_cents'];
            $lineDiscountTotal += $line['line_discount_cents'];
            $netTotal += $net;
            $vatTotal += $vat;
            $total += $lineTotal;

            $resultLines[] = [
                'product_id' => $line['product_id'],
                'primary_part_number' => $line['primary_part_number'],
                'name' => $line['name'],
                'qty' => $line['qty'],
                'unit_price' => Money::decimal($line['unit_price_cents']),
                'original_unit_price' => Money::decimal($line['original_unit_price_cents']),
                'discount_type' => $line['discount_type'],
                'discount_value' => $line['discount_value'],
                'discount_amount' => Money::decimal($line['line_discount_cents']),
                'tax_rate' => $line['tax_rate'],
                'net_amount' => Money::decimal($net),
                'vat_amount' => Money::decimal($vat),
                'line_total' => Money::decimal($lineTotal),
                'cost_price' => Money::decimal($line['cost_price_cents']),
                'price_override' => $line['price_override'],
                'override_reason' => $line['override_reason'],
                'invoice_discount_allocated' => Money::decimal($allocated),
            ];
        }

        return [
            'lines' => $resultLines,
            'subtotal' => Money::decimal($subtotal),
            'line_discount_total' => Money::decimal($lineDiscountTotal),
            'invoice_discount_total' => Money::decimal($invoiceDiscount),
            'discount_total' => Money::decimal($lineDiscountTotal + $invoiceDiscount),
            'net_total' => Money::decimal($netTotal),
            'vat_total' => Money::decimal($vatTotal),
            'total' => Money::decimal($total),
            'price_mode' => $inclusive ? 'inclusive' : 'exclusive',
        ];
    }

    public function discountCents(int $baseCents, string $type, float|int|string $value): int
    {
        return match ($type) {
            'percent' => (int) round($baseCents * ((float) $value / 100)),
            'amount' => Money::cents($value),
            default => 0,
        };
    }

    /**
     * Allocate an amount proportionally. Remainder goes to the largest line.
     *
     * @param array<int, int> $weights
     * @return array<int, int>
     */
    private function allocate(int $amount, array $weights): array
    {
        $totalWeight = array_sum($weights);
        if ($amount <= 0 || $totalWeight <= 0) {
            return array_fill(0, count($weights), 0);
        }

        $allocations = [];
        $allocated = 0;
        foreach ($weights as $i => $weight) {
            $share = (int) floor($amount * ($weight / $totalWeight));
            $allocations[$i] = $share;
            $allocated += $share;
        }

        $remainder = $amount - $allocated;
        if ($remainder > 0 && $weights) {
            $largest = array_keys($weights, max($weights))[0];
            $allocations[$largest] += $remainder;
        }

        return $allocations;
    }
}
