<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
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
                        ->orWhere('car_plate_number', 'like', "%{$q}%")
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
        return view('customers.form', ['customer' => new Customer]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $this->validated($request);
        $customer = Customer::create($data);
        $audit->log('customer.created', $customer, ['name' => $customer->name], $request->user());

        return redirect()->route('customers.index')->with('success', 'Customer created.');
    }

    public function statement(Customer $customer)
    {
        $customer->load([
            'collections' => fn ($q) => $q->with(['sale', 'user'])->latest('paid_at'),
            'sales' => fn ($q) => $q->where('status', '!=', 'voided')
                ->with(['payments', 'collections'])
                ->orderBy('sold_at'),
        ]);

        $invoices = $customer->sales->map(function (Sale $sale) use ($customer) {
            $paidAtSale = (float) $sale->payments->where('method', '!=', 'credit')->sum('amount');
            $creditSold = (float) $sale->payments->where('method', 'credit')->sum('amount');
            $collected = (float) $sale->collections->sum('amount');
            $dueDate = $sale->sold_at->copy()->addDays((int) $customer->payment_terms_days);
            $balance = (float) $sale->balance_due;

            return [
                'sale' => $sale,
                'paid_at_sale' => $paidAtSale,
                'credit_sold' => $creditSold,
                'collected' => $collected,
                'balance' => $balance,
                'due_date' => $dueDate,
                'days_overdue' => $balance > 0 && $dueDate->isPast() ? $dueDate->diffInDays(now()) : 0,
            ];
        });

        $totals = [
            'invoiced' => (float) $customer->sales->sum('total'),
            'paid_at_sale' => $invoices->sum('paid_at_sale'),
            'credit_sold' => $invoices->sum('credit_sold'),
            'collected' => (float) $customer->collections->sum('amount'),
            'outstanding' => (float) $customer->sales->sum('balance_due'),
        ];

        $monthly = $invoices->groupBy(fn ($row) => $row['sale']->sold_at->format('Y-m'))->map(function ($rows, $month) {
            return [
                'month' => $month,
                'invoices' => $rows->count(),
                'invoiced' => $rows->sum(fn ($row) => (float) $row['sale']->total),
                'credit_sold' => $rows->sum('credit_sold'),
                'collected' => $rows->sum('collected'),
                'balance' => $rows->sum('balance'),
            ];
        })->sortKeysDesc();

        return view('customers.statement', compact('customer', 'invoices', 'totals', 'monthly'));
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
            'car_plate_number' => ['nullable', 'string', 'max:50'],
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

        if (! $request->user()->canManageCustomers()) {
            $data['credit_limit'] = 0;
            $data['payment_terms_days'] = 0;
        }

        return $data;
    }
}
