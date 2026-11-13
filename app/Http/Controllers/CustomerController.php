<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $customers = Customer::query()
            ->withSum('sales as outstanding', 'balance_due')
            ->withCount('sales')
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('company_name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('trn', 'like', "%{$q}%");
                });
            })
            ->orderBy('company_name')
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', compact('customers', 'q'));
    }

    public function create()
    {
        return view('customers.form', ['customer' => new Customer()]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $this->validated($request);
        $customer = Customer::create($data);
        $audit->log('customer.created', $customer, ['name' => $customer->name], $request->user());

        return redirect()->route('customers.index')->with('success', 'Customer created.');
    }

    public function edit(Customer $customer)
    {
        return view('customers.form', compact('customer'));
    }

    public function update(Request $request, Customer $customer, AuditService $audit)
    {
        $data = $this->validated($request, $customer);
        $customer->update($data);
        $audit->log('customer.updated', $customer, ['name' => $customer->name], $request->user());

        return redirect()->route('customers.index')->with('success', 'Customer updated.');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'trn' => ['nullable', 'digits:15', Rule::unique('customers')->ignore($customer?->id)],
            'address' => ['nullable', 'string', 'max:1000'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'payment_terms_days' => ['nullable', 'integer', 'between:0,365'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['credit_limit'] = $data['credit_limit'] ?? 0;
        $data['payment_terms_days'] = $data['payment_terms_days'] ?? 0;

        if (! $request->user()->canApprove()) {
            $data['credit_limit'] = 0;
            $data['payment_terms_days'] = 0;
        }

        return $data;
    }
}
