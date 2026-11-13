<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function sales(Request $request)
    {
        [$from, $to] = $this->range($request);
        $sales = Sale::with('items')->whereBetween('sold_at', [$from, $to])->where('status', '!=', 'voided')->get();
        $returns = SaleReturn::whereBetween('returned_at', [$from, $to])->get();

        $daily = $sales->groupBy(fn (Sale $sale) => $sale->sold_at->format('Y-m-d'))->map(function ($group, $date) {
            return [
                'date' => $date,
                'count' => $group->count(),
                'total' => $group->sum('total'),
                'discount' => $group->sum('discount_total'),
                'vat' => $group->sum('vat_total'),
                'net' => $group->sum('net_total'),
            ];
        })->sortKeysDesc();

        $totals = [
            'count' => $sales->count(),
            'total' => $sales->sum('total'),
            'discount' => $sales->sum('discount_total'),
            'vat' => $sales->sum('vat_total'),
            'net' => $sales->sum('net_total'),
            'returns' => $returns->sum('total'),
        ];

        return view('reports.sales', compact('sales', 'returns', 'daily', 'totals', 'from', 'to'));
    }


    public function cashCredit(Request $request)
    {
        [$from, $to] = $this->range($request);

        $sales = Sale::query()
            ->with(['payments', 'collections', 'customer'])
            ->whereBetween('sold_at', [$from, $to])
            ->where('status', '!=', 'voided')
            ->get();

        $collections = CustomerPayment::query()
            ->with(['customer', 'sale', 'user'])
            ->whereBetween('paid_at', [$from, $to])
            ->latest('paid_at')
            ->get();

        $atSaleByMethod = [];
        foreach ($sales as $sale) {
            foreach ($sale->payments as $payment) {
                if ($payment->method === 'credit') {
                    continue;
                }
                $atSaleByMethod[$payment->method] = ($atSaleByMethod[$payment->method] ?? 0) + (float) $payment->amount;
            }
        }

        $collectionsByMethod = [];
        foreach ($collections as $collection) {
            $collectionsByMethod[$collection->method] = ($collectionsByMethod[$collection->method] ?? 0) + (float) $collection->amount;
        }

        $cashAtSale = array_sum($atSaleByMethod);
        $creditSold = $sales->sum(fn (Sale $sale) => (float) $sale->payments->where('method', 'credit')->sum('amount'));
        $collectedInPeriod = (float) $collections->sum('amount');
        $outstanding = (float) Sale::query()
            ->where('balance_due', '>', 0)
            ->where('status', '!=', 'voided')
            ->sum('balance_due');

        $totals = [
            'invoices' => $sales->count(),
            'sales' => (float) $sales->sum('total'),
            'cash_at_sale' => $cashAtSale,
            'credit_sold' => $creditSold,
            'collected' => $collectedInPeriod,
            'outstanding' => $outstanding,
            'at_sale_by_method' => $atSaleByMethod,
            'collections_by_method' => $collectionsByMethod,
        ];

        $months = [];
        $cursor = $from->copy()->startOfMonth();
        $lastMonth = $to->copy()->startOfMonth();
        while ($cursor->lte($lastMonth)) {
            $key = $cursor->format('Y-m');
            $monthSales = $sales->filter(fn (Sale $sale) => $sale->sold_at->format('Y-m') === $key);
            $monthCollections = $collections->filter(fn (CustomerPayment $payment) => $payment->paid_at->format('Y-m') === $key);

            $months[] = [
                'month' => $cursor->format('M Y'),
                'invoices' => $monthSales->count(),
                'cash' => $monthSales->sum(fn (Sale $sale) => (float) $sale->payments->where('method', '!=', 'credit')->sum('amount')),
                'credit' => $monthSales->sum(fn (Sale $sale) => (float) $sale->payments->where('method', 'credit')->sum('amount')),
                'collected' => (float) $monthCollections->sum('amount'),
                'balance' => (float) $monthSales->sum('balance_due'),
            ];

            $cursor->addMonth();
        }

        $customerRows = Customer::query()
            ->with([
                'collections',
                'sales' => fn ($query) => $query->where('status', '!=', 'voided')->with(['payments', 'collections']),
            ])
            ->get()
            ->map(function (Customer $customer) {
                $creditSold = 0.0;
                $outstanding = 0.0;
                $buckets = ['current' => 0.0, 'days_1_30' => 0.0, 'days_31_60' => 0.0, 'days_61_90' => 0.0, 'days_90_plus' => 0.0];
                $oldestDue = null;

                foreach ($customer->sales as $sale) {
                    $creditSold += (float) $sale->payments->where('method', 'credit')->sum('amount');
                    $balance = (float) $sale->balance_due;
                    if ($balance <= 0.009) {
                        continue;
                    }

                    $outstanding += $balance;
                    $due = $sale->sold_at->copy()->addDays((int) $customer->payment_terms_days);
                    if (! $due->isPast()) {
                        $buckets['current'] += $balance;
                    } else {
                        $days = (int) $due->diffInDays(now());
                        if ($days <= 30) {
                            $buckets['days_1_30'] += $balance;
                        } elseif ($days <= 60) {
                            $buckets['days_31_60'] += $balance;
                        } elseif ($days <= 90) {
                            $buckets['days_61_90'] += $balance;
                        } else {
                            $buckets['days_90_plus'] += $balance;
                        }
                    }

                    if (! $oldestDue || $due->lt($oldestDue)) {
                        $oldestDue = $due;
                    }
                }

                return [
                    'customer' => $customer,
                    'credit_sold' => $creditSold,
                    'collected' => (float) $customer->collections->sum('amount'),
                    'outstanding' => $outstanding,
                    'buckets' => $buckets,
                    'oldest_due' => $oldestDue,
                    'credit_limit' => (float) $customer->credit_limit,
                ];
            })
            ->filter(fn ($row) => $row['credit_sold'] > 0 || $row['outstanding'] > 0)
            ->sortByDesc('outstanding')
            ->values();

        return view('reports.cash-credit', compact('sales', 'collections', 'totals', 'months', 'customerRows', 'from', 'to'));
    }

    public function vat(Request $request)
    {
        [$from, $to] = $this->range($request);
        $sales = Sale::whereBetween('sold_at', [$from, $to])->where('status', '!=', 'voided')->get();
        $saleIds = $sales->pluck('id');
        $returns = SaleReturn::whereBetween('returned_at', [$from, $to])->get();

        $salesByRate = SaleItem::query()
            ->whereIn('sale_id', $saleIds)
            ->selectRaw('tax_rate, SUM(net_amount) as net, SUM(vat_amount) as vat, SUM(line_total) as gross')
            ->groupBy('tax_rate')
            ->orderBy('tax_rate')
            ->get();

        $returnsByRate = DB::table('return_items')
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->whereBetween('returns.returned_at', [$from, $to])
            ->selectRaw('return_items.vat_amount as vat, return_items.net_amount as net, return_items.total as gross, 5 as tax_rate')
            ->get()
            ->groupBy('tax_rate')
            ->map(fn ($group) => (object) [
                'tax_rate' => $group->first()->tax_rate,
                'net' => $group->sum('net'),
                'vat' => $group->sum('vat'),
                'gross' => $group->sum('gross'),
            ])
            ->values();

        return view('reports.vat', compact('sales', 'returns', 'salesByRate', 'returnsByRate', 'from', 'to'));
    }

    public function inventory()
    {
        $products = Product::with('category')->orderBy('name')->get();
        $value = $products->sum(fn ($p) => (float) $p->stock_qty * (float) $p->cost_price);
        $retail = $products->sum(fn ($p) => (float) $p->stock_qty * (float) $p->selling_price);
        $low = $products->filter(fn ($p) => $p->track_stock && $p->isLowStock());

        return view('reports.inventory', compact('products', 'value', 'retail', 'low'));
    }

    public function profit(Request $request)
    {
        [$from, $to] = $this->range($request);
        $rows = SaleItem::query()
            ->selectRaw('primary_part_number, name, SUM(qty) as qty, SUM(net_amount) as net, SUM(cost_price * qty) as cost, SUM(line_total) as gross')
            ->whereHas('sale', fn ($q) => $q->whereBetween('sold_at', [$from, $to])->where('status', '!=', 'voided'))
            ->groupBy('primary_part_number', 'name')
            ->orderByDesc('net')
            ->get()
            ->map(function ($row) {
                $row->profit = (float) $row->net - (float) $row->cost;
                $row->margin = (float) $row->net > 0 ? ($row->profit / (float) $row->net) * 100 : 0;
                return $row;
            });

        return view('reports.profit', compact('rows', 'from', 'to'));
    }

    private function range(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }
}
