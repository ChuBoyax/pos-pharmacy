<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($request->input('from', today()->startOfMonth()))->startOfDay();
        $to = Carbon::parse($request->input('to', today()))->endOfDay();

        $sales = fn () => Sale::query()->whereBetween('sales.created_at', [$from, $to]);
        $paid = fn () => $sales()->where('sales.status', Sale::STATUS_PAID);
        $items = fn () => SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', Sale::STATUS_PAID)
            ->whereBetween('sales.created_at', [$from, $to]);

        $summary = $paid()->selectRaw('count(*) as transactions, coalesce(sum(subtotal), 0) as gross, coalesce(sum(discount), 0) as discounts, coalesce(sum(vat), 0) as vat, coalesce(sum(total), 0) as net')->first();
        $cogs = (float) $items()->leftJoin('batches', 'batches.id', '=', 'sale_items.batch_id')->sum(DB::raw('sale_items.quantity * coalesce(batches.cost, 0)'));
        $voided = $sales()->where('status', Sale::STATUS_VOIDED)->selectRaw('count(*) as count, coalesce(sum(total), 0) as total')->first();

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'transactions' => (int) $summary->transactions,
                'gross' => (float) $summary->gross,
                'discounts' => (float) $summary->discounts,
                'vat' => (float) $summary->vat,
                'net' => (float) $summary->net,
                'average' => $summary->transactions ? round($summary->net / $summary->transactions, 2) : 0,
                'items_sold' => (int) $items()->sum('sale_items.quantity'),
                'cogs' => round($cogs, 2),
                'profit' => round($summary->net - $cogs, 2),
                'voided_count' => (int) $voided->count,
                'voided_total' => (float) $voided->total,
            ],
            'daily' => $paid()
                ->selectRaw('date(created_at) as day, count(*) as transactions, sum(discount) as discounts, sum(total) as total')
                ->groupBy('day')
                ->orderBy('day')
                ->get(),
            'products' => $items()
                ->selectRaw('sale_items.name, sum(sale_items.quantity) as units, sum(sale_items.total) as revenue')
                ->groupBy('sale_items.name')
                ->orderByDesc('revenue')
                ->limit(15)
                ->get(),
            'categories' => $items()
                ->leftJoin('medicines', 'medicines.id', '=', 'sale_items.medicine_id')
                ->leftJoin('categories', 'categories.id', '=', 'medicines.category_id')
                ->selectRaw("coalesce(categories.name, 'Uncategorized') as name, sum(sale_items.quantity) as units, sum(sale_items.total) as revenue")
                ->groupByRaw("coalesce(categories.name, 'Uncategorized')")
                ->orderByDesc('revenue')
                ->get(),
            'cashiers' => $paid()
                ->leftJoin('users', 'users.id', '=', 'sales.user_id')
                ->selectRaw("coalesce(users.name, 'Deleted user') as name, count(*) as transactions, sum(sales.total) as total")
                ->groupBy('users.name')
                ->orderByDesc('total')
                ->get(),
            'payments' => $paid()
                ->selectRaw('payment_method, count(*) as transactions, sum(total) as total')
                ->groupBy('payment_method')
                ->orderByDesc('total')
                ->get(),
            'discounts' => $paid()
                ->where('discount_type', '!=', 'none')
                ->selectRaw('discount_type, count(*) as transactions, sum(discount) as discount')
                ->groupBy('discount_type')
                ->get(),
        ]);
    }
}
