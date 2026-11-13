<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'invoice_number', 'customer_id', 'user_id', 'shift_id', 'approved_by', 'status', 'payment_status',
        'price_mode', 'subtotal', 'line_discount_total', 'invoice_discount_total', 'discount_total', 'net_total', 'vat_total', 'total',
        'paid_total', 'change_total', 'balance_due', 'customer_name', 'customer_trn',
        'vehicle', 'notes', 'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'line_discount_total' => 'decimal:2',
            'invoice_discount_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'net_total' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'change_total' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'sold_at' => 'datetime',
        ];
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function shift() { return $this->belongsTo(Shift::class); }
    public function items() { return $this->hasMany(SaleItem::class); }
    public function payments() { return $this->hasMany(SalePayment::class); }
    public function returns() { return $this->hasMany(SaleReturn::class); }

    public function isCredit(): bool
    {
        return (float) $this->balance_due > 0;
    }

    public function statusLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    public function paymentLabel(): string
    {
        return $this->payments->pluck('method')->unique()->map(fn ($m) => ucfirst($m))->implode(' + ') ?: ucfirst($this->payment_status);
    }
}
