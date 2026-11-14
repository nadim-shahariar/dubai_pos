<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('roles')->where('is_system', true)->get() as $role) {
            $permissions = json_decode($role->permissions ?: '[]', true);
            $permissions[] = 'view_dashboard';
            if (in_array($role->slug, ['owner', 'manager', 'cashier'], true)) {
                $permissions[] = 'view_sales';
            }
            if (in_array($role->slug, ['owner', 'manager'], true)) {
                $permissions[] = 'return_sales';
            }
            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values(array_unique($permissions)))]);
        }
    }

    public function down(): void
    {
        // Retain system access when rolling back the compatibility backfill.
    }
};
