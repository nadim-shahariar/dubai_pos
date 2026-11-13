<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function log(string $event, ?Model $subject = null, array $meta = [], ?User $user = null): void
    {
        AuditLog::create([
            'user_id' => $user?->id ?? auth()->id(),
            'event' => $event,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
