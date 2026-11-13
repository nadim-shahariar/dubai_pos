<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\VoidSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OptionalPartNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_multiple_products_can_be_created_and_sold_without_part_numbers(): void
    {
        $cashier = User::where('role', 'cashier')->firstOrFail();
        $this->actingAs($cashier);
        foreach (['OPTIONALONE', 'OPTIONALTWO'] as $code) {
            $response = $this->postJson('/api/pos/quick-add', [
                'name' => 'Part without number '.$code, 'secret_cost_code' => $code, 'selling_price' => 50, 'stock_qty' => 3,
            ])->assertCreated()->assertJsonPath('product.primary_part_number', null);
            $id = $response->json('product.id');
            $this->postJson('/api/pos/sales', [
                'items' => [['product_id' => $id, 'qty' => 1, 'unit_price' => 50]],
                'payments' => [['method' => 'cash', 'amount' => 50]],
            ])->assertCreated();
            $this->assertSame('2.000', Product::findOrFail($id)->stock_qty);
        }
    }

    public function test_numberless_variants_use_ids_for_sale_and_void_and_keep_separate_stock(): void
    {
        $cashier = User::where('role', 'cashier')->firstOrFail();
        $response = $this->actingAs($cashier)->postJson('/api/pos/quick-add', [
            'name' => 'Two numberless variants', 'secret_cost_code' => 'NOVARIANTNO', 'selling_price' => 50,
            'variants' => [
                ['vehicle_brand' => 'TestBrand', 'vehicle_model' => 'ModelA', 'year_from' => 2020, 'year_to' => 2024, 'stock_qty' => 3],
                ['vehicle_brand' => 'TestBrand', 'vehicle_model' => 'ModelB', 'year_from' => 2020, 'year_to' => 2024, 'stock_qty' => 7],
            ],
        ])->assertCreated()->assertJsonPath('product.primary_part_number', null);
        $product = Product::findOrFail($response->json('product.id'));
        [$a, $b] = $product->fitments()->get()->all();
        $this->assertNull($a->mapped_part_number);
        $this->assertNull($b->mapped_part_number);
        $this->postJson('/api/pos/sales', [
            'items' => [['product_id' => $product->id, 'product_fitment_id' => $b->id, 'qty' => 1, 'unit_price' => 50]],
            'payments' => [['method' => 'cash', 'amount' => 50]],
        ])->assertCreated();
        $sale = Sale::latest('id')->firstOrFail();
        $this->get(route('sales.invoice', $sale))->assertOk()->assertSee('Two numberless variants');
        $this->assertSame('3.000', $a->fresh()->stock_qty);
        $this->assertSame('6.000', $b->fresh()->stock_qty);
        app(VoidSaleService::class)->void(User::where('role', 'manager')->firstOrFail(), $sale, null, 'Test cancellation');
        $this->assertSame('3.000', $a->fresh()->stock_qty);
        $this->assertSame('7.000', $b->fresh()->stock_qty);
    }
}
