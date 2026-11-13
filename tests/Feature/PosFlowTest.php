<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Product;
use App\Models\ProductFitment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function cashier(): User
    {
        return User::where('email', 'cashier@gitgadgets.test')->firstOrFail();
    }

    private function manager(): User
    {
        return User::where('email', 'manager@gitgadgets.test')->firstOrFail();
    }

    public function test_cashier_can_complete_a_split_payment_sale_and_stock_decrements(): void
    {
        $cashier = $this->cashier();
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();
        $before = (float) $product->stock_qty;

        $response = $this->actingAs($cashier)->postJson('/api/pos/sales', [
            'items' => [[
                'product_id' => $product->id,
                'qty' => 2,
                'unit_price' => 125,
                'discount_type' => 'none',
                'discount_value' => 0,
            ]],
            'payments' => [
                ['method' => 'cash', 'amount' => 200, 'tendered' => 200],
                ['method' => 'card', 'amount' => 50, 'reference' => 'AUTH-1'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('sale.total', 250)
            ->assertJsonPath('sale.vat_total', 11.9)
            ->assertJsonPath('sale.change_total', 0)
            ->assertJsonPath('sale.payment_status', 'paid');

        $product->refresh();
        $this->assertSame($before - 2, (float) $product->stock_qty);
        $this->assertStringStartsWith('GG-2026-', Sale::latest('id')->first()->invoice_number);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'sale', 'qty_out' => 2]);
    }

    public function test_sale_stores_the_selected_variant_snapshot(): void
    {
        $cashier = $this->cashier();
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();

        $this->actingAs($cashier)->postJson('/api/pos/sales', [
            'items' => [[
                'product_id' => $product->id,
                'qty' => 1,
                'unit_price' => 125,
                'variant_label' => 'Toyota Camry 2018–2024',
                'variant_sku' => 'TOY-BP-001',
            ]],
            'payments' => [['method' => 'cash', 'amount' => 125, 'tendered' => 125]],
        ])->assertCreated();

        $this->assertDatabaseHas('sale_items', [
            'primary_part_number' => 'BP-101',
            'variant_label' => 'Toyota Camry 2018–2024',
            'variant_sku' => 'TOY-BP-001',
        ]);
    }

    public function test_cashier_search_hides_numeric_cost(): void
    {
        $response = $this->actingAs($this->cashier())->getJson('/api/pos/search?q=BP-101');

        $response->assertOk()
            ->assertJsonPath('data.0.primary_part_number', 'BP-101')
            ->assertJsonMissingPath('data.0.cost_price')
            ->assertJsonPath('data.0.secret_cost_code', 'MAE');
    }

    public function test_manager_search_can_see_cost(): void
    {
        $response = $this->actingAs($this->manager())->getJson('/api/pos/search?q=BP-101');

        $response->assertOk()->assertJsonPath('data.0.cost_price', 82);
    }

    public function test_a_discount_above_the_cashier_limit_requires_a_manager_pin(): void
    {
        $cashier = $this->cashier();
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();
        $payload = [
            'items' => [[
                'product_id' => $product->id,
                'qty' => 1,
                'unit_price' => 125,
                'discount_type' => 'percent',
                'discount_value' => 20,
            ]],
            'payments' => [['method' => 'cash', 'amount' => 100, 'tendered' => 100]],
        ];

        $this->actingAs($cashier)->postJson('/api/pos/sales', $payload)->assertStatus(422);

        $this->actingAs($cashier)->postJson('/api/pos/sales', $payload + ['approval_pin' => '1234'])
            ->assertCreated()
            ->assertJsonPath('sale.total', 100);
    }

    public function test_quick_add_creates_a_sellable_product_and_marks_cost_pending_for_cashier(): void
    {
        $response = $this->actingAs($this->cashier())->postJson('/api/pos/quick-add', [
            'name' => 'Rear Brake Shoe',
            'primary_part_number' => 'BS-900',
            'selling_price' => 140,
            'secret_cost_code' => 'SHOE',
            'stock_qty' => 4,
            'category_id' => null,
            'brand' => 'Aftermarket',
        ]);

        $response->assertCreated()
            ->assertJsonPath('product.primary_part_number', 'BS-900')
            ->assertJsonPath('product.cost_pending', true)
            ->assertJsonMissingPath('product.cost_price');

        $this->assertDatabaseHas('products', ['primary_part_number' => 'BS-900', 'cost_pending' => 1, 'stock_qty' => 4]);
        $this->assertDatabaseHas('stock_movements', ['type' => 'opening', 'qty_in' => 4]);
    }

    public function test_invoice_does_not_expose_secret_code_or_cost(): void
    {
        $sale = Sale::firstOrFail();

        $response = $this->actingAs($this->cashier())->get(route('sales.invoice', $sale));

        $response->assertOk()
            ->assertDontSee('MAE')
            ->assertDontSee('cost_price');
    }

    public function test_manager_can_issue_a_credit_note_and_restock(): void
    {
        $sale = Sale::whereHas('items', fn ($q) => $q->where('primary_part_number', 'BP-101'))->firstOrFail();
        $item = $sale->items()->where('primary_part_number', 'BP-101')->firstOrFail();
        $before = (float) $item->product->stock_qty;

        $response = $this->actingAs($this->manager())->post(route('sales.return.store', $sale), [
            'items' => [[
                'sale_item_id' => $item->id,
                'qty' => 1,
                'restock' => '1',
                'condition' => 'resellable',
            ]],
            'reason' => 'Wrong part',
            'refund_method' => 'cash',
        ]);

        $response->assertRedirect(route('sales.show', $sale));
        $this->assertDatabaseHas('returns', ['sale_id' => $sale->id, 'reason' => 'Wrong part']);
        $this->assertDatabaseHas('return_items', ['sale_item_id' => $item->id, 'qty' => 1]);
        $this->assertSame($before + 1, (float) $item->product->fresh()->stock_qty);
    }



    public function test_storekeeper_can_quick_add_a_product_from_the_catalogue(): void
    {
        $storekeeper = User::where('email', 'store@gitgadgets.test')->firstOrFail();

        $this->actingAs($storekeeper)->postJson('/api/pos/quick-add', [
            'name' => 'Catalogue Quick Add',
            'primary_part_number' => 'CAT-QA-001',
            'selling_price' => 75,
            'secret_cost_code' => 'CATQA',
            'stock_qty' => 3,
            'variants' => [[
                'vehicle_brand' => 'Mazda',
                'vehicle_model' => 'Mazda2',
                'year_from' => 2020,
                'year_to' => 2024,
                'mapped_part_number' => 'CAT-QA-M2',
            ]],
        ])->assertCreated();

        $this->assertDatabaseHas('products', ['primary_part_number' => 'CAT-QA-001', 'cost_pending' => 1]);
        $this->assertDatabaseHas('product_fitments', ['mapped_part_number' => 'CAT-QA-M2']);
    }

    public function test_variant_stock_is_separate_from_product_stock(): void
    {
        $cashier = $this->cashier();
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();
        $fitment = ProductFitment::where('product_id', $product->id)->where('mapped_part_number', 'TOY-BP-001')->firstOrFail();
        $fitment->update(['stock_qty' => 2]);
        $productBefore = (float) $product->stock_qty;

        $this->actingAs($cashier)->postJson('/api/pos/sales', [
            'items' => [[
                'product_id' => $product->id,
                'qty' => 1,
                'unit_price' => 125,
                'variant_label' => 'Toyota Camry 2018–2024',
                'variant_sku' => 'TOY-BP-001',
            ]],
            'payments' => [['method' => 'cash', 'amount' => 125, 'tendered' => 125]],
        ])->assertCreated();

        $this->assertSame(1.0, (float) $fitment->fresh()->stock_qty);
        $this->assertSame($productBefore - 1, (float) $product->fresh()->stock_qty);
        $this->assertDatabaseHas('stock_movements', ['product_fitment_id' => $fitment->id, 'qty_out' => 1]);

        $this->actingAs($cashier)->postJson('/api/pos/sales', [
            'items' => [[
                'product_id' => $product->id,
                'qty' => 2,
                'unit_price' => 125,
                'variant_label' => 'Toyota Camry 2018–2024',
                'variant_sku' => 'TOY-BP-001',
            ]],
            'payments' => [['method' => 'cash', 'amount' => 250, 'tendered' => 250]],
        ])->assertStatus(422)->assertJsonValidationErrors('stock');

        $this->assertSame(1.0, (float) $fitment->fresh()->stock_qty);
        $this->assertSame($productBefore - 1, (float) $product->fresh()->stock_qty);
    }


    public function test_recent_sales_and_sale_metadata_edit_are_available_from_pos(): void
    {
        $cashier = $this->cashier();
        $sale = Sale::where('status', 'completed')->where('user_id', $cashier->id)->latest('id')->firstOrFail();

        $this->actingAs($cashier)->getJson('/api/pos/recent-sales')
            ->assertOk()
            ->assertJsonFragment(['invoice_number' => $sale->invoice_number]);

        $this->actingAs($cashier)->patchJson('/api/pos/sales/'.$sale->id, [
            'vehicle' => 'Toyota Camry · TEST-PLATE',
            'notes' => 'Edited from recent transactions',
        ])->assertOk();

        $sale->refresh();
        $this->assertSame('Toyota Camry · TEST-PLATE', $sale->vehicle);
        $this->assertSame('Edited from recent transactions', $sale->notes);
    }

    public function test_manager_can_void_a_recent_sale_and_stock_is_restored(): void
    {
        $manager = $this->manager();
        $sale = Sale::where('status', 'completed')->whereDoesntHave('returns')->whereDoesntHave('collections')->latest('id')->firstOrFail();
        $sale->load('items');
        $beforeStocks = [];
        foreach ($sale->items as $item) {
            if ($item->product_id) {
                $product = Product::find($item->product_id);
                if ($product && $product->track_stock) {
                    $beforeStocks[$item->product_id] = (float) $product->stock_qty;
                }
            }
        }

        $this->actingAs($manager)->postJson('/api/pos/sales/'.$sale->id.'/void', [
            'reason' => 'Duplicate test invoice',
        ])->assertOk()->assertJsonPath('sale.status', 'voided');

        $sale->refresh();
        $this->assertSame('voided', $sale->status);
        $this->assertSame(0.0, (float) $sale->balance_due);

        foreach ($sale->items as $item) {
            if (isset($beforeStocks[$item->product_id])) {
                $this->assertSame($beforeStocks[$item->product_id] + (float) $item->qty, (float) Product::find($item->product_id)->stock_qty);
            }
        }

        $this->assertDatabaseHas('stock_movements', ['reference_type' => 'void', 'reference_id' => $sale->id]);
    }

    public function test_cashier_needs_a_manager_pin_to_void_a_sale(): void
    {
        $cashier = $this->cashier();
        $sale = Sale::where('status', 'completed')->where('user_id', $cashier->id)->latest('id')->firstOrFail();

        $this->actingAs($cashier)->postJson('/api/pos/sales/'.$sale->id.'/void', [
            'reason' => 'Cashier attempt',
        ])->assertStatus(422)->assertJsonValidationErrors('approval_pin');

        $this->assertNotSame('voided', $sale->fresh()->status);
    }

    public function test_cashier_can_complete_a_credit_sale_for_a_customer(): void
    {
        $cashier = $this->cashier();
        $customer = Customer::where('credit_limit', '>', 0)->firstOrFail();
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();

        $this->actingAs($cashier)->postJson('/api/pos/sales', [
            'customer_id' => $customer->id,
            'items' => [[
                'product_id' => $product->id,
                'qty' => 1,
                'unit_price' => 125,
            ]],
            'payments' => [['method' => 'credit', 'amount' => 125]],
        ])->assertCreated()
            ->assertJsonPath('sale.payment_status', 'credit')
            ->assertJsonPath('sale.paid_total', 0)
            ->assertJsonPath('sale.balance_due', 125);

        $this->assertDatabaseHas('sale_payments', ['method' => 'credit', 'amount' => 125]);
    }

    public function test_credit_sale_requires_a_registered_customer(): void
    {
        $cashier = $this->cashier();
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();

        $this->actingAs($cashier)->postJson('/api/pos/sales', [
            'items' => [[
                'product_id' => $product->id,
                'qty' => 1,
                'unit_price' => 125,
            ]],
            'payments' => [['method' => 'credit', 'amount' => 125]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('customer_id');
    }

    public function test_credit_collection_updates_invoice_balance_and_generates_a_receipt(): void
    {
        $cashier = $this->cashier();
        $sale = Sale::where('balance_due', '>', 0)->firstOrFail();
        $customer = Customer::findOrFail($sale->customer_id);
        $originalBalance = (float) $sale->balance_due;

        $response = $this->actingAs($cashier)->post('/customers/'.$customer->id.'/collections', [
            'sale_id' => $sale->id,
            'amount' => 40,
            'method' => 'cash',
            'reference' => 'COLLECT-1',
        ]);

        $payment = CustomerPayment::latest('id')->firstOrFail();
        $response->assertRedirect(route('credit-payments.receipt', $payment));

        $this->assertSame(40.0, (float) $payment->amount);
        $this->assertStringStartsWith('CR-GG-', $payment->receipt_number);
        $this->assertSame($originalBalance - 40, (float) $sale->fresh()->balance_due);
        $this->assertSame('partial', $sale->fresh()->payment_status);
        $this->assertSame($originalBalance - 40, (float) $payment->sale->balance_due);

        $this->actingAs($cashier)->get(route('credit-payments.receipt', $payment))
            ->assertOk()
            ->assertSee($payment->receipt_number)
            ->assertSee('Balance remaining');

        $this->actingAs($cashier)->post('/customers/'.$customer->id.'/collections', [
            'sale_id' => $sale->id,
            'amount' => $originalBalance - 40,
            'method' => 'card',
        ])->assertRedirect();

        $this->assertSame(0.0, (float) $sale->fresh()->balance_due);
        $this->assertSame('paid', $sale->fresh()->payment_status);
        $this->assertSame(2, CustomerPayment::where('sale_id', $sale->id)->count());
    }

    public function test_cash_credit_report_and_customer_statement_are_available(): void
    {
        $accountant = User::where('email', 'accounts@gitgadgets.test')->firstOrFail();
        $sale = Sale::where('balance_due', '>', 0)->firstOrFail();

        $this->actingAs($accountant)->get('/reports/cash-credit')
            ->assertOk()
            ->assertSee('Cash & Credit Report')
            ->assertSee('Customer credit balances');

        $this->actingAs($accountant)->get('/customers/'.$sale->customer_id.'/statement')
            ->assertOk()
            ->assertSee('Credit invoices')
            ->assertSee($sale->invoice_number);
    }

    public function test_collection_cannot_exceed_the_invoice_balance(): void
    {
        $cashier = $this->cashier();
        $sale = Sale::where('balance_due', '>', 0)->firstOrFail();
        $customer = Customer::findOrFail($sale->customer_id);

        $this->actingAs($cashier)->post('/customers/'.$customer->id.'/collections', [
            'sale_id' => $sale->id,
            'amount' => (float) $sale->balance_due + 1,
            'method' => 'cash',
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, CustomerPayment::count());
        $this->assertSame((float) $sale->balance_due, (float) $sale->fresh()->balance_due);
    }

    public function test_cashier_cannot_collect_against_another_customers_invoice(): void
    {
        $cashier = $this->cashier();
        $sale = Sale::where('balance_due', '>', 0)->firstOrFail();
        $other = Customer::create(['name' => 'Other Customer', 'credit_limit' => 1000, 'is_active' => true]);

        $this->actingAs($cashier)->post('/customers/'.$other->id.'/collections', [
            'sale_id' => $sale->id,
            'amount' => 1,
            'method' => 'cash',
        ])->assertNotFound();
    }

    public function test_cashier_cannot_access_reports_or_the_catalogue_back_office(): void
    {
        $this->actingAs($this->cashier())->get('/reports/sales')->assertForbidden();
        $this->actingAs($this->cashier())->get('/products')->assertForbidden();
    }

    public function test_cashier_can_open_the_pos_when_a_shift_is_open(): void
    {
        $this->assertNotNull(Shift::openFor($this->cashier()));

        $this->actingAs($this->cashier())->get('/pos')
            ->assertOk()
            ->assertSee('Product name / key')
            ->assertSee('Walk-in Customer');
    }

    public function test_quick_add_saves_a_new_part_brand_and_vehicle_fitment(): void
    {
        $response = $this->actingAs($this->cashier())->postJson('/api/pos/quick-add', [
            'name' => 'Genesis G80 Brake Pad',
            'primary_part_number' => 'GEN-001',
            'selling_price' => 180,
            'secret_cost_code' => 'GEN',
            'stock_qty' => 2,
            'brand' => 'ACME Parts',
            'vehicle_brand' => 'Genesis',
            'vehicle_model' => 'G80',
            'year_from' => 2021,
            'year_to' => 2024,
            'mapped_part_number' => 'GEN-BP-001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('product.brand', 'ACME Parts')
            ->assertJsonPath('product.fitments.0.brand', 'Genesis')
            ->assertJsonPath('product.fitments.0.model', 'G80');

        $this->assertDatabaseHas('car_brands', ['name' => 'Genesis']);
        $this->assertDatabaseHas('car_models', ['name' => 'G80']);
        $this->assertDatabaseHas('products', ['primary_part_number' => 'GEN-001', 'brand' => 'ACME Parts']);
        $this->assertDatabaseHas('product_fitments', ['mapped_part_number' => 'GEN-BP-001']);

        $this->actingAs($this->cashier())->getJson('/api/pos/search?q=Genesis%20G80')
            ->assertOk()
            ->assertJsonPath('data.0.primary_part_number', 'GEN-001');
    }

    public function test_owner_can_add_a_fitment_with_a_new_vehicle_brand_and_model(): void
    {
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();

        $response = $this->actingAs(User::where('email', 'owner@gitgadgets.test')->firstOrFail())
            ->post(route('products.fitments.store', $product), [
                'variants' => [
                    ['vehicle_brand' => 'BYD', 'vehicle_model' => 'Seal', 'year_from' => 2023, 'year_to' => 2025, 'mapped_part_number' => 'BYD-BP-101'],
                    ['vehicle_brand' => 'BYD', 'vehicle_model' => 'Atto 3', 'year_from' => 2023, 'year_to' => 2025, 'mapped_part_number' => 'BYD-BP-102'],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('car_brands', ['name' => 'BYD']);
        $this->assertDatabaseHas('car_models', ['name' => 'Seal']);
        $this->assertDatabaseHas('car_models', ['name' => 'Atto 3']);
        $this->assertDatabaseHas('product_fitments', ['product_id' => $product->id, 'mapped_part_number' => 'BYD-BP-101']);
        $this->assertDatabaseHas('product_fitments', ['product_id' => $product->id, 'mapped_part_number' => 'BYD-BP-102']);
    }

    public function test_quick_add_accepts_multiple_variants_in_one_request(): void
    {
        $response = $this->actingAs($this->cashier())->postJson('/api/pos/quick-add', [
            'name' => 'Multi Fitment Filter',
            'primary_part_number' => 'MULTI-001',
            'selling_price' => 75,
            'secret_cost_code' => 'MULTI',
            'stock_qty' => 3,
            'variants' => [
                ['vehicle_brand' => 'Toyota', 'vehicle_model' => 'Hilux', 'year_from' => 2018, 'year_to' => 2024, 'mapped_part_number' => 'TOY-HILUX-001'],
                ['vehicle_brand' => 'Ford', 'vehicle_model' => 'F-150', 'year_from' => 2019, 'year_to' => 2024, 'mapped_part_number' => 'FOR-F150-001'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('product.fitments.0.model', 'Hilux')
            ->assertJsonPath('product.fitments.1.model', 'F-150');

        $this->assertDatabaseHas('product_fitments', ['mapped_part_number' => 'TOY-HILUX-001']);
        $this->assertDatabaseHas('product_fitments', ['mapped_part_number' => 'FOR-F150-001']);
        $this->assertSame(2, \App\Models\Product::where('primary_part_number', 'MULTI-001')->firstOrFail()->fitments()->count());
    }

    public function test_owner_can_create_a_product_with_multiple_variants_in_one_form(): void
    {
        $response = $this->actingAs(User::where('email', 'owner@gitgadgets.test')->firstOrFail())
            ->post('/products', [
                'primary_part_number' => 'FORM-001',
                'name' => 'Form Variant Part',
                'category_id' => 1,
                'brand' => 'Form Brand',
                'unit' => 'piece',
                'tax_rate' => 5,
                'selling_price' => 100,
                'cost_price' => 60,
                'secret_cost_code' => 'FORM',
                'stock_qty' => 2,
                'reorder_level' => 1,
                'is_active' => 1,
                'track_stock' => 1,
                'variants' => [
                    ['vehicle_brand' => 'Kia', 'vehicle_model' => 'Sportage', 'year_from' => 2020, 'year_to' => 2024, 'mapped_part_number' => 'KIA-SPORT-001'],
                    ['vehicle_brand' => 'Hyundai', 'vehicle_model' => 'Tucson', 'year_from' => 2021, 'year_to' => 2024, 'mapped_part_number' => 'HYU-TUC-001'],
                ],
            ]);

        $response->assertRedirect();
        $product = Product::where('primary_part_number', 'FORM-001')->firstOrFail();
        $this->assertSame(2, $product->fitments()->count());
        $this->assertDatabaseHas('car_brands', ['name' => 'Kia']);
        $this->assertDatabaseHas('car_models', ['name' => 'Sportage']);
    }

    public function test_variant_buying_price_is_saved_and_used_for_cogs(): void
    {
        $owner = User::where('email', 'owner@gitgadgets.test')->firstOrFail();
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();

        $this->actingAs($owner)->postJson('/api/pos/products/'.$product->id.'/variants', [
            'variants' => [[
                'vehicle_brand' => 'Mazda',
                'vehicle_model' => 'Mazda3',
                'year_from' => 2020,
                'year_to' => 2024,
                'mapped_part_number' => 'MAZ-BP-001',
                'cost_price' => 55.25,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('product_fitments', [
            'product_id' => $product->id,
            'mapped_part_number' => 'MAZ-BP-001',
            'cost_price' => 55.25,
        ]);

        $this->actingAs($this->cashier())->postJson('/api/pos/sales', [
            'items' => [[
                'product_id' => $product->id,
                'qty' => 1,
                'unit_price' => 125,
                'variant_label' => 'Mazda Mazda3 2020–2024',
                'variant_sku' => 'MAZ-BP-001',
            ]],
            'payments' => [['method' => 'cash', 'amount' => 125, 'tendered' => 125]],
        ])->assertCreated();

        $this->assertDatabaseHas('sale_items', [
            'variant_sku' => 'MAZ-BP-001',
            'cost_price' => 55.25,
        ]);
    }

    public function test_cashier_can_create_multiple_variants_for_an_existing_product_from_pos(): void
    {
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();

        $response = $this->actingAs($this->cashier())
            ->postJson('/api/pos/products/'.$product->id.'/variants', [
                'variants' => [
                    ['vehicle_brand' => 'Mazda', 'vehicle_model' => 'Mazda6', 'year_from' => 2020, 'year_to' => 2024, 'mapped_part_number' => 'MAZ-BP-101'],
                    ['vehicle_brand' => 'Mazda', 'vehicle_model' => 'CX-5', 'year_from' => 2021, 'year_to' => 2024, 'mapped_part_number' => 'MAZ-BP-102'],
                ],
            ]);

        $response->assertOk()->assertJsonCount(4, 'data');
        $this->assertDatabaseHas('car_brands', ['name' => 'Mazda']);
        $this->assertDatabaseHas('car_models', ['name' => 'Mazda6']);
        $this->assertDatabaseHas('product_fitments', ['product_id' => $product->id, 'mapped_part_number' => 'MAZ-BP-101']);
        $this->assertDatabaseHas('product_fitments', ['product_id' => $product->id, 'mapped_part_number' => 'MAZ-BP-102']);
    }

    public function test_cashier_can_create_a_customer_from_the_pos(): void
    {
        $response = $this->actingAs($this->cashier())->postJson('/api/pos/customers', [
            'name' => 'Counter Customer',
            'company_name' => 'Counter Garage LLC',
            'phone' => '+971500000001',
            'trn' => '100999888777666',
        ]);

        $response->assertCreated()
            ->assertJsonPath('customer.name', 'Counter Garage LLC')
            ->assertJsonPath('customer.credit_limit', 0);
        $this->assertDatabaseHas('customers', [
            'name' => 'Counter Customer',
            'company_name' => 'Counter Garage LLC',
            'credit_limit' => 0,
        ]);
    }

    public function test_owner_can_save_a_hidden_buying_price_for_a_new_pos_product(): void
    {
        $owner = User::where('email', 'owner@gitgadgets.test')->firstOrFail();

        $response = $this->actingAs($owner)->postJson('/api/pos/quick-add', [
            'name' => 'Hidden Cost Part',
            'primary_part_number' => 'HIDDEN-001',
            'selling_price' => 150,
            'secret_cost_code' => 'HIDDEN',
            'stock_qty' => 1,
            'cost_price' => 92.50,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('products', [
            'primary_part_number' => 'HIDDEN-001',
            'cost_price' => 92.50,
            'cost_pending' => 0,
        ]);
    }

    public function test_cashier_totals_endpoint_never_returns_cost(): void
    {
        $product = Product::where('primary_part_number', 'BP-101')->firstOrFail();

        $this->actingAs($this->cashier())->postJson('/api/pos/totals', [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_price' => 125]],
        ])->assertOk()->assertJsonMissingPath('lines.0.cost_price');
    }

    public function test_cashier_cannot_edit_a_customer_credit_limit(): void
    {
        $customer = \App\Models\Customer::firstOrFail();

        $this->actingAs($this->cashier())->get(route('customers.edit', $customer))->assertForbidden();
    }
}
