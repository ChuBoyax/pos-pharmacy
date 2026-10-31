<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Medicine;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $today = today();
        $warnUntil = $today->copy()->addDays((int) Setting::valueOf('expiry_warning_days'));

        $daily = $this->dailyTotals($today->copy()->subDays(29), $today);
        $todayRow = $daily->last();
        $yesterdayRow = $daily->get($daily->count() - 2);

        $lowStock = Medicine::where('is_active', true)->lowStock();
        $expiring = Batch::where('quantity', '>', 0)->whereBetween('expiry_date', [$today->toDateString(), $warnUntil->toDateString()]);

        return response()->json([
            'kpis' => [
                'sales_today' => $todayRow['total'],
                'sales_delta' => $this->delta($todayRow['total'], $yesterdayRow['total']),
                'sales_spark' => $daily->slice(-8)->pluck('total')->values(),
                'txns_today' => $todayRow['count'],
                'txns_delta' => $this->delta($todayRow['count'], $yesterdayRow['count']),
                'txns_spark' => $daily->slice(-8)->pluck('count')->values(),
                'low_stock' => (clone $lowStock)->count(),
                'out_of_stock' => Medicine::where('is_active', true)->outOfStock()->count(),
                'expiring' => (clone $expiring)->count(),
                'expired' => Batch::where('quantity', '>', 0)->where('expiry_date', '<=', $today->toDateString())->count(),
                'expiry_days' => (int) Setting::valueOf('expiry_warning_days'),
            ],
            'target' => (float) Setting::valueOf('daily_target'),
            'revenue' => [
                'day' => $this->hourly($today),
                'week' => $this->series($daily->slice(-7), 'D'),
                'month' => $this->series($daily, 'M j'),
            ],
            'categories' => SaleItem::query()
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->leftJoin('medicines', 'medicines.id', '=', 'sale_items.medicine_id')
                ->leftJoin('categories', 'categories.id', '=', 'medicines.category_id')
                ->where('sales.status', Sale::STATUS_PAID)
                ->where('sales.created_at', '>=', $today->copy()->startOfMonth())
                ->selectRaw("coalesce(categories.name, 'Uncategorized') as name, sum(sale_items.total) as total")
                ->groupByRaw("coalesce(categories.name, 'Uncategorized')")
                ->orderByDesc('total')
                ->get(),
            'payments' => Sale::paid()
                ->where('created_at', '>=', $today->copy()->startOfMonth())
                ->selectRaw('payment_method, count(*) as count, sum(total) as total')
                ->groupBy('payment_method')
                ->get(),
            'recent' => Sale::with('user:id,name')->latest('id')->limit(6)->get(),
            'low_stock' => (clone $lowStock)->withStock()->orderBy('stock')->limit(6)->get(['medicines.id', 'name', 'unit', 'reorder_level']),
            'expiring' => (clone $expiring)->with('medicine:id,name')->orderBy('expiry_date')->limit(6)->get(),
            'top' => SaleItem::query()
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sales.status', Sale::STATUS_PAID)
                ->where('sales.created_at', '>=', $today->copy()->subDays(6))
                ->selectRaw('sale_items.name, sum(sale_items.quantity) as units, sum(sale_items.total) as revenue')
                ->groupBy('sale_items.name')
                ->orderByDesc('units')
                ->limit(5)
                ->get(),
        ]);
    }

    /**
     * One row per calendar day (including days with no sales), keyed in date order.
     *
     * @return Collection<int, array{date: Carbon, total: float, count: int}>
     */
    private function dailyTotals(Carbon $from, Carbon $to): Collection
    {
        $rows = Sale::paid()
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('date(created_at) as day, sum(total) as total, count(*) as count')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        return collect(range(0, $from->diffInDays($to)))->map(function ($i) use ($from, $rows) {
            $date = $from->copy()->addDays($i);
            $row = $rows->get($date->toDateString());

            return ['date' => $date, 'total' => (float) ($row->total ?? 0), 'count' => (int) ($row->count ?? 0)];
        });
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    private function series(Collection $days, string $format): array
    {
        return [
            'labels' => $days->map(fn ($d) => $d['date']->format($format))->values()->all(),
            'values' => $days->pluck('total')->values()->all(),
        ];
    }

    /**
     * Sales per store hour (7 AM – 9 PM) for the given day.
     *
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    private function hourly(Carbon $day): array
    {
        $byHour = Sale::paid()
            ->whereDate('created_at', $day)
            ->get(['total', 'created_at'])
            ->groupBy(fn ($sale) => (int) $sale->created_at->format('G'))
            ->map(fn ($sales) => (float) $sales->sum('total'));

        $hours = range(7, 21);

        return [
            'labels' => array_map(fn ($h) => Carbon::createFromTime($h)->format('ga'), $hours),
            'values' => array_map(fn ($h) => $byHour->get($h, 0.0), $hours),
        ];
    }

    private function delta(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
