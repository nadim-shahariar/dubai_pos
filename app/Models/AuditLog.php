<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'event', 'auditable_type', 'auditable_id', 'meta', 'ip_address', 'created_at'];
    protected function casts(): array { return ['meta' => 'array', 'created_at' => 'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
}
