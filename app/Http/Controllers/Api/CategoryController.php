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
        $userId = $request->user()->id;

        $categories = Category::where("user_id", $userId)->get();

        return response()->json([
            "message" => "Daftar kategori berhasil diambil",
            "data" => $categories,
        ]);
    }

    public function getCategoriesWithProducts(Request $request): JsonResponse
    {
        $categories = $request->user()->categories()->with("products")->get();

        return response()->json([
            "message" => "Daftar kategori berhasil diambil",
            "data" => $categories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            "name" => ["required", "string", "max:255"],
            "description" => ["nullable", "string"],
        ]);

        $data["user_id"] = $request->user()->id;

        $category = Category::create($data);

        return response()->json(
            [
                "message" => "Category created successfully.",
                "data" => $category,
            ],
            201,
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Category $category): JsonResponse
    {
        if ($category->user_id !== $request->user()->id) {
            return response()->json(["message" => "Kategori tidak ditemukan"], 404);
        }

        return response()->json([
            "message" => "Category retrieved successfully.",
            "data" => $category,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        if ($category->user_id !== $request->user()->id) {
            return response()->json(["message" => "Kategori tidak ditemukan"], 404);
        }

        $data = $request->validate([
            "name" => ["string", "max:255"],
            "description" => ["nullable", "string"],
        ]);

        $category->update($data);

        return response()->json([
            "message" => "Category updated successfully.",
            "data" => $category->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        if ($category->user_id !== $request->user()->id) {
            return response()->json(["message" => "Kategori tidak ditemukan"], 404);
        }

        $category->delete();

        return response()->json([
            "message" => "Category deleted successfully.",
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $category = Category::where('user_id', $request->user()->id)->find($id);

        if (!$category) {
            return response()->json(
                ["message" => "Kategori tidak ditemukan"],
                404,
            );
        }

        $request->validate([
            "isActive" => ["required", "boolean"],
        ]);

        $category->update([
            "is_active" => $request->isActive,
        ]);

        return response()->json([
            "message" => "Status kategori berhasil diubah!",
            "data" => $category,
        ]);
    }
}
