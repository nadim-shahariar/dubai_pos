<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'product_id', 'product_fitment_id', 'user_id', 'type', 'qty_in', 'qty_out', 'unit_cost',
        'balance_after', 'variant_balance_after', 'reference_type', 'reference_id', 'note', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_in' => 'decimal:3',
            'qty_out' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'balance_after' => 'decimal:3',
            'variant_balance_after' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }

    public function product() { return $this->belongsTo(Product::class); }
    public function fitment() { return $this->belongsTo(ProductFitment::class, 'product_fitment_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
