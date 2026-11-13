<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'sale_id', 'product_id', 'primary_part_number', 'name', 'qty', 'unit_price',
        'original_unit_price', 'discount_type', 'discount_value', 'discount_amount',
        'tax_rate', 'net_amount', 'vat_amount', 'line_total', 'cost_price',
        'price_override', 'override_reason', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'original_unit_price' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'price_override' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function sale() { return $this->belongsTo(Sale::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function returnItems() { return $this->hasMany(ReturnItem::class); }

    public function returnedQty(): float
    {
        return (float) $this->returnItems()->sum('qty');
    }
}
