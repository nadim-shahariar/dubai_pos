<?php

namespace App\Support;

class Money
{
    public static function cents(float|int|string|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    public static function decimal(int $cents): float
    {
        return round($cents / 100, 2);
    }

    public static function format(float|int|string|null $amount): string
    {
        return number_format((float) ($amount ?? 0), 2, '.', ',');
    }

    public static function aed(float|int|string|null $amount): string
    {
        return 'AED '.self::format($amount);
    }
}
