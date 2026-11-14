<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'base_role', 'permissions', 'is_system'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'is_system' => 'boolean'];
    }

    public function users()
    {
        return $this->hasMany(User::class, 'role', 'slug');
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }

    public static function permissionList(): array
    {
        return [
            'view_dashboard' => 'View dashboard totals',
            'view_sales' => 'View invoices and print sales',
            'return_sales' => 'Issue returns and credit notes',
            'sell' => 'Use POS / create sales',
            'view_cost' => 'View buying cost and profit',
            'approve' => 'Approve discounts, voids and manager overrides',
            'manage_catalog' => 'Manage products, variants and catalogue',
            'view_customers' => 'View customers and statements',
            'manage_customers' => 'Create / edit customers and credit limits',
            'collect_credit' => 'Receive credit collections',
            'view_reports' => 'View reports and audit trail',
            'manage_inventory' => 'Receive and adjust stock',
            'manage_users' => 'Create users and custom roles',
            'manage_settings' => 'Change company settings',
        ];
    }
}
