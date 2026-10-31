<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        $batches = Batch::query()
            ->with('supplier:id,name', 'medicine:id,name,sku,unit,price')
            ->whereNotNull('supplier_id')
            ->when($request->medicine_id, fn ($q, $id) => $q->where('medicine_id', $id))
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        if ($request->medicine_id) {
            $offers = $this->offers($batches);

            return response()->json([
                'medicine' => Medicine::find($request->medicine_id, ['id', 'name', 'sku', 'unit', 'price']),
                'suppliers' => $offers,
                'best_supplier_id' => $offers->first()['supplier_id'] ?? null,
            ]);
        }

        return response()->json($batches->groupBy('medicine_id')
            ->map(fn ($rows) => [$rows->first()->medicine, $this->offers($rows)])
            ->filter(fn ($pair) => $pair[1]->count() >= 2)
            ->map(fn ($pair) => [
                'medicine' => $pair[0],
                'suppliers' => $pair[1]->count(),
                'best' => $pair[1]->first(),
                'highest_cost' => $pair[1]->max('last_cost'),
                'saving_per_unit' => round($pair[1]->max('last_cost') - $pair[1]->first()['last_cost'], 2),
            ])
            ->sortByDesc(fn ($row) => $row['highest_cost'] > 0 ? $row['saving_per_unit'] / $row['highest_cost'] : 0)
            ->values());
    }

    /**
     * One row per supplier, cheapest latest price first.
     *
     * @param  Collection<int, Batch>  $batches  ordered oldest delivery first
     * @return Collection<int, array<string, mixed>>
     */
    private function offers(Collection $batches): Collection
    {
        return $batches->groupBy('supplier_id')->map(function ($rows) {
            $units = $rows->sum('quantity_received');
            $last = $rows->last();

            return [
                'supplier_id' => $last->supplier_id,
                'supplier' => $last->supplier->name,
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

    public function store(Request $request): JsonResponse
    {
        return response()->json(Supplier::create($this->validated($request)), 201);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($this->validated($request));

        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
