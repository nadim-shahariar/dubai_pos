<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturn extends Model
{
    protected $table = 'returns';
    protected $fillable = [
        'credit_note_number', 'sale_id', 'customer_id', 'user_id', 'reason', 'refund_method',
        'subtotal', 'vat_total', 'total', 'status', 'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'returned_at' => 'datetime',
        ];
    }

    public function sale() { return $this->belongsTo(Sale::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(ReturnItem::class, 'return_id'); }
}
