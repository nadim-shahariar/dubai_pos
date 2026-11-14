<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BulkStockReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('role', 'owner')->first());
    }

    private function payload(): array
    {
        return ['request_key' => (string) Str::uuid(), 'received_on' => now()->subDay()->toDateString(), 'reference' => 'SUPPLIER-1', 'items' => []];
    }

    public function test_multiple_variant_receipts_are_dated_atomic_and_safe_to_retry(): void
    {
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();
        [$a, $b] = $product->fitments()->get()->all();
        $beforeA = (float) $a->stock_qty;
        $beforeB = (float) $b->stock_qty;
        $data = $this->payload();
        $data['items'] = [
            ['product_id' => $product->id, 'product_fitment_id' => $a->id, 'qty' => 3, 'unit_cost' => 50],
            ['product_id' => $product->id, 'product_fitment_id' => $b->id, 'qty' => 5, 'unit_cost' => 70],
        ];
        $this->post(route('inventory.receipts.store'), $data)->assertRedirect();
        $receipt = StockReceipt::firstOrFail();
        $this->assertSame($data['received_on'], $receipt->received_on->toDateString());
        $this->assertSame($beforeA + 3, (float) $a->fresh()->stock_qty);
        $this->assertSame($beforeB + 5, (float) $b->fresh()->stock_qty);
        $this->assertSame(2, $receipt->items()->count());
        $this->post(route('inventory.receipts.store'), $data)->assertRedirect(route('inventory.receipts.show', $receipt));
        $this->assertDatabaseCount('stock_receipts', 1);
        $this->assertSame($beforeA + 3, (float) $a->fresh()->stock_qty);
        $this->get(route('inventory.receipts.show', $receipt))->assertOk()->assertSee('SUPPLIER-1');
        $this->get('/inventory/receipts?from='.$data['received_on'].'&to='.$data['received_on'])->assertOk()->assertSee($receipt->receipt_number);
        $this->get('/inventory/receipts?from='.now()->subYear()->toDateString().'&to='.now()->subYear()->toDateString())->assertOk()->assertDontSee($receipt->receipt_number);
        $this->assertDatabaseHas('stock_movements', ['reference_type' => 'stock_receipt', 'reference_id' => $receipt->id, 'product_fitment_id' => $a->id, 'qty_in' => 3]);
    }

    public function test_invalid_variant_rolls_back_all_selected_items(): void
    {
        $product = Product::where('primary_part_number', 'BP-101')->first();
        $variant = $product->fitments()->first();
        $before = $variant->stock_qty;
        $movements = StockMovement::count();
        $data = $this->payload();
        $data['items'] = [
            ['product_id' => $product->id, 'product_fitment_id' => $variant->id, 'qty' => 3, 'unit_cost' => 50],
            ['product_id' => $product->id, 'product_fitment_id' => 999999, 'qty' => 5, 'unit_cost' => 70],
        ];
        $this->postJson(route('inventory.receipts.store'), $data)->assertUnprocessable();
        $this->assertSame($before, $variant->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_receipts', 0);
        $this->assertSame($movements, StockMovement::count());
    }

    public function test_single_product_receipts_permissions_and_future_date_validation(): void
    {
        $product = Product::create(['name' => 'Single stock item', 'secret_cost_code' => 'BULKONE', 'selling_price' => 50, 'stock_qty' => 4, 'cost_price' => 20, 'track_stock' => true, 'is_active' => true]);
        $data = $this->payload();
        $data['items'] = [['product_id' => $product->id, 'qty' => 2, 'unit_cost' => 30]];
        $this->actingAs(User::where('role', 'cashier')->first())->postJson(route('inventory.receipts.store'), $data)->assertForbidden();
        $this->actingAs(User::where('role', 'storekeeper')->first())->post(route('inventory.receipts.store'), $data)->assertRedirect();
        $this->assertSame('6.000', $product->fresh()->stock_qty);
        $data['received_on'] = now()->addDay()->toDateString();
        $data['request_key'] = (string) Str::uuid();
        $this->postJson(route('inventory.receipts.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('received_on');
    }
}
