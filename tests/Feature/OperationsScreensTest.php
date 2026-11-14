<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('role', 'owner')->first());
    }

    public function test_catalogue_lists_each_variant_as_a_separate_row_and_searches_variant_part_numbers(): void
    {
        $product = Product::where('primary_part_number', 'OF-220')->first();
        [$first, $second] = $product->fitments()->get()->all();
        $this->get('/products?q=TOY-OF-220')->assertOk()
            ->assertSee('data-catalogue-variant="'.$first->id.'"', false)
            ->assertSee('data-catalogue-variant="'.$second->id.'"', false)
            ->assertSee('Corolla')->assertSee('Camry')->assertSee('Alt+N')->assertSee('Alt+L');
        $this->get('/products?filter=low')->assertOk()->assertSee('data-catalogue-variant="'.$second->id.'"', false)->assertDontSee('data-catalogue-variant="'.$first->id.'"', false);
    }

    public function test_sales_have_direct_print_actions_and_selected_row_shortcuts(): void
    {
        $this->get('/sales')->assertOk()->assertSee('Print invoice')->assertSee('data-invoice-print', false)->assertSee('data-receipt-print', false)->assertSee('Alt+P')->assertSee('print=1', false);
        $sale = Sale::first();
        $this->get(route('sales.invoice', [$sale, 'print' => 1]))->assertOk()->assertSee("window.addEventListener('load'", false);
        $this->get(route('sales.receipt', [$sale, 'print' => 1]))->assertOk()->assertSee("window.addEventListener('load'", false);
    }

    public function test_customer_statement_has_responsive_and_print_specific_markup(): void
    {
        $customer = Customer::first();
        $this->get(route('customers.statement', $customer))->assertOk()->assertSee('statement-page')->assertSee('statement-print-heading')->assertSee('operations.css')->assertSee('Print statement');
    }
}
