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
        $before = (float) $product->stock_qty;
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
        $before = (float) $product->stock_qty;
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
}
