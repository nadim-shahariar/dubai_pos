<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductFitment extends Model
{
    protected $fillable = [
        'product_id', 'car_brand_id', 'car_model_id', 'year_from', 'year_to', 'engine',
        'mapped_part_number', 'cost_price',
    ];

    protected function casts(): array
    {
        return ['cost_price' => 'decimal:2'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function brand()
    {
        return $this->belongsTo(CarBrand::class, 'car_brand_id');
    }

    public function model()
    {
        return $this->belongsTo(CarModel::class, 'car_model_id');
    }
}
