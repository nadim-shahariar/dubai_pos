<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AuditService;
use App\Support\Pos;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        $settings = [
            'company_name' => Pos::company(),
            'company_trn' => Pos::trn(),
            'company_address' => Pos::address(),
            'company_phone' => Pos::phone(),
            'company_email' => Pos::email(),
            'vat_rate' => Pos::vatRate(),
            'prices_include_vat' => Pos::pricesIncludeVat() ? '1' : '0',
            'invoice_prefix' => Pos::invoicePrefix(),
            'cashier_discount_limit' => Pos::cashierDiscountLimit(),
            'receipt_footer' => Pos::receiptFooter(),
        ];

        return view('settings.edit', compact('settings'));
    }

    public function update(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'company_trn' => ['required', 'digits:15'],
            'company_address' => ['required', 'string', 'max:500'],
            'company_phone' => ['nullable', 'string', 'max:30'],
            'company_email' => ['nullable', 'email', 'max:150'],
            'vat_rate' => ['required', 'numeric', 'between:0,100'],
            'prices_include_vat' => ['nullable', 'boolean'],
            'invoice_prefix' => ['required', 'string', 'max:10', 'alpha_num'],
            'cashier_discount_limit' => ['required', 'numeric', 'between:0,100'],
            'receipt_footer' => ['required', 'string', 'max:500'],
        ]);

        $old = [];
        foreach ($data as $key => $value) {
            $old[$key] = Setting::get($key);
        }

        foreach ($data as $key => $value) {
            Setting::put($key, $key === 'prices_include_vat' ? ($request->boolean('prices_include_vat') ? '1' : '0') : $value);
        }

        $audit->log('settings.updated', null, ['old' => $old, 'new' => $data], $request->user());

        return back()->with('success', 'Settings saved. New sales will use the updated tax and invoice settings.');
    }
}
