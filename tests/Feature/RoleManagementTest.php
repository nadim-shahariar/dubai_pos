<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function owner(): User
    {
        return User::where('role', 'owner')->firstOrFail();
    }

    public function test_owner_creates_a_custom_role_and_staff_account_with_hashed_credentials(): void
    {
        $this->actingAs($this->owner())->post('/roles', ['name' => 'Stock operator', 'base_role' => 'owner', 'permissions' => ['manage_inventory']])->assertRedirect(route('roles.index'));
        $role = Role::where('name', 'Stock operator')->firstOrFail();
        $this->post('/users', ['name' => 'New staff', 'email' => 'staff@example.test', 'password' => 'StaffPassword123', 'pin' => '5678', 'role' => $role->slug, 'is_active' => 1])->assertRedirect(route('users.index'));
        $staff = User::where('email', 'staff@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('StaffPassword123', $staff->password));
        $this->assertTrue(Hash::check('5678', $staff->pin));
        $this->assertFalse($staff->isOwner());
        $this->actingAs($staff)->get('/inventory')->assertOk();
        $this->get('/inventory/receipts/create')->assertOk();
        $this->get('/products')->assertForbidden();
        $this->get('/reports')->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->get('/settings')->assertForbidden();
        $this->get('/pos')->assertForbidden();
        $this->postJson('/api/pos/sales', [])->assertForbidden();
    }

    public function test_custom_role_login_goes_to_an_allowed_screen_and_inactive_accounts_are_blocked(): void
    {
        $role = Role::create(['name' => 'Stock only', 'slug' => 'stock-only', 'base_role' => 'cashier', 'permissions' => ['manage_inventory']]);
        $staff = User::create(['name' => 'Stock staff', 'email' => 'stock@example.test', 'password' => 'Password123', 'role' => $role->slug, 'is_active' => true]);
        $this->post('/login', ['email' => $staff->email, 'password' => 'Password123'])->assertRedirect(route('inventory.index'));
        $staff->update(['is_active' => false]);
        $this->actingAs($staff->fresh())->get('/inventory')->assertForbidden();
    }

    public function test_last_owner_cannot_be_deactivated_or_demoted_and_system_roles_are_fixed(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->patchJson(route('users.update', $owner), ['name' => $owner->name, 'email' => $owner->email, 'role' => 'cashier', 'is_active' => 1])->assertUnprocessable();
        $this->patchJson(route('users.update', $owner), ['name' => $owner->name, 'email' => $owner->email, 'role' => 'owner', 'is_active' => 0])->assertUnprocessable();
        $this->patchJson(route('roles.update', Role::where('slug', 'owner')->first()), ['name' => 'Changed owner', 'base_role' => 'cashier', 'permissions' => []])->assertUnprocessable();
        $this->assertSame('owner', $owner->fresh()->role);
        $this->assertTrue($owner->fresh()->is_active);
    }

    public function test_a_limited_user_administrator_cannot_grant_more_permissions_than_they_have(): void
    {
        $role = Role::create(['name' => 'Limited admin', 'slug' => 'limited-admin', 'base_role' => 'owner', 'permissions' => ['manage_users']]);
        $staff = User::create(['name' => 'Limited admin', 'email' => 'limited@example.test', 'password' => 'Password123', 'role' => $role->slug, 'is_active' => true]);
        $this->actingAs($staff)->postJson('/roles', ['name' => 'Escalated role', 'base_role' => 'owner', 'permissions' => ['manage_users', 'manage_settings']])->assertForbidden();
        $this->postJson('/users', ['name' => 'Root staff', 'email' => 'root@example.test', 'password' => 'Password123', 'role' => 'owner', 'is_active' => 1])->assertForbidden();
        $this->get('/settings')->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'root@example.test']);
    }

    public function test_cashier_pin_is_not_a_manager_approval_pin(): void
    {
        $cashier = User::where('role', 'cashier')->first();
        $product = Product::where('primary_part_number', 'BP-101')->first();
        $this->actingAs($cashier)->postJson('/api/pos/sales', [
            'items' => [['product_id' => $product->id, 'product_fitment_id' => $product->fitments()->first()->id, 'qty' => 1, 'unit_price' => 125, 'discount_type' => 'percent', 'discount_value' => 20]],
            'payments' => [['method' => 'cash', 'amount' => 100]], 'approval_pin' => '1111',
        ])->assertUnprocessable();
    }

    public function test_read_only_custom_permissions_hide_cost_and_block_customer_changes(): void
    {
        $role = Role::create(['name' => 'Viewer', 'slug' => 'viewer', 'base_role' => 'manager', 'permissions' => ['view_dashboard', 'view_customers', 'view_reports']]);
        $staff = User::create(['name' => 'Viewer', 'email' => 'viewer@example.test', 'password' => 'Password123', 'role' => $role->slug, 'is_active' => true]);
        $this->actingAs($staff)->get('/dashboard')->assertOk()->assertDontSee('Gross profit today')->assertDontSee('Margin ');
        $this->get('/reports/profit')->assertForbidden();
        $this->get('/reports/inventory')->assertForbidden();
        $this->get('/customers')->assertOk()->assertDontSee('+ Add customer');
        $this->post('/customers', [])->assertForbidden();
        $this->postJson('/api/pos/customers', [])->assertForbidden();
        $role->update(['permissions' => ['view_sales']]);
        $this->assertSame('sales.index', $staff->fresh()->homeRoute());
    }
}
