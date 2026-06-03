<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    #[OA\Get(
        path: "/categories",
        summary: "List all categories",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Categories retrieved", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Category")),
                ],
                type: "object"
            )),
        ]
    )]
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $categories = Category::where("user_id", $userId)->get();

        return response()->json([
            "message" => "Daftar kategori berhasil diambil",
            "data" => $categories,
        ]);
    }

    #[OA\Get(
        path: "/categories/products",
        summary: "List categories with their products",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Categories with products retrieved", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Category")),
                ],
                type: "object"
            )),
        ]
    )]
    public function getCategoriesWithProducts(Request $request): JsonResponse
    {
        $categories = $request->user()->categories()->with("products")->get();

        return response()->json([
            "message" => "Daftar kategori berhasil diambil",
            "data" => $categories,
        ]);
    }

    #[OA\Post(
        path: "/categories",
        summary: "Create a new category",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/CategoryStoreRequest")
        ),
        responses: [
            new OA\Response(response: 201, description: "Category created", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", ref: "#/components/schemas/Category"),
                ],
                type: "object"
            )),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
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

    #[OA\Get(
        path: "/categories/{category}",
        summary: "Get category by ID",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "category", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Category retrieved", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", ref: "#/components/schemas/Category"),
                ],
                type: "object"
            )),
            new OA\Response(response: 404, description: "Category not found"),
        ]
    )]
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

    #[OA\Put(
        path: "/categories/{category}",
        summary: "Update a category",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "category", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/CategoryUpdateRequest")
        ),
        responses: [
            new OA\Response(response: 200, description: "Category updated", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", ref: "#/components/schemas/Category"),
                ],
                type: "object"
            )),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
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

    #[OA\Delete(
        path: "/categories/{category}",
        summary: "Delete a category",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "category", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Category deleted", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string", example: "Category deleted successfully."),
                ],
                type: "object"
            )),
            new OA\Response(response: 404, description: "Category not found"),
        ]
    )]
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

    #[OA\Patch(
        path: "/categories/{id}/status",
        summary: "Update category active status",
        tags: ["Categories"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/CategoryStatusRequest")
        ),
        responses: [
            new OA\Response(response: 200, description: "Status updated", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", ref: "#/components/schemas/Category"),
                ],
                type: "object"
            )),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function updateStatus(Request $request, $id)
    {
        $category = Category::where('user_id', $request->user()->id)->find($id);

        if (!$category) {
            return response()->json(
                ["message" => "Kategori tidak ditemukan"],
                404,
            );
        }

        $validated = $request->validate([
            "is_active" => ["required", "boolean"],
        ]);

        $category->update([
            "is_active" => $validated['is_active'],
        ]);

        return response()->json([
            "message" => "Status kategori berhasil diubah!",
            "data" => $category,
        ]);
    }
}
