<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Models\Batch;
use App\Models\Medicine;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->withCount('medicines')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('contact_person', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->orderBy('name');

        return response()->json($request->boolean('all') ? $suppliers->get(['id', 'name']) : $suppliers->paginate(15));
    }

    /**
     * Compare what each supplier has charged for a medicine, based on received batches.
     * Without a medicine_id, lists every medicine bought from two or more suppliers.
     */
    public function compare(Request $request): JsonResponse
    {
        $request->validate(['medicine_id' => ['nullable', 'exists:medicines,id']]);

        // Only the columns the maths needs: this scans every delivery ever received.
        $batches = Batch::query()
            ->whereNotNull('supplier_id')
            ->when($request->medicine_id, fn ($q, $id) => $q->where('medicine_id', $id))
            ->orderBy('received_at')
            ->orderBy('id')
            ->get(['medicine_id', 'supplier_id', 'cost', 'quantity_received', 'received_at', 'expiry_date']);

        $names = Supplier::whereIn('id', $batches->pluck('supplier_id')->unique())->pluck('name', 'id');
        $medicineColumns = ['id', 'name', 'sku', 'unit', 'price'];

        if ($request->medicine_id) {
            $offers = $this->offers($batches, $names);

            return response()->json([
                'medicine' => Medicine::find($request->medicine_id, $medicineColumns),
                'suppliers' => $offers,
                'best_supplier_id' => $offers->first()['supplier_id'] ?? null,
            ]);
        }

        $compared = $batches->groupBy('medicine_id')
            ->map(fn ($rows) => $this->offers($rows, $names))
            ->filter(fn ($offers) => $offers->count() >= 2);
        $medicines = Medicine::whereIn('id', $compared->keys())->get($medicineColumns)->keyBy('id');

        return response()->json($compared
            ->map(fn ($offers, $medicineId) => [
                'medicine' => $medicines->get($medicineId),
                'suppliers' => $offers->count(),
                'best' => $offers->first(),
                'highest_cost' => $offers->max('last_cost'),
                'saving_per_unit' => round($offers->max('last_cost') - $offers->first()['last_cost'], 2),
            ])
            ->sortByDesc(fn ($row) => $row['highest_cost'] > 0 ? $row['saving_per_unit'] / $row['highest_cost'] : 0)
            ->values());
    }

    /**
     * One row per supplier, cheapest latest price first.
     *
     * @param  Collection<int, Batch>  $batches  ordered oldest delivery first
     * @param  Collection<int, string>  $names  supplier names keyed by id
     * @return Collection<int, array<string, mixed>>
     */
    private function offers(Collection $batches, Collection $names): Collection
    {
        return $batches->groupBy('supplier_id')->map(function ($rows) use ($names) {
            $units = $rows->sum('quantity_received');
            $last = $rows->last();

            return [
                'supplier_id' => $last->supplier_id,
                'supplier' => $names->get($last->supplier_id),
                'last_cost' => (float) $last->cost,
                'avg_cost' => $units ? round($rows->sum(fn ($b) => $b->cost * $b->quantity_received) / $units, 2) : 0.0,
                'min_cost' => (float) $rows->min('cost'),
                'max_cost' => (float) $rows->max('cost'),
                'deliveries' => $rows->count(),
                'units' => $units,
                // How much shelf life the stock still had when it arrived.
                'avg_shelf_life_days' => (int) round($rows->avg(fn ($b) => $b->received_at->diffInDays($b->expiry_date))),
                'last_received' => $last->received_at->toDateString(),
            ];
        })->sortBy('last_cost')->values();
    }

    public function store(SupplierRequest $request): JsonResponse
    {
        return response()->json(Supplier::create($request->validated()), 201);
    }

    public function update(SupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted.']);
    }
}
