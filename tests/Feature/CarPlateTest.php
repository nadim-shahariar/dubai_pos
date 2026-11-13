<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarPlateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function payload(): array
    {
        return [
            'items' => [['product_id' => Product::where('primary_part_number', 'BP-101')->firstOrFail()->id, 'variant_sku' => 'TOY-BP-001', 'qty' => 1, 'unit_price' => 125]],
            'payments' => [['method' => 'cash', 'amount' => 125]],
        ];
    }

    public function test_customer_plate_is_saved_and_invoice_keeps_its_own_snapshot(): void
    {
        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner)->post('/customers', [
            'name' => 'Plate Test Customer', 'car_plate_number' => 'Dubai A 12345', 'is_active' => 1,
        ])->assertRedirect();
        $customer = Customer::where('name', 'Plate Test Customer')->firstOrFail();
        $this->assertSame('Dubai A 12345', $customer->car_plate_number);
        $cashier = User::where('role', 'cashier')->firstOrFail();
        $sale = app(SaleService::class)->complete($cashier, $this->payload() + ['customer_id' => $customer->id]);
        $customer->update(['car_plate_number' => 'Dubai B 98765']);
        $this->assertSame('Dubai A 12345', $sale->fresh()->car_plate_number);
        $this->actingAs($cashier)->get(route('sales.invoice', $sale))->assertOk()->assertSee('Car plate number: Dubai A 12345')->assertDontSee('Dubai B 98765');
        $this->get(route('sales.receipt', $sale))->assertOk()->assertSee('Car plate number: Dubai A 12345');
    }

    public function test_invoice_plate_can_override_customer_default_or_be_left_empty(): void
    {
        $cashier = User::where('role', 'cashier')->firstOrFail();
        $customer = Customer::create(['name' => 'With a car', 'car_plate_number' => 'A 123']);
        $this->actingAs($cashier)->postJson('/api/pos/sales', $this->payload() + [
            'customer_id' => $customer->id, 'car_plate_number' => 'B 456',
        ])->assertCreated();
        $this->assertSame('B 456', Sale::latest('id')->first()->car_plate_number);
        $this->postJson('/api/pos/sales', $this->payload() + [
            'customer_id' => $customer->id, 'car_plate_number' => '',
        ])->assertCreated();
        $sale = Sale::latest('id')->first();
        $this->assertNull($sale->car_plate_number);
        $this->get(route('sales.invoice', $sale))->assertOk()->assertDontSee('Car plate number:');
        $this->assertSame('A 123', $customer->fresh()->car_plate_number);
    }

    public function test_walk_in_plate_is_optional_and_plate_length_is_validated(): void
    {
        $cashier = User::where('role', 'cashier')->firstOrFail();
        $this->actingAs($cashier)->postJson('/api/pos/sales', $this->payload())->assertCreated();
        $this->assertNull(Sale::latest('id')->first()->car_plate_number);
        $this->postJson('/api/pos/sales', $this->payload() + ['car_plate_number' => 'DHAKA METRO GA 12-3456'])->assertCreated();
        $this->assertSame('DHAKA METRO GA 12-3456', Sale::latest('id')->first()->car_plate_number);
        $this->postJson('/api/pos/sales', $this->payload() + ['car_plate_number' => str_repeat('x', 51)])->assertUnprocessable()->assertJsonValidationErrors('car_plate_number');
        $this->get('/pos')->assertOk()->assertSee('invoice-car-plate')->assertSee('optional');
    }
    public function test_company_invoice_can_identify_a_person_a_plate_or_both(): void
    {
        $customer = Customer::create(['name' => 'Office Contact', 'company_name' => 'Service Company LLC']);
        $cashier = User::where('role', 'cashier')->firstOrFail();
        $this->actingAs($cashier);
        foreach ([['Ahmed', null], [null, 'Dubai A 12345'], ['Mohammed', 'Dubai B 45678'], [null, null]] as [$person, $plate]) {
            $this->postJson('/api/pos/sales', $this->payload() + [
                'customer_id' => $customer->id, 'person_name' => $person, 'car_plate_number' => $plate,
            ])->assertCreated();
            $sale = Sale::latest('id')->firstOrFail();
            $this->assertSame('Service Company LLC', $sale->customer_name);
            $this->assertSame($person, $sale->person_name);
            $response = $this->get(route('sales.invoice', $sale))->assertOk()->assertSee('Service Company LLC');
            if ($person) $response->assertSee('Person / Driver: '.$person);
            else $response->assertDontSee('Person / Driver:');
            if ($plate) $response->assertSee('Car plate number: '.$plate);
            else $response->assertDontSee('Car plate number:');
        }
        $this->postJson('/api/pos/sales', $this->payload() + ['person_name' => str_repeat('x', 151)])
            ->assertUnprocessable()->assertJsonValidationErrors('person_name');
    }
}
