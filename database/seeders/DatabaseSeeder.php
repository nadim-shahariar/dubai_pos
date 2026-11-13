<?php

namespace Database\Seeders;

use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\PricingService;
use App\Services\SaleService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Rashid Al Mansoori', 'email' => 'owner@gitgadgets.test', 'role' => 'owner', 'pin' => '9999'],
            ['name' => 'Samir Haddad', 'email' => 'manager@gitgadgets.test', 'role' => 'manager', 'pin' => '1234'],
            ['name' => 'Ahmed Khan', 'email' => 'cashier@gitgadgets.test', 'role' => 'cashier', 'pin' => '1111'],
            ['name' => 'Joseph Mathew', 'email' => 'store@gitgadgets.test', 'role' => 'storekeeper', 'pin' => '2222'],
            ['name' => 'Priya Nair', 'email' => 'accounts@gitgadgets.test', 'role' => 'accountant', 'pin' => '3333'],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                $data + ['password' => 'password', 'is_active' => true],
            );
        }

        $settings = [
            'company_name' => 'GIT GADGETS VEHICLE PARTS',
            'company_trn' => '100000000000003',
            'company_address' => 'Al Quoz Industrial Area 3, Dubai, UAE',
            'company_phone' => '+971 4 555 0180',
            'company_email' => 'counter@gitgadgets.ae',
            'vat_rate' => '5',
            'prices_include_vat' => '1',
            'invoice_prefix' => 'GG',
            'cashier_discount_limit' => '5',
            'receipt_footer' => 'Thank you for your business. Returns within 7 days with invoice. Opened or electrical parts are not returnable.',
        ];
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['group' => 'general', 'value' => $value]);
        }

        $categories = ['Brakes', 'Filters', 'Engine', 'Suspension', 'Electrical', 'Body', 'Fluids', 'Service'];
        foreach ($categories as $name) {
            Category::updateOrCreate(['name' => $name]);
        }

        $vehicleMap = [
            'Toyota' => ['Camry', 'Corolla', 'Land Cruiser', 'Hilux'],
            'Lexus' => ['ES350', 'RX350'],
            'Nissan' => ['Altima', 'Patrol'],
            'Honda' => ['Accord', 'Civic'],
            'Mitsubishi' => ['Pajero', 'Lancer'],
            'Ford' => ['F-150'],
        ];
        $brands = [];
        $models = [];
        foreach ($vehicleMap as $brandName => $modelNames) {
            $brand = CarBrand::updateOrCreate(['name' => $brandName]);
            $brands[$brandName] = $brand;
            foreach ($modelNames as $modelName) {
                $models[$brandName][$modelName] = CarModel::updateOrCreate([
                    'car_brand_id' => $brand->id,
                    'name' => $modelName,
                ]);
            }
        }

        $products = [
            [
                'primary_part_number' => 'BP-101', 'name' => 'Front Brake Pad Set', 'category' => 'Brakes',
                'brand' => 'Toyota Genuine', 'barcode' => '8901234567890', 'selling_price' => 125, 'cost_price' => 82,
                'secret_cost_code' => 'MAE', 'stock_qty' => 18, 'reorder_level' => 5, 'bin_location' => 'A-12-3',
                'description' => 'Ceramic front brake pad set. Quiet stopping, low dust.',
                'fitments' => [
                    ['Toyota', 'Camry', 2018, 2024, 'TOY-BP-001'],
                    ['Lexus', 'ES350', 2019, 2024, 'LEX-BP-001'],
                ],
                'aliases' => [['oem', '04465-33450', 'Toyota'], ['oem', '04465-33451', 'Toyota']],
            ],
            [
                'primary_part_number' => 'OF-220', 'name' => 'Oil Filter', 'category' => 'Filters',
                'brand' => 'Denso', 'barcode' => '8901234567891', 'selling_price' => 45, 'cost_price' => 28,
                'secret_cost_code' => 'KDS', 'stock_qty' => 40, 'reorder_level' => 10, 'bin_location' => 'B-03-1',
                'description' => 'Spin-on oil filter with anti-drain-back valve.',
                'fitments' => [
                    ['Toyota', 'Camry', 2015, 2024, 'TOY-OF-220'],
                    ['Toyota', 'Corolla', 2014, 2024, 'TOY-OF-220'],
                ],
                'aliases' => [['oem', '90915-YZZE1', 'Toyota'], ['aftermarket', 'OF-220-D', 'Denso']],
            ],
            [
                'primary_part_number' => 'AF-330', 'name' => 'Air Filter', 'category' => 'Filters',
                'brand' => 'Bosch', 'barcode' => '8901234567892', 'selling_price' => 65, 'cost_price' => 40,
                'secret_cost_code' => 'ABCKDS', 'stock_qty' => 25, 'reorder_level' => 6, 'bin_location' => 'B-04-2',
                'description' => 'High-flow panel air filter.',
                'fitments' => [
                    ['Nissan', 'Altima', 2019, 2024, 'NIS-AF-330'],
                    ['Honda', 'Accord', 2018, 2024, 'HON-AF-330'],
                ],
                'aliases' => [['oem', '16546-6CA0A', 'Nissan']],
            ],
            [
                'primary_part_number' => 'SP-440', 'name' => 'Iridium Spark Plug Set', 'category' => 'Engine',
                'brand' => 'NGK', 'barcode' => '8901234567893', 'selling_price' => 90, 'cost_price' => 55,
                'secret_cost_code' => 'X1', 'stock_qty' => 30, 'reorder_level' => 8, 'bin_location' => 'C-01-1',
                'description' => 'Set of four iridium spark plugs.',
                'fitments' => [['Toyota', 'Corolla', 2015, 2024, 'NGK-SP-440']],
                'aliases' => [['oem', '90919-01253', 'Toyota']],
            ],
            [
                'primary_part_number' => 'WB-550', 'name' => 'Wiper Blade 24 inch', 'category' => 'Body',
                'brand' => 'Bosch', 'barcode' => '8901234567894', 'selling_price' => 55, 'cost_price' => 32,
                'secret_cost_code' => 'W24', 'stock_qty' => 22, 'reorder_level' => 5, 'bin_location' => 'D-07-2',
                'description' => 'Aerotwin flat wiper blade, 600 mm.',
                'fitments' => [
                    ['Toyota', 'Camry', 2018, 2024, 'BOS-WB-550'],
                    ['Honda', 'Accord', 2018, 2024, 'BOS-WB-550'],
                ],
                'aliases' => [],
            ],
            [
                'primary_part_number' => 'SH-660', 'name' => 'Front Shock Absorber', 'category' => 'Suspension',
                'brand' => 'KYB', 'barcode' => '8901234567895', 'selling_price' => 210, 'cost_price' => 145,
                'secret_cost_code' => 'SUSP', 'stock_qty' => 8, 'reorder_level' => 4, 'bin_location' => 'E-02-1',
                'description' => 'Gas-charged front shock absorber.',
                'fitments' => [['Mitsubishi', 'Pajero', 2015, 2024, 'KYB-SH-660']],
                'aliases' => [['oem', 'MR554860', 'Mitsubishi']],
            ],
            [
                'primary_part_number' => 'CL-770', 'name' => 'Clutch Kit', 'category' => 'Engine',
                'brand' => 'Aisin', 'barcode' => null, 'selling_price' => 480, 'cost_price' => 340,
                'secret_cost_code' => 'CLK', 'stock_qty' => 4, 'reorder_level' => 2, 'bin_location' => 'C-03-1',
                'description' => 'Complete clutch cover, disc and release bearing kit.',
                'fitments' => [['Toyota', 'Corolla', 2015, 2020, 'AIN-CL-770']],
                'aliases' => [],
            ],
            [
                'primary_part_number' => 'BAT-880', 'name' => 'Battery 70Ah', 'category' => 'Electrical',
                'brand' => 'Varta', 'barcode' => '8901234567896', 'selling_price' => 420, 'cost_price' => 300,
                'secret_cost_code' => 'BAT7', 'stock_qty' => 6, 'reorder_level' => 3, 'bin_location' => 'F-01-1',
                'description' => 'Maintenance-free calcium battery, 70Ah, 3-year warranty.',
                'fitments' => [
                    ['Toyota', 'Camry', 2015, 2024, 'VAR-BAT-880'],
                    ['Nissan', 'Altima', 2019, 2024, 'VAR-BAT-880'],
                ],
                'aliases' => [],
            ],
            [
                'primary_part_number' => 'AC-990', 'name' => 'Cabin Air Filter', 'category' => 'Filters',
                'brand' => 'Denso', 'barcode' => null, 'selling_price' => 75, 'cost_price' => 45,
                'secret_cost_code' => 'CAB', 'stock_qty' => 15, 'reorder_level' => 4, 'bin_location' => 'B-05-2',
                'description' => 'Activated-carbon cabin air filter.',
                'fitments' => [
                    ['Lexus', 'ES350', 2019, 2024, 'DEN-AC-990'],
                    ['Toyota', 'Camry', 2018, 2024, 'DEN-AC-990'],
                ],
                'aliases' => [],
            ],
            [
                'primary_part_number' => 'BR-100', 'name' => 'Front Brake Disc', 'category' => 'Brakes',
                'brand' => 'Brembo', 'barcode' => '8901234567897', 'selling_price' => 260, 'cost_price' => 180,
                'secret_cost_code' => 'DISC', 'stock_qty' => 10, 'reorder_level' => 4, 'bin_location' => 'A-13-1',
                'description' => 'Vented front brake disc, sold each.',
                'fitments' => [['Toyota', 'Camry', 2018, 2024, 'BRE-BR-100']],
                'aliases' => [],
            ],
            [
                'primary_part_number' => 'OIL-5W30', 'name' => 'Engine Oil 5W-30 5L', 'category' => 'Fluids',
                'brand' => 'Mobil', 'barcode' => '8901234567898', 'selling_price' => 160, 'cost_price' => 110,
                'secret_cost_code' => 'OIL5', 'stock_qty' => 35, 'reorder_level' => 10, 'bin_location' => 'G-01-1',
                'description' => 'Fully synthetic engine oil, 5 litres.',
                'fitments' => [],
                'aliases' => [],
            ],
            [
                'primary_part_number' => 'HID-120', 'name' => 'Headlight Bulb H11', 'category' => 'Electrical',
                'brand' => 'Philips', 'barcode' => null, 'selling_price' => 85, 'cost_price' => 52,
                'secret_cost_code' => 'H11', 'stock_qty' => 20, 'reorder_level' => 6, 'bin_location' => 'F-04-1',
                'description' => 'Halogen headlight bulb, 12V 55W.',
                'fitments' => [
                    ['Nissan', 'Altima', 2019, 2024, 'PHI-HID-120'],
                    ['Honda', 'Accord', 2018, 2024, 'PHI-HID-120'],
                ],
                'aliases' => [],
            ],
            [
                'primary_part_number' => 'FIT-001', 'name' => 'Fitting Charge', 'category' => 'Service',
                'brand' => 'Git Gadgets', 'barcode' => null, 'selling_price' => 25, 'cost_price' => 0,
                'secret_cost_code' => 'FIT', 'stock_qty' => 0, 'reorder_level' => 0, 'bin_location' => null,
                'description' => 'Workshop fitting labour charge.', 'track_stock' => false,
                'fitments' => [], 'aliases' => [],
            ],
        ];

        $productModels = [];
        foreach ($products as $data) {
            $category = Category::where('name', $data['category'])->first();
            $product = Product::updateOrCreate(
                ['primary_part_number' => $data['primary_part_number']],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'category_id' => $category?->id,
                    'brand' => $data['brand'],
                    'barcode' => $data['barcode'],
                    'selling_price' => $data['selling_price'],
                    'cost_price' => $data['cost_price'],
                    'secret_cost_code' => $data['secret_cost_code'],
                    'stock_qty' => $data['stock_qty'],
                    'reorder_level' => $data['reorder_level'],
                    'bin_location' => $data['bin_location'],
                    'track_stock' => $data['track_stock'] ?? true,
                ],
            );
            $productModels[$product->primary_part_number] = $product;

            $product->fitments()->delete();
            foreach ($data['fitments'] as [$brandName, $modelName, $from, $to, $mapped]) {
                $product->fitments()->create([
                    'car_brand_id' => $brands[$brandName]->id,
                    'car_model_id' => $models[$brandName][$modelName]->id,
                    'year_from' => $from,
                    'year_to' => $to,
                    'mapped_part_number' => $mapped,
                ]);
            }

            $product->aliases()->delete();
            foreach ($data['aliases'] as [$type, $number, $brandName]) {
                $product->aliases()->create([
                    'reference_type' => $type,
                    'reference_number' => $number,
                    'brand' => $brandName,
                ]);
            }
        }

        $garage = Customer::updateOrCreate(
            ['phone' => '+971501112233'],
            [
                'name' => 'Omar Al Suwaidi', 'company_name' => 'Al Quoz Garage LLC',
                'email' => 'accounts@alquozgarage.test', 'trn' => '100123456789012',
                'address' => 'Al Quoz Industrial Area 2, Dubai, UAE',
                'credit_limit' => 5000, 'payment_terms_days' => 30,
            ],
        );

        $cashier = User::where('email', 'cashier@gitgadgets.test')->first();
        $shift = Shift::firstOrCreate(
            ['user_id' => $cashier->id, 'status' => 'open'],
            ['opened_at' => now()->subHours(2), 'opening_float' => 500],
        );

        if (\App\Models\Sale::count() === 0) {
            $pricing = app(PricingService::class);
            $sales = app(SaleService::class);

            $scenarios = [
                [['BP-101', 2], ['OF-220', 1]],
                [['AF-330', 1], ['WB-550', 2]],
                [['BP-101', 1], ['BR-100', 1]],
                [['SH-660', 1]],
                [['OIL-5W30', 2]],
                [['BP-101', 1], ['FIT-001', 1]],
            ];

            foreach ($scenarios as $index => $scenario) {
                $lines = [];
                foreach ($scenario as [$part, $qty]) {
                    $product = $productModels[$part];
                    $lines[] = [
                        'product_id' => $product->id,
                        'primary_part_number' => $product->primary_part_number,
                        'name' => $product->name,
                        'qty' => $qty,
                        'unit_price' => $product->selling_price,
                        'tax_rate' => $product->tax_rate,
                        'cost_price' => $product->cost_price,
                    ];
                }
                $totals = $pricing->calculate($lines);
                $total = (float) $totals['total'];
                $credit = $index === 3 ? 100.0 : 0.0;
                $cash = $total - $credit;
                $payments = [];
                if ($credit > 0) {
                    $payments[] = ['method' => 'credit', 'amount' => $credit];
                }
                $payments[] = ['method' => $index % 3 === 1 ? 'card' : 'cash', 'amount' => $cash, 'tendered' => $cash];
                if ($index === 2) {
                    $payments = [
                        ['method' => 'cash', 'amount' => 200, 'tendered' => 200],
                        ['method' => 'card', 'amount' => $total - 200],
                    ];
                }

                $sale = $sales->complete($cashier, [
                    'customer_id' => $index === 3 ? $garage->id : null,
                    'items' => $lines,
                    'payments' => $payments,
                    'vehicle' => $index === 0 ? 'Toyota Camry 2020 · D-12345' : null,
                ]);

                if ($index < 3) {
                    $sale->update(['sold_at' => now()->subMinutes(30 + ($index * 15))]);
                } else {
                    $sale->update(['sold_at' => now()->subDays($index - 2)->setTime(15, 30)]);
                }
            }
        }
    }
}
