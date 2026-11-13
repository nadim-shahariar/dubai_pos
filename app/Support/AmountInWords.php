<?php

namespace App\Support;

class AmountInWords
{
    public static function aed(float|int|string $amount): string
    {
        $cents = Money::cents($amount);
        $whole = intdiv(abs($cents), 100);
        $fils = abs($cents) % 100;
        $words = ($cents < 0 ? 'Minus ' : '').self::integer($whole).' '.($whole === 1 ? 'Dirham' : 'Dirhams');
        if ($fils > 0) {
            $words .= ' and '.self::integer($fils).' '.($fils === 1 ? 'Fil' : 'Fils');
        }

        return $words.' Only.';
    }

    private static function integer(int $number): string
    {
        $small = ['Zero', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        if ($number < 20) {
            return $small[$number];
        }
        if ($number < 100) {
            return $tens[intdiv($number, 10)].($number % 10 ? '-'.strtolower($small[$number % 10]) : '');
        }
        if ($number < 1000) {
            return $small[intdiv($number, 100)].' Hundred'.($number % 100 ? ' and '.self::integer($number % 100) : '');
        }
        foreach ([1000000000000 => 'Trillion', 1000000000 => 'Billion', 1000000 => 'Million', 1000 => 'Thousand'] as $scale => $label) {
            if ($number >= $scale) {
                $remainder = $number % $scale;

                return self::integer(intdiv($number, $scale)).' '.$label.($remainder ? ($remainder < 100 ? ' and ' : ' ').self::integer($remainder) : '');
            }
        }

        return '';
    }
}
