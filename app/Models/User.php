<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'pin', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token', 'pin'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isOwner(): bool { return $this->role === 'owner'; }
    public function isManager(): bool { return $this->role === 'manager'; }
    public function isCashier(): bool { return $this->role === 'cashier'; }
    public function isStorekeeper(): bool { return $this->role === 'storekeeper'; }
    public function isAccountant(): bool { return $this->role === 'accountant'; }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function canViewCost(): bool
    {
        return $this->hasRole('owner', 'manager', 'accountant');
    }

    public function canApprove(): bool
    {
        return $this->hasRole('owner', 'manager');
    }

    public function canManageCatalog(): bool
    {
        return $this->hasRole('owner', 'manager', 'storekeeper');
    }

    public function canSell(): bool
    {
        return $this->hasRole('owner', 'manager', 'cashier');
    }

    public function canViewReports(): bool
    {
        return $this->hasRole('owner', 'manager', 'accountant');
    }

    public function roleLabel(): string
    {
        return ucfirst($this->role);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }
}
