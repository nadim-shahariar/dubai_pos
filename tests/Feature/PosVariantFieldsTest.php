<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosVariantFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('role', 'owner')->firstOrFail());
    }

    private function row(): array
    {
        return ['vehicle_brand' => 'New brand', 'vehicle_model' => 'New model', 'year_from' => 2020, 'year_to' => 2024, 'cost_price' => 25, 'stock_qty' => 4];
    }

    public function test_variant_can_select_existing_category_or_create_a_new_one_without_a_part_number(): void
    {
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();
        $category = Category::firstOrFail();
        $this->postJson('/api/pos/products/'.$product->id.'/variants', ['variants' => [$this->row() + ['category_name' => $category->name]]])->assertOk();
        $first = $product->fitments()->whereHas('model', fn ($q) => $q->where('name', 'New model'))->firstOrFail();
        $this->assertSame($category->id, $first->category_id);
        $this->assertNull($first->mapped_part_number);
        $this->assertSame('25.00', $first->cost_price);
        $this->assertSame('4.000', $first->stock_qty);
        $response = $this->postJson('/api/pos/products/'.$product->id.'/variants', ['variants' => [array_replace($this->row(), ['vehicle_model' => 'Another model', 'category_name' => 'Counter added category'])]])->assertOk();
        $new = Category::where('name', 'Counter added category')->firstOrFail();
        $this->assertDatabaseHas('product_fitments', ['product_id' => $product->id, 'category_id' => $new->id]);
        $this->assertTrue(collect($response->json('data'))->contains('category_name', 'Counter added category'));
        $this->assertEquals(4, collect($response->json('data'))->firstWhere('category_id', $new->id)['stock_qty']);
    }

    public function test_buying_price_and_stock_are_required_but_explicit_zero_is_valid(): void
    {
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();
        $url = '/api/pos/products/'.$product->id.'/variants';
        foreach (['cost_price', 'stock_qty'] as $field) {
            $row = $this->row();
            unset($row[$field]);
            $this->postJson($url, ['variants' => [$row]])->assertUnprocessable()->assertJsonValidationErrors('variants.0.'.$field);
            $row[$field] = -1;
            $this->postJson($url, ['variants' => [$row]])->assertUnprocessable()->assertJsonValidationErrors('variants.0.'.$field);
        }
        $this->postJson($url, ['variants' => [array_replace($this->row(), ['cost_price' => 0, 'stock_qty' => 0])]])->assertOk();
        $this->assertDatabaseHas('product_fitments', ['product_id' => $product->id, 'cost_price' => 0, 'stock_qty' => 0]);
    }

    public function test_cashier_can_enter_required_buying_price_without_receiving_saved_cost_values(): void
    {
        $this->actingAs(User::where('role', 'cashier')->firstOrFail());
        $response = $this->postJson('/api/pos/quick-add', [
            'name' => 'Counter created variant', 'selling_price' => 50, 'secret_cost_code' => 'CATEGORYTEST',
            'variants' => [$this->row() + ['category_name' => 'Counter category']],
        ])->assertCreated()->assertJsonMissingPath('product.cost_price')->assertJsonMissingPath('product.fitments.0.cost_price');
        $this->assertDatabaseHas('product_fitments', ['product_id' => $response->json('product.id'), 'cost_price' => 25, 'stock_qty' => 4]);
        $this->get('/pos')->assertOk()->assertSee('variant-category-list');
    }
}
