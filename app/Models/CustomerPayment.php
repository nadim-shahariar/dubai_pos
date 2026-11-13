<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPayment extends Model
{
    protected $fillable = [
        'receipt_number', 'customer_id', 'sale_id', 'user_id', 'shift_id',
        'method', 'amount', 'reference', 'notes', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function sale() { return $this->belongsTo(Sale::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function shift() { return $this->belongsTo(Shift::class); }
}
