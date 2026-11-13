<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Services\CreditCollectionService;
use Illuminate\Http\Request;

class CustomerPaymentController extends Controller
{
    public function store(Request $request, Customer $customer, CreditCollectionService $collections)
    {
        $data = $request->validate([
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,card,bank_transfer'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $sale = Sale::query()
            ->where('customer_id', $customer->id)
            ->findOrFail($data['sale_id']);

        $payment = $collections->collect($request->user(), $sale, $data);

        return redirect()
            ->route('credit-payments.receipt', $payment)
            ->with('success', 'Payment '.$payment->receipt_number.' received. Remaining balance: AED '.number_format((float) $payment->sale->balance_due, 2).'.');
    }

    public function receipt(CustomerPayment $payment)
    {
        $payment->load(['customer', 'sale', 'user', 'shift']);

        return view('credit-payments.receipt', compact('payment'));
    }
}
