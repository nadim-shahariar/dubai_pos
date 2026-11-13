<?php

namespace App\Http\Controllers;

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
