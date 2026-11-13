<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosHold extends Model
{
    protected $fillable = ['reference', 'user_id', 'customer_id', 'cart_json', 'note', 'status', 'expires_at'];
    protected function casts(): array { return ['cart_json' => 'array', 'expires_at' => 'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
}
