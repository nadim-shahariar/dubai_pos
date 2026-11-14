<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->string('base_role', 30)->default('cashier');
            $table->json('permissions')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $now = now();
        $roles = [
            ['Owner', 'owner', 'owner', ['view_dashboard', 'view_sales', 'return_sales', 'view_cost', 'approve', 'manage_catalog', 'sell', 'view_customers', 'manage_customers', 'collect_credit', 'view_reports', 'manage_inventory', 'manage_users', 'manage_settings']],
            ['Manager', 'manager', 'manager', ['view_dashboard', 'view_sales', 'return_sales', 'view_cost', 'approve', 'manage_catalog', 'sell', 'view_customers', 'manage_customers', 'collect_credit', 'view_reports', 'manage_inventory']],
            ['Cashier', 'cashier', 'cashier', ['view_dashboard', 'view_sales', 'sell', 'view_customers', 'collect_credit']],
            ['Storekeeper', 'storekeeper', 'storekeeper', ['view_dashboard', 'manage_catalog', 'manage_inventory']],
            ['Accountant', 'accountant', 'accountant', ['view_dashboard', 'view_cost', 'view_reports', 'collect_credit', 'view_customers']],
        ];

        foreach ($roles as [$name, $slug, $base, $permissions]) {
            DB::table('roles')->insert([
                'name' => $name,
                'slug' => $slug,
                'base_role' => $base,
                'permissions' => json_encode($permissions),
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
