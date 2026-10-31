<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    public function __construct(private SaleService $sales) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $sales = Sale::query()
            ->with('user:id,name')
            ->withSum('items as items_count', 'quantity')
            // Cashiers only see the sales they rang up themselves.
            ->when($user->hasRole(User::ROLE_CASHIER), fn ($q) => $q->where('user_id', $user->id))
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
        abort_if($user->hasRole(User::ROLE_CASHIER) && $sale->user_id !== $user->id, 403);

        return response()->json($sale->load('items', 'user:id,name', 'voider:id,name'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', Rule::in(Sale::PAYMENT_METHODS)],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'reference_no' => ['nullable', 'required_unless:payment_method,cash', 'string', 'max:100'],
            'discount_type' => ['nullable', Rule::in(Sale::DISCOUNT_TYPES)],
            'discount_id_no' => ['nullable', 'required_if:discount_type,senior,pwd', 'string', 'max:50'],
            'customer_name' => ['nullable', 'required_if:discount_type,senior,pwd', 'string', 'max:255'],
            'prescription_ref' => ['nullable', 'string', 'max:100'],
        ], [
            'reference_no.required_unless' => 'Enter the GCash / card reference number.',
            'discount_id_no.required_if' => 'Senior citizen / PWD ID number is required.',
            'customer_name.required_if' => 'Customer name is required for senior citizen / PWD discounts.',
        ]);

        return response()->json($this->sales->checkout($request->user(), $data), 201);
    }

    public function void(Request $request, Sale $sale): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return response()->json($this->sales->void($sale, $request->user(), $data['reason']));
    }
}
