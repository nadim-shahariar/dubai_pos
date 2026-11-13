<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariantInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_a_product_without_variants_uses_one_stock_balance(): void
    {
        $product = Product::create(['name' => 'Single stock part', 'primary_part_number' => 'SINGLE-1', 'secret_cost_code' => 'SINGLECODE', 'selling_price' => 100, 'cost_price' => 50, 'stock_qty' => 4, 'track_stock' => true, 'is_active' => true, 'tax_rate' => 5]);
        $cashier = User::where('role', 'cashier')->firstOrFail();
        app(StockService::class)->receive($product, $cashier, 3, 50);
        app(SaleService::class)->complete($cashier, [
            'items' => [['product_id' => $product->id, 'qty' => 2, 'unit_price' => 100]],
            'payments' => [['method' => 'cash', 'amount' => 200]],
        ]);
        $this->assertSame('5.000', $product->fresh()->stock_qty);
        $this->assertCount(0, $product->fitments);
    }

    public function test_first_counted_variant_quantity_is_saved_and_variant_selection_cannot_be_bypassed(): void
    {
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();
        $variant = $product->fitments()->firstOrFail();
        $before = (float) $product->fitments()->where('id', '!=', $variant->id)->sum('stock_qty');
        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner)->patch(route('inventory.variant-stock', [$product, $variant]), ['stock_qty' => 3])->assertRedirect();
        $this->assertSame('3.000', $variant->fresh()->stock_qty);
        $this->assertSame($before + 3, (float) $product->fresh()->stock_qty);
        $cashier = User::where('role', 'cashier')->firstOrFail();
        foreach ([null, 'UNKNOWN-SKU'] as $sku) {
            $this->actingAs($cashier)->postJson('/api/pos/sales', [
                'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_price' => 125, 'variant_sku' => $sku]],
                'payments' => [['method' => 'cash', 'amount' => 125]],
            ])->assertUnprocessable();
        }
        $this->assertSame('3.000', $variant->fresh()->stock_qty);
    }

    public function test_receipt_changes_only_selected_variant_and_aggregate_stock(): void
    {
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();
        [$first, $second] = $product->fitments()->get()->all();
        $first->update(['stock_qty' => 2, 'cost_price' => 50]);
        $second->update(['stock_qty' => 4]);
        $before = (float) $product->fitments()->sum('stock_qty');
        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner)->post(route('inventory.receive', $product), [
            'product_fitment_id' => $first->id, 'qty' => 3, 'unit_cost' => 100,
        ])->assertRedirect();
        $this->assertSame('5.000', $first->fresh()->stock_qty);
        $this->assertSame('80.00', $first->fresh()->cost_price);
        $this->assertSame('4.000', $second->fresh()->stock_qty);
        $this->assertSame($before + 3, (float) $product->fresh()->stock_qty);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'product_fitment_id' => $first->id, 'type' => 'purchase_receipt', 'qty_in' => 3]);
        $this->postJson(route('inventory.receive', $product), ['qty' => 1, 'unit_cost' => 100])->assertUnprocessable();
        $this->postJson(route('inventory.receive', $product), ['product_fitment_id' => 999999, 'qty' => 1, 'unit_cost' => 100])->assertUnprocessable();
        $this->postJson(route('inventory.adjust', $product), ['new_qty' => 100])->assertUnprocessable();
        $this->get(route('inventory.show', $product))->assertOk()->assertSee('Receive into variant');
    }
    public function test_a_variant_never_borrows_stock_from_other_variants_or_product_cache(): void
    {
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();
        [$a, $b] = $product->fitments()->get()->all();
        $a->update(['stock_qty' => 2]);
        $b->update(['stock_qty' => 7]);
        $product->update(['stock_qty' => 0]); // Stale aggregate must not prevent an available SKU sale.
        $cashier = User::where('role', 'cashier')->firstOrFail();
        $payload = fn ($sku, $qty) => [
            'items' => [['product_id' => $product->id, 'variant_sku' => $sku, 'qty' => $qty, 'unit_price' => 125]],
            'payments' => [['method' => 'cash', 'amount' => 125 * $qty]],
        ];
        $this->actingAs($cashier)->postJson('/api/pos/sales', $payload($a->mapped_part_number, 1))->assertCreated();
        $this->assertSame('1.000', $a->fresh()->stock_qty);
        $this->assertSame('7.000', $b->fresh()->stock_qty);
        $product->update(['stock_qty' => 999]); // Neither parent nor sibling quantity can fund A.
        $this->postJson('/api/pos/sales', $payload($a->mapped_part_number, 2))->assertUnprocessable();
        $this->assertSame('1.000', $a->fresh()->stock_qty);
        $this->assertSame('7.000', $b->fresh()->stock_qty);
        $this->postJson('/api/pos/sales', $payload($b->mapped_part_number, 3))->assertCreated();
        $this->assertSame('1.000', $a->fresh()->stock_qty);
        $this->assertSame('4.000', $b->fresh()->stock_qty);
        $this->assertDatabaseHas('stock_movements', ['product_fitment_id' => $b->id, 'qty_out' => 3, 'variant_balance_after' => 4]);
    }

    public function test_unset_variant_stock_cannot_inherit_any_combined_quantity(): void
    {
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();
        $variant = $product->fitments()->firstOrFail();
        $variant->update(['stock_qty' => null]);
        $product->update(['stock_qty' => 999]);
        $cashier = User::where('role', 'cashier')->firstOrFail();
        $this->actingAs($cashier)->postJson('/api/pos/sales', [
            'items' => [['product_id' => $product->id, 'variant_sku' => $variant->mapped_part_number, 'qty' => 1, 'unit_price' => 125]],
            'payments' => [['method' => 'cash', 'amount' => 125]],
        ])->assertUnprocessable();
        $this->assertNull($variant->fresh()->stock_qty);
        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner)->get(route('inventory.show', $product))->assertOk()->assertSee('Not set')->assertDontSee('Inherit product');
    }
    public function test_variants_with_the_same_part_number_keep_separate_identity_through_sale_return_and_void(): void
    {
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();
        [$a, $b] = $product->fitments()->get()->all();
        $a->update(['mapped_part_number' => 'SHARED-PART', 'stock_qty' => 5]);
        $b->update(['mapped_part_number' => 'SHARED-PART', 'stock_qty' => 8]);
        $cashier = User::where('role', 'cashier')->firstOrFail();
        $manager = User::where('role', 'manager')->firstOrFail();
        $payload = [
            'items' => [['product_id' => $product->id, 'product_fitment_id' => $b->id, 'variant_sku' => 'SHARED-PART', 'qty' => 1, 'unit_price' => 125]],
            'payments' => [['method' => 'cash', 'amount' => 125]],
        ];
        $sale = app(SaleService::class)->complete($cashier, $payload);
        $this->assertEquals($b->id, $sale->items->first()->product_fitment_id);
        $this->assertSame('5.000', $a->fresh()->stock_qty);
        $this->assertSame('7.000', $b->fresh()->stock_qty);
        app(\App\Services\ReturnService::class)->create($manager, $sale, [['sale_item_id' => $sale->items->first()->id, 'qty' => 1, 'restock' => true]], 'Wrong item', 'cash');
        $this->assertSame('5.000', $a->fresh()->stock_qty);
        $this->assertSame('8.000', $b->fresh()->stock_qty);
        $sale = app(SaleService::class)->complete($cashier, $payload);
        app(\App\Services\VoidSaleService::class)->void($manager, $sale, null, 'Test void');
        $this->assertSame('5.000', $a->fresh()->stock_qty);
        $this->assertSame('8.000', $b->fresh()->stock_qty);
        $this->actingAs($cashier)->postJson('/api/pos/sales', [
            'items' => [['product_id' => $product->id, 'variant_sku' => 'SHARED-PART', 'qty' => 1, 'unit_price' => 125]],
            'payments' => [['method' => 'cash', 'amount' => 125]],
        ])->assertUnprocessable();
        $data = $product->fresh()->toPosArray($cashier);
        $this->assertEquals([$a->id, $b->id], collect($data['fitments'])->pluck('id')->all());
    }
}
