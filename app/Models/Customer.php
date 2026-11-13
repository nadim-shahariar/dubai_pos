<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name', 'company_name', 'phone', 'email', 'trn', 'address', 'credit_limit',
        'payment_terms_days', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return ['credit_limit' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function collections()
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function outstandingBalance(): float
    {
        return (float) $this->sales()->where('balance_due', '>', 0)->sum('balance_due');
    }
}
