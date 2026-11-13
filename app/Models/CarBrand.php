<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarBrand extends Model
{
    protected $fillable = ['name'];

    public function models()
    {
        return $this->hasMany(CarModel::class);
    }

    /**
     * Find a brand by name without creating case-only duplicates, or create it.
     */
    public static function findOrCreateByName(string $name): self
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));

        $existing = static::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        return $existing ?? static::create(['name' => $name]);
    }
}
