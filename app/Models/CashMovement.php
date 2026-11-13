<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model
{
    public $timestamps = false;
    protected $fillable = ['shift_id', 'user_id', 'type', 'direction', 'amount', 'reason', 'created_at'];
    protected function casts(): array { return ['created_at' => 'datetime', 'amount' => 'decimal:2']; }
    public function shift() { return $this->belongsTo(Shift::class); }
    public function user() { return $this->belongsTo(User::class); }
}
