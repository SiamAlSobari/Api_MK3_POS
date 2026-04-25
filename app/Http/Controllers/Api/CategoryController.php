<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
{
    // Menggunakan $request->user()->id agar aman dari error 'undefined method id'
    $userId = $request->user()->id ?? 1; 
    $search = $request->query('search');

    $categories = Category::with('products') // Ini untuk +products
        ->where('user_id', $userId) // Filter berdasarkan user login
        ->when($search, function($query) use ($search) {
            $query->where('name', 'like', '%' . $search . '%'); // Fitur cari
        })
        ->get();

    return response()->json([
        'message' => 'Daftar kategori berhasil diambil',
        'data' => $categories
    ]);
}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $category = Category::create($data);

        return response()->json([
            'message' => 'Category created successfully.',
            'data' => $category,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category): JsonResponse
    {
        return response()->json([
            'message' => 'Category retrieved successfully.',
            'data' => $category,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $category->update($data);

        return response()->json([
            'message' => 'Category updated successfully.',
            'data' => $category->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
    $category = Category::find($id);

    if (!$category) {
        return response()->json(['message' => 'Kategori tidak ditemukan'], 404);
    }

    $category->update([
        'isActive' => $request->isActive
    ]);

    return response()->json([
        'message' => 'Status kategori berhasil diubah!',
        'data' => $category
    ]);
    }
}
