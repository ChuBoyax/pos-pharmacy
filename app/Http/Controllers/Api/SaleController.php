<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(private SaleService $sales) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $sales = Sale::query()
            ->with('user:id,name')
            ->withSum('items as items_count', 'quantity')
            // Without sales.view_all, users only see the sales they rang up themselves.
            ->when($user->cannot('sales.view_all'), fn ($q) => $q->where('user_id', $user->id))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('invoice_no', 'like', "%{$s}%")
                ->orWhere('customer_name', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->payment_method, fn ($q, $m) => $q->where('payment_method', $m))
            ->when($request->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id');

        return response()->json($sales->paginate(15));
    }

    public function show(Request $request, Sale $sale): JsonResponse
    {
        $user = $request->user();
        abort_if($user->cannot('sales.view_all') && $sale->user_id !== $user->id, 403);

        return response()->json($sale->load('items', 'user:id,name', 'voider:id,name'));
    }

    public function store(CheckoutRequest $request): JsonResponse
    {
        return response()->json($this->sales->checkout($request->user(), $request->validated()), 201);
    }

    public function void(Request $request, Sale $sale): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return response()->json($this->sales->void($sale, $request->user(), $data['reason']));
    }
}
