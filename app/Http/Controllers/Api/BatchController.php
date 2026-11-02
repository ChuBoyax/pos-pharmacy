<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceiveStockRequest;
use App\Models\Batch;
use App\Models\Medicine;
use App\Models\Setting;
use App\Services\PurchaseCosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $today = today()->toDateString();
        $warnUntil = today()->addDays((int) Setting::valueOf('expiry_warning_days'))->toDateString();

        $batches = Batch::query()
            ->with('medicine:id,name,sku,unit', 'supplier:id,name')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->whereLike('batch_no', "%{$s}%")
                ->orWhereHas('medicine', fn ($q) => $q->whereLike('name', "%{$s}%")->orWhereLike('sku', "%{$s}%"))))
            ->when($request->medicine_id, fn ($q, $id) => $q->where('medicine_id', $id))
            ->when($request->status === 'expiring', fn ($q) => $q->where('quantity', '>', 0)->whereBetween('expiry_date', [$today, $warnUntil]))
            ->when($request->status === 'expired', fn ($q) => $q->where('quantity', '>', 0)->where('expiry_date', '<=', $today))
            ->when($request->status === 'active', fn ($q) => $q->where('quantity', '>', 0)->where('expiry_date', '>', $today))
            ->when($request->status === 'empty', fn ($q) => $q->where('quantity', 0))
            ->orderBy($request->status === 'recent' ? 'created_at' : 'expiry_date', $request->status === 'recent' ? 'desc' : 'asc');

        return response()->json($batches->paginate(15));
    }

    /**
     * Receive a new delivery of stock.
     */
    public function store(ReceiveStockRequest $request): JsonResponse
    {
        $data = $request->validated();

        $costing = PurchaseCosting::compute(
            (int) $data['packs_bought'],
            (int) $data['pack_size'],
            (float) ($data['list_price'] ?? 0),
            (float) ($data['discount_percent'] ?? 0),
            isset($data['deal_buy']) ? (int) $data['deal_buy'] : null,
            isset($data['deal_free']) ? (int) $data['deal_free'] : null,
            isset($data['total_cost']) ? (float) $data['total_cost'] : null,
        );

        $batch = DB::transaction(function () use ($data, $costing) {
            // The medicine's cost follows the latest delivery; its selling price is never touched.
            Medicine::whereKey($data['medicine_id'])->update(['cost' => round($costing['cost'], 2)]);

            return Batch::create([
                ...$data,
                ...$costing,
                'discount_percent' => $data['discount_percent'] ?? 0,
                'quantity_received' => $costing['quantity'],
                'received_at' => $data['received_at'] ?? today(),
            ]);
        });

        return response()->json($batch->load('medicine:id,name'), 201);
    }

    /**
     * Correct the on-hand quantity of a batch (damaged, lost, recount) and log why.
     */
    public function update(Request $request, Batch $batch): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($request, $batch, $data) {
            $batch->adjustments()->create([
                'user_id' => $request->user()->id,
                'quantity_before' => $batch->quantity,
                'quantity_after' => $data['quantity'],
                'reason' => $data['reason'],
            ]);

            $batch->update([
                'quantity' => $data['quantity'],
                'expiry_date' => $data['expiry_date'] ?? $batch->expiry_date,
            ]);
        });

        return response()->json($batch->fresh('medicine:id,name'));
    }

    public function adjustments(Batch $batch): JsonResponse
    {
        return response()->json($batch->adjustments()->with('user:id,name')->latest()->get());
    }
}
