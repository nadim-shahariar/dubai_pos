<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();
        $q = trim((string) $request->query('q'));

        $base = Sale::query()
            ->whereBetween('sold_at', [$from, $to])
            ->when(($request->user()->isCashier() || ! $request->user()->hasPermission('view_sales')), fn ($query) => $query->where('user_id', $request->user()->id))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('invoice_number', 'like', "%{$q}%")
                        ->orWhere('customer_name', 'like', "%{$q}%")
                        ->orWhere('customer_trn', 'like', "%{$q}%");
                });
            })
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status));

        $totals = [
            'count' => (clone $base)->count(),
            'total' => (float) (clone $base)->sum('total'),
            'vat' => (float) (clone $base)->sum('vat_total'),
            'net' => (float) (clone $base)->sum('net_total'),
        ];

        $sales = $base->with(['user', 'customer', 'payments'])->latest('sold_at')->paginate(20)->withQueryString();

        return view('sales.index', compact('sales', 'totals', 'from', 'to', 'q'));
    }

    public function show(Sale $sale)
    {
        $this->authorizeSale($sale);
        $sale->load(['items.returnItems', 'payments', 'collections', 'returns.user', 'customer', 'user', 'shift']);

        return view('sales.show', compact('sale'));
    }

    public function invoice(Sale $sale)
    {
        $this->authorizeSale($sale);
        $sale->load(['items', 'payments', 'customer', 'user']);

        return view('sales.invoice', compact('sale'));
    }

    public function receipt(Sale $sale)
    {
        $this->authorizeSale($sale);
        $sale->load(['items', 'payments', 'customer', 'user']);

        return view('sales.receipt', compact('sale'));
    }

    public function returnForm(Sale $sale)
    {
        $this->authorizeSale($sale);
        $sale->load(['items.returnItems', 'customer', 'user']);
        abort_if($sale->status === 'voided', 422, 'A voided sale cannot be returned.');

        return view('sales.return', compact('sale'));
    }

    public function returnStore(Request $request, Sale $sale, ReturnService $returns)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'exists:sale_items,id'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.restock' => ['nullable', 'boolean'],
            'items.*.condition' => ['nullable', 'in:resellable,damaged,defective,wrong_part'],
            'reason' => ['required', 'string', 'max:255'],
            'refund_method' => ['required', 'in:cash,card,credit,bank_transfer'],
        ]);

        $items = collect($data['items'])
            ->filter(fn ($item) => (float) ($item['qty'] ?? 0) > 0)
            ->map(fn ($item) => [
                'sale_item_id' => $item['sale_item_id'],
                'qty' => (float) $item['qty'],
                'restock' => (bool) ($item['restock'] ?? false),
                'condition' => $item['condition'] ?? 'resellable',
            ])
            ->values()
            ->all();

        if (! $items) {
            return back()->withErrors(['items' => 'Enter a return quantity for at least one line.'])->withInput();
        }

        $return = $returns->create($request->user(), $sale, $items, $data['reason'], $data['refund_method']);

        return redirect()->route('sales.show', $sale)->with('success', 'Credit note '.$return->credit_note_number.' issued. Stock and VAT have been updated.');
    }

    public function creditNote(SaleReturn $saleReturn)
    {
        $this->authorizeSale($saleReturn->sale);
        $saleReturn->load(['items.saleItem', 'sale', 'customer', 'user']);

        return view('sales.credit-note', ['return' => $saleReturn]);
    }

    private function authorizeSale(Sale $sale): void
    {
        if ((auth()->user()->isCashier() || ! auth()->user()->hasPermission('view_sales')) && $sale->user_id !== auth()->id()) {
            abort(403, 'You can only view your own sales.');
        }
    }
}
