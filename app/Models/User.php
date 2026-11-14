<?php

namespace App\Models;

use App\Support\Access;
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

    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role', 'slug');
    }

    public function baseRole(): string
    {
        return $this->roleModel?->base_role ?: $this->role;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'owner') {
            return true;
        }

        return (bool) $this->roleModel?->hasPermission($permission);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isManager(): bool
    {
        return $this->baseRole() === 'manager';
    }

    public function isCashier(): bool
    {
        return $this->baseRole() === 'cashier';
    }

    public function isStorekeeper(): bool
    {
        return $this->baseRole() === 'storekeeper';
    }

    public function isAccountant(): bool
    {
        return $this->baseRole() === 'accountant';
    }

    public function canAccessRoute(string $route): bool
    {
        return Access::allows($this, $route);
    }

    public function homeRoute(): string
    {
        foreach (['dashboard', 'pos.index', 'products.index', 'inventory.index', 'sales.index', 'customers.index', 'reports.index', 'users.index', 'settings.edit'] as $route) {
            if ($this->canAccessRoute($route)) {
                return $route;
            }
        }

        return 'no-access';
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function canViewCost(): bool
    {
        return $this->hasPermission('view_cost');
    }

    public function canApprove(): bool
    {
        return $this->hasPermission('approve');
    }

    public function canManageCatalog(): bool
    {
        return $this->hasPermission('manage_catalog');
    }

    public function canSell(): bool
    {
        return $this->hasPermission('sell');
    }

    public function canCollectCredit(): bool
    {
        return $this->hasPermission('collect_credit');
    }

    public function canViewReports(): bool
    {
        return $this->hasPermission('view_reports');
    }

    public function canManageInventory(): bool
    {
        return $this->hasPermission('manage_inventory');
    }

    public function canManageUsers(): bool
    {
        return $this->hasPermission('manage_users');
    }

    public function canManageCustomers(): bool
    {
        return $this->hasPermission('manage_customers');
    }

    public function roleLabel(): string
    {
        return $this->roleModel?->name ?: ucfirst($this->role);
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
