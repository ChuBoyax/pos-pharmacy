<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Category::withCount('medicines')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $category = Category::create($request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories'],
        ]));

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $category->update($request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories')->ignore($category->id)],
        ]));

        return response()->json($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}
