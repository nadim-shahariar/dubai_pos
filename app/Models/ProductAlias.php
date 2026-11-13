<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAlias extends Model
{
    protected $fillable = ['product_id', 'reference_type', 'reference_number', 'brand'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
