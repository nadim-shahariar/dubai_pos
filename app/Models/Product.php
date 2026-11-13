<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'primary_part_number', 'name', 'name_ar', 'description', 'category_id', 'brand',
        'barcode', 'unit', 'tax_rate', 'selling_price', 'cost_price', 'secret_cost_code', 'track_stock',
        'stock_qty', 'reorder_level', 'bin_location', 'is_active', 'cost_pending',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock_qty' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'is_active' => 'boolean',
            'cost_pending' => 'boolean',
            'track_stock' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function fitments()
    {
        return $this->hasMany(ProductFitment::class);
    }

    public function aliases()
    {
        return $this->hasMany(ProductAlias::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function fitmentHint(): string
    {
        return $this->fitments
            ->map(fn (ProductFitment $f) => trim(($f->brand?->name ?? '').' '.($f->model?->name ?? '').' '.$f->year_from.'–'.$f->year_to))
            ->filter()
            ->unique()
            ->take(3)
            ->implode(' · ');
    }

    public function margin(): float
    {
        $price = (float) $this->selling_price;
        $cost = (float) $this->cost_price;

        return $price > 0 ? round((($price - $cost) / $price) * 100, 1) : 0.0;
    }

    public function isLowStock(): bool
    {
        return (float) $this->stock_qty <= (float) $this->reorder_level;
    }

    public function toPosArray(?User $user = null): array
    {
        $data = [
            'id' => $this->id,
            'primary_part_number' => $this->primary_part_number,
            'name' => $this->name,
            'description' => $this->description,
            'brand' => $this->brand,
            'category' => $this->category?->name,
            'category_id' => $this->category_id,
            'barcode' => $this->barcode,
            'unit' => $this->unit,
            'tax_rate' => (float) $this->tax_rate,
            'selling_price' => (float) $this->selling_price,
            'secret_cost_code' => $this->secret_cost_code,
            'stock_qty' => $this->fitments->isEmpty() ? (float) $this->stock_qty : null,
            'stock_mode' => $this->fitments->isEmpty() ? 'single' : 'variant',
            'reorder_level' => (float) $this->reorder_level,
            'fitment_hint' => $this->fitmentHint(),
            'fitments' => $this->fitments->map(fn (ProductFitment $f) => [
                'id' => $f->id,
                'category_id' => $f->category_id,
                'category_name' => $f->category?->name,
                'brand' => $f->brand?->name,
                'model' => $f->model?->name,
                'year_from' => $f->year_from,
                'year_to' => $f->year_to,
                'mapped_part_number' => $f->mapped_part_number,
                'stock_qty' => $f->stock_qty === null ? null : (float) $f->stock_qty,
            ])->values(),
            'cost_pending' => $this->cost_pending,
            'track_stock' => $this->track_stock,
        ];

        if ($user?->canViewCost()) {
            $data['cost_price'] = (float) $this->cost_price;
            $data['margin'] = $this->margin();
        }

        return $data;
    }
}
