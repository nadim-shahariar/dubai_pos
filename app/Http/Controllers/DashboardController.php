<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->startOfDay();
        $end = now()->endOfDay();

        $salesQuery = Sale::query()->whereBetween('sold_at', [$today, $end])->where('status', '!=', 'voided');
        $todaySales = (float) (clone $salesQuery)->sum('total');
        $todayCount = (clone $salesQuery)->count();
        $todayVat = (float) (clone $salesQuery)->sum('vat_total');
        $todayCost = (float) SaleItem::query()
            ->whereHas('sale', fn ($q) => $q->whereBetween('sold_at', [$today, $end])->where('status', '!=', 'voided'))
            ->sum(\DB::raw('cost_price * qty'));
        $todayNet = (float) (clone $salesQuery)->sum('net_total');
        $grossProfit = $todayNet - $todayCost;

        $lowStock = Product::active()->whereColumn('stock_qty', '<=', 'reorder_level')->count();
        $openShifts = Shift::with('user')->where('status', 'open')->get();
        $recent = Sale::with(['user', 'customer'])->latest('sold_at')->limit(7)->get();

        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $trend[] = [
                'label' => $date->format('D'),
                'date' => $date->format('d M'),
                'total' => (float) Sale::whereDate('sold_at', $date->toDateString())->where('status', '!=', 'voided')->sum('total'),
            ];
        }
        $maxTrend = max(1, collect($trend)->max('total'));

        $topProducts = SaleItem::query()
            ->selectRaw('primary_part_number, name, SUM(qty) as qty, SUM(line_total) as revenue, SUM(net_amount) as net, SUM(cost_price * qty) as cost')
            ->whereHas('sale', fn ($q) => $q->whereBetween('sold_at', [now()->startOfMonth(), $end])->where('status', '!=', 'voided'))
            ->groupBy('primary_part_number', 'name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'todaySales', 'todayCount', 'todayVat', 'todayCost', 'grossProfit', 'lowStock',
            'openShifts', 'recent', 'trend', 'maxTrend', 'topProducts'
        ));
    }
}
