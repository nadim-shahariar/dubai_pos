<?php

namespace Tests\Unit;

use App\Services\PricingService;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    private PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = new PricingService();
    }

    public function test_vat_inclusive_line_is_split_correctly(): void
    {
        $result = $this->pricing->calculate([
            ['product_id' => 1, 'primary_part_number' => 'BP-101', 'name' => 'Brake Pad', 'qty' => 1, 'unit_price' => 105, 'tax_rate' => 5],
        ], 'none', 0, true, 5);

        $this->assertSame(105.0, $result['total']);
        $this->assertSame(100.0, $result['net_total']);
        $this->assertSame(5.0, $result['vat_total']);
    }

    public function test_vat_exclusive_line_is_split_correctly(): void
    {
        $result = $this->pricing->calculate([
            ['product_id' => 1, 'primary_part_number' => 'BP-101', 'name' => 'Brake Pad', 'qty' => 1, 'unit_price' => 100, 'tax_rate' => 5],
        ], 'none', 0, false, 5);

        $this->assertSame(105.0, $result['total']);
        $this->assertSame(100.0, $result['net_total']);
        $this->assertSame(5.0, $result['vat_total']);
    }

    public function test_invoice_discount_is_allocated_before_vat(): void
    {
        $result = $this->pricing->calculate([
            ['product_id' => 1, 'primary_part_number' => 'A', 'name' => 'A', 'qty' => 1, 'unit_price' => 105, 'tax_rate' => 5],
        ], 'percent', 10, true, 5);

        $this->assertSame(94.5, $result['total']);
        $this->assertSame(90.0, $result['net_total']);
        $this->assertSame(4.5, $result['vat_total']);
        $this->assertSame(10.5, $result['invoice_discount_total']);
    }

    public function test_line_discount_and_tax_round_to_fils(): void
    {
        $result = $this->pricing->calculate([
            ['product_id' => 1, 'primary_part_number' => 'A', 'name' => 'A', 'qty' => 3, 'unit_price' => 33.33, 'tax_rate' => 5, 'discount_type' => 'percent', 'discount_value' => 5],
        ], 'none', 0, false, 5);

        $this->assertSame(94.99, $result['net_total']);
        $this->assertSame(4.75, $result['vat_total']);
        $this->assertSame(99.74, $result['total']);
    }
}
