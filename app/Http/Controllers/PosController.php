<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\PosHold;
use App\Models\Product;
use App\Models\Shift;
use App\Support\Pos;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $shift = Shift::openFor($user);

        $vehicles = \App\Models\CarBrand::with('models')->orderBy('name')->get()->map(fn ($brand) => [
            'id' => $brand->id,
            'name' => $brand->name,
            'models' => $brand->models->map(fn ($model) => ['id' => $model->id, 'name' => $model->name])->values(),
        ]);

        $customers = Customer::query()
            ->where('is_active', true)
            ->withSum('sales as outstanding_balance', 'balance_due')
            ->orderByRaw("COALESCE(NULLIF(company_name, ''), name)")
            ->limit(500)
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->company_name ?: $customer->name,
                'contact' => $customer->company_name ? $customer->name : null,
                'trn' => $customer->trn,
                'credit_limit' => (float) $customer->credit_limit,
                'outstanding' => (float) ($customer->outstanding_balance ?? 0),
            ]);

        $boot = [
            'csrf' => csrf_token(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'can_view_cost' => $user->canViewCost(),
            ],
            'shift' => $shift ? [
                'id' => $shift->id,
                'opening_float' => (float) $shift->opening_float,
                'opened_at' => $shift->opened_at->toIso8601String(),
            ] : null,
            'company' => [
                'name' => Pos::company(),
                'trn' => Pos::trn(),
                'address' => Pos::address(),
                'phone' => Pos::phone(),
            ],
            'tax_rate' => Pos::vatRate(),
            'prices_include_vat' => Pos::pricesIncludeVat(),
            'discount_limit' => Pos::cashierDiscountLimit(),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'customers' => $customers,
            'part_brands' => Product::query()->whereNotNull('brand')->where('brand', '<>', '')->distinct()->orderBy('brand')->pluck('brand'),
            'vehicles' => $vehicles,
            'holds_count' => PosHold::where('user_id', $user->id)->where('status', 'open')->count(),
        ];

        return view('pos.index', [
            'boot' => $boot,
            'recent' => Product::active()->with(['category', 'fitments.brand', 'fitments.model'])->orderByDesc('stock_qty')->limit(12)->get(),
        ]);
    }
}
