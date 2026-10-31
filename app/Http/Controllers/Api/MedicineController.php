<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicineRequest;
use App\Models\Medicine;
use App\Models\SaleItem;
use App\Services\ImageOptimizer;
use App\Services\ImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicineController extends Controller
{
    public function __construct(private ImageStore $images) {}

    public function index(Request $request): JsonResponse
    {
        $medicines = Medicine::query()
            ->withStock()
            ->with('category:id,name', 'supplier:id,name')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->whereLike('name', "%{$s}%")
                ->orWhereLike('generic_name', "%{$s}%")
                ->orWhereLike('sku', "%{$s}%")))
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->stock === 'low', fn ($q) => $q->lowStock())
            ->when($request->stock === 'out', fn ($q) => $q->outOfStock())
            ->when($request->boolean('sellable'), fn ($q) => $q->where('is_active', true)->inStock())
            ->orderBy('name');

        return response()->json($medicines->paginate(min($request->integer('per_page', 15), 100)));
    }

    public function show(Medicine $medicine): JsonResponse
    {
        return response()->json($medicine->load('category:id,name', 'supplier:id,name'));
    }

    public function store(MedicineRequest $request): JsonResponse
    {
        return response()->json(Medicine::create($request->validated()), 201);
    }

    public function update(MedicineRequest $request, Medicine $medicine): JsonResponse
    {
        $medicine->update($request->validated());

        return response()->json($medicine);
    }

    public function uploadImage(Request $request, Medicine $medicine, ImageOptimizer $optimizer): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'image.max' => 'The image must not be larger than 10 MB.',
        ]);

        $old = $medicine->image_path;
        $medicine->update(['image_path' => $optimizer->store($request->file('image'), 'medicines')]);
        $this->images->delete($old);

        return response()->json($medicine);
    }

    public function deleteImage(Medicine $medicine): JsonResponse
    {
        if ($medicine->image_path) {
            $this->images->delete($medicine->image_path);
            $medicine->update(['image_path' => null]);
        }

        return response()->json($medicine);
    }

    public function destroy(Medicine $medicine): JsonResponse
    {
        // Medicines that were already sold stay on record so receipts and reports remain accurate.
        if ($medicine->batches()->exists() || SaleItem::where('medicine_id', $medicine->id)->exists()) {
            $medicine->update(['is_active' => false]);

            return response()->json(['message' => 'Medicine has stock history, so it was deactivated instead.']);
        }

        $this->images->delete($medicine->image_path);
        $medicine->delete();

        return response()->json(['message' => 'Medicine deleted.']);
    }
}
