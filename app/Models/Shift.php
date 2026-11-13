<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'user_id', 'opened_at', 'closed_at', 'opening_float', 'expected_cash',
        'counted_cash', 'variance', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'variance' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function cashMovements()
    {
        return $this->hasMany(CashMovement::class);
    }

    public static function openFor(User $user): ?self
    {
        return static::query()->where('user_id', $user->id)->where('status', 'open')->latest()->first();
    }
}
