<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSheetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('role', 'owner')->firstOrFail());
    }

    private function row(string $code): array
    {
        return ['name' => 'Sheet product '.$code, 'secret_cost_code' => $code, 'selling_price' => 50, 'cost_price' => 25, 'stock_qty' => 4, 'category_name' => 'Sheet category'];
    }

    public function test_both_add_products_and_quick_add_use_the_same_sheet(): void
    {
        $this->get('/products/create')->assertOk()->assertSee('data-product-sheet', false)->assertSee('product-sheet.js')->assertSee('Save all products');
        $this->get('/products')->assertOk()->assertSee('data-product-sheet', false)->assertSee('Quick add product')->assertDontSee('catalog-quick-form');
    }

    public function test_multiple_rows_save_stock_categories_and_optional_details_in_one_request(): void
    {
        $before = StockMovement::count();
        $this->postJson('/api/pos/products/batch', ['rows' => [
            $this->row('SHEETONE') + ['unit' => 'box', 'tax_rate' => 0, 'bin_location' => 'A-9', 'barcode' => 'SHEET-BARCODE'],
            array_replace($this->row('SHEETTWO'), ['stock_qty' => 0, 'cost_price' => 0]),
        ]])->assertCreated()->assertJsonPath('count', 2)->assertJsonCount(2, 'products');
        $first = Product::where('secret_cost_code', 'SHEETONE')->firstOrFail();
        $second = Product::where('secret_cost_code', 'SHEETTWO')->firstOrFail();
        $this->assertNull($first->primary_part_number);
        $this->assertSame('4.000', $first->stock_qty);
        $this->assertSame('box', $first->unit);
        $this->assertSame('0.00', $first->tax_rate);
        $this->assertSame('A-9', $first->bin_location);
        $this->assertSame($first->category_id, $second->category_id);
        $this->assertSame(1, Category::where('name', 'Sheet category')->count());
        $this->assertSame($before + 1, StockMovement::count());
    }

    public function test_invalid_second_row_rolls_back_first_product_stock_and_new_category(): void
    {
        $before = StockMovement::count();
        $invalid = $this->row('SHEETINVALID'); unset($invalid['stock_qty']);
        $this->postJson('/api/pos/products/batch', ['rows' => [$this->row('SHEETROLLBACK'), $invalid]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.1.stock_qty');
        $this->assertDatabaseMissing('products', ['secret_cost_code' => 'SHEETROLLBACK']);
        $this->assertDatabaseMissing('categories', ['name' => 'Sheet category']);
        $this->assertSame($before, StockMovement::count());
    }

    public function test_duplicate_codes_in_the_same_batch_are_rejected_without_partial_save(): void
    {
        $this->postJson('/api/pos/products/batch', ['rows' => [$this->row('samecode'), $this->row('SAMECODE')]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.1.secret_cost_code');
        $this->assertDatabaseMissing('products', ['secret_cost_code' => 'SAMECODE']);
    }

    public function test_variant_rows_keep_independent_stock_and_required_costs(): void
    {
        $row = $this->row('SHEETVARIANTS'); unset($row['stock_qty'], $row['cost_price']);
        $row['variants'] = [
            ['vehicle_brand' => 'Brand', 'vehicle_model' => 'A', 'year_from' => 2020, 'year_to' => 2024, 'stock_qty' => 2, 'cost_price' => 10],
            ['vehicle_brand' => 'Brand', 'vehicle_model' => 'B', 'year_from' => 2020, 'year_to' => 2024, 'stock_qty' => 3, 'cost_price' => 20],
        ];
        $this->postJson('/api/pos/products/batch', ['rows' => [$row]])->assertCreated();
        $product = Product::where('secret_cost_code', 'SHEETVARIANTS')->firstOrFail();
        $this->assertSame([2.0, 3.0], $product->fitments->map(fn ($f) => (float) $f->stock_qty)->all());
        $this->assertSame('16.00', $product->cost_price);
        $row['secret_cost_code'] = 'SHEETMISSINGCOST'; unset($row['variants'][1]['cost_price']);
        $this->postJson('/api/pos/products/batch', ['rows' => [$row]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.variants.1.cost_price');
        $this->assertDatabaseMissing('products', ['secret_cost_code' => 'SHEETMISSINGCOST']);
    }

    public function test_access_and_batch_limits_are_enforced(): void
    {
        $this->actingAs(User::where('role', 'cashier')->firstOrFail())->postJson('/api/pos/products/batch', ['rows' => [$this->row('SHEETDENIED')]])->assertForbidden();
        $this->actingAs(User::where('role', 'storekeeper')->firstOrFail())->postJson('/api/pos/products/batch', ['rows' => [$this->row('SHEETSTORE')]])->assertCreated()->assertJsonMissingPath('products.0.cost_price');
        $this->assertDatabaseHas('products', ['secret_cost_code' => 'SHEETSTORE', 'cost_price' => 25]);
        $this->postJson('/api/pos/products/batch', ['rows' => []])->assertUnprocessable();
        $this->postJson('/api/pos/products/batch', ['rows' => array_fill(0, 101, $this->row('TOOMANY'))])->assertUnprocessable();
    }
}
