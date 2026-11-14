<?php

namespace App\Support;

use App\Models\User;

class Access
{
    public static function allows(User $user, string $route): bool
    {
        $p = fn (string $permission) => $user->hasPermission($permission);

        return match (true) {
            $route === 'no-access' => true,
            $route === 'dashboard' => $p('view_dashboard'),
            str_starts_with($route, 'users.') || str_starts_with($route, 'roles.') => $p('manage_users'),
            str_starts_with($route, 'settings.') => $p('manage_settings'),
            str_starts_with($route, 'inventory.') => $p('manage_inventory'),
            str_starts_with($route, 'products.') || $route === 'api.pos.products.batch' => $p('manage_catalog'),
            in_array($route, ['reports.profit', 'reports.inventory'], true) => $p('view_reports') && $p('view_cost'),
            str_starts_with($route, 'reports.') || str_starts_with($route, 'audit.') => $p('view_reports'),
            str_starts_with($route, 'customers.collections.') || str_starts_with($route, 'credit-payments.') => $p('collect_credit'),
            $route === 'customers.statement' => $p('view_customers') || $p('collect_credit'),
            in_array($route, ['customers.edit', 'customers.update'], true) => $p('manage_customers'),
            in_array($route, ['customers.create', 'customers.store'], true) => $p('manage_customers') || $p('sell'),
            $route === 'customers.index' => $p('view_customers') || $p('manage_customers') || $p('collect_credit'),
            str_starts_with($route, 'sales.return') || str_starts_with($route, 'returns.') => $p('return_sales'),
            in_array($route, ['sales.show', 'sales.invoice', 'sales.receipt'], true) => $p('view_sales') || $p('sell'),
            str_starts_with($route, 'sales.') => $p('view_sales'),
            $route === 'api.pos.quick-add' => $p('sell') || $p('manage_catalog'),
            $route === 'api.pos.search' => $p('sell') || $p('manage_catalog') || $p('manage_inventory'),
            $route === 'api.pos.customers.store' => $p('manage_customers') || $p('sell'),
            str_starts_with($route, 'api.pos.customers') => $p('view_customers') || $p('sell'),
            str_starts_with($route, 'api.pos.') || $route === 'pos.index' => $p('sell'),
            default => false,
        };
    }
}
