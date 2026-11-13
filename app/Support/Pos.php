<?php

namespace App\Support;

use App\Models\Setting;

class Pos
{
    public static function setting(string $key, mixed $default = null): mixed
    {
        $value = Setting::get($key);

        return $value === null ? $default : $value;
    }

    public static function company(): string
    {
        return (string) self::setting('company_name', config('pos.company'));
    }

    public static function trn(): string
    {
        return (string) self::setting('company_trn', config('pos.trn'));
    }

    public static function address(): string
    {
        return (string) self::setting('company_address', config('pos.address'));
    }

    public static function phone(): string
    {
        return (string) self::setting('company_phone', config('pos.phone'));
    }

    public static function email(): string
    {
        return (string) self::setting('company_email', config('pos.email'));
    }

    public static function vatRate(): float
    {
        return (float) self::setting('vat_rate', config('pos.vat_rate', 5));
    }

    public static function pricesIncludeVat(): bool
    {
        return (bool) filter_var(self::setting('prices_include_vat', '1'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function invoicePrefix(): string
    {
        return strtoupper((string) self::setting('invoice_prefix', config('pos.invoice_prefix', 'GG')));
    }

    public static function cashierDiscountLimit(): float
    {
        return (float) self::setting('cashier_discount_limit', config('pos.cashier_discount_limit', 5));
    }

    public static function receiptFooter(): string
    {
        return (string) self::setting('receipt_footer', 'Thank you for your business. Returns within 7 days with invoice. Opened or electrical parts are not returnable.');
    }

    public static function currency(): string
    {
        return 'AED';
    }
}
