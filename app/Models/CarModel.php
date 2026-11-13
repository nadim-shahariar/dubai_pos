<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarModel extends Model
{
    protected $fillable = ['car_brand_id', 'name'];

    public function brand()
    {
        return $this->belongsTo(CarBrand::class, 'car_brand_id');
    }

    public function fitments()
    {
        return $this->hasMany(ProductFitment::class);
    }

    /**
     * Find a model under a brand, or create it.
     */
    public static function findOrCreateForBrand(CarBrand|int $brand, string $name): self
    {
        $brandId = $brand instanceof CarBrand ? $brand->id : $brand;
        $name = trim(preg_replace('/\s+/', ' ', $name));

        $existing = static::query()
            ->where('car_brand_id', $brandId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        return $existing ?? static::create([
            'car_brand_id' => $brandId,
            'name' => $name,
        ]);
    }
}
