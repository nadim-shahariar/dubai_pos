<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalePayment extends Model
{
    public $timestamps = false;
    protected $fillable = ['sale_id', 'method', 'amount', 'reference', 'created_at'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'created_at' => 'datetime']; }
    public function sale() { return $this->belongsTo(Sale::class); }
}
