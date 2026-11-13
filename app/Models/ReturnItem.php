<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnItem extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'return_id', 'sale_item_id', 'product_id', 'qty', 'unit_price', 'net_amount',
        'vat_amount', 'total', 'restock', 'condition', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'restock' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function return() { return $this->belongsTo(SaleReturn::class, 'return_id'); }
    public function saleItem() { return $this->belongsTo(SaleItem::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
