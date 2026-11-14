<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReceipt extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['received_on' => 'date'];
    }

    public function items()
    {
        return $this->hasMany(StockReceiptItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
