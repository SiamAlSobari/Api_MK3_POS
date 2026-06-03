<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    #[OA\Get(
        path: "/products",
        summary: "List all products",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Products retrieved", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Product")),
                ],
                type: "object"
            )),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $products = Product::where("user_id", $request->user()->id)
            ->with(["category", "stocks"])
            ->latest()
            ->get();

        return response()->json([
            "message" => "Products retrieved successfully.",
            "data" => $products,
        ]);
    }

    #[OA\Post(
        path: "/products",
        summary: "Create a new product",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(ref: "#/components/schemas/ProductStoreRequest")
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Product created", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", ref: "#/components/schemas/Product"),
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
            "price" => ["required", "numeric", "min:0"],
            "description" => ["nullable", "string"],
            "stock" => ["required", "integer", "min:0"],
            "image" => ["nullable", "image", "max:2048"],
            "category_id" => ["nullable", "exists:categories,id"],
        ]);

        $data["user_id"] = $request->user()->id;

        if ($request->hasFile("image")) {
            try {
                $file = $request->file("image");

                $cloudName = env("CLOUDINARY_CLOUD_NAME");
                $apiKey = env("CLOUDINARY_API_KEY");
                $apiSecret = env("CLOUDINARY_API_SECRET");

                $response = Http::asMultipart()
                    ->withBasicAuth($apiKey, $apiSecret)
                    ->post(
                        "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload",
                        [
                            "file" => fopen($file->getRealPath(), "r"),
                            "folder" => "pos_products",
                        ],
                    );

                if ($response->successful()) {
                    $data["image_url"] = $response->json()["secure_url"];
                } else {
                    return response()->json(
                        [
                            "error" => "Upload Cloudinary Gagal",
                            "detail" => $response->json(),
                        ],
                        500,
                    );
                }
            } catch (\Exception $e) {
                return response()->json(["error" => $e->getMessage()], 500);
            }
        }

        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create($data);

            $product->stocks()->create([
                "stock_on_hand" => $data["stock"],
            ]);

            if ($data["stock"] > 0) {
                $transaction = Transaction::create([
                    "user_id" => $request->user()->id,
                    "trx_type" => "PURCHASE",
                    "trx_date" => now(),
                    "payment_method" => "CASH",
                    "paid_at" => now(),
                    "total_amount" => $data["stock"] * $data["price"],
                ]);

                TransactionItem::create([
                    "transaction_id" => $transaction->id,
                    "product_id" => $product->id,
                    "quantity" => $data["stock"],
                    "unit_price" => $data["price"],
                    "line_price" => $data["stock"] * $data["price"],
                ]);
            }

            return $product;
        });

        return response()->json(
            [
                "message" => "Product created successfully.",
                "data" => $product,
            ],
            201,
        );
    }

    #[OA\Get(
        path: "/products/{product}",
        summary: "Get product by ID",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "product", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Product retrieved", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", ref: "#/components/schemas/Product"),
                ],
                type: "object"
            )),
            new OA\Response(response: 404, description: "Product not found"),
        ]
    )]
    public function show(Product $product): JsonResponse
    {
        $product->load(["category", "stocks"]);

        return response()->json([
            "message" => "Product retrieved successfully.",
            "data" => $product,
        ]);
    }

    #[OA\Put(
        path: "/products/{product}",
        summary: "Update a product",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "product", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(ref: "#/components/schemas/ProductUpdateRequest")
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Product updated", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", ref: "#/components/schemas/Product"),
                ],
                type: "object"
            )),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            "name" => ["string", "max:255"],
            "price" => ["numeric", "min:0"],
            "description" => ["nullable", "string"],
            "stock" => ["integer", "min:0"],
            "category_id" => ["nullable", "exists:categories,id"],
            "image" => ["nullable", "image", "max:2048"],
        ]);

        if ($request->hasFile("image")) {
            try {
                $file = $request->file("image");

                $cloudName = env("CLOUDINARY_CLOUD_NAME");
                $apiKey = env("CLOUDINARY_API_KEY");
                $apiSecret = env("CLOUDINARY_API_SECRET");

                $response = Http::asMultipart()
                    ->withBasicAuth($apiKey, $apiSecret)
                    ->post(
                        "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload",
                        [
                            "file" => fopen($file->getRealPath(), "r"),
                            "folder" => "pos_products",
                        ],
                    );

                if ($response->successful()) {
                    $data["image_url"] = $response->json()["secure_url"];
                } else {
                    return response()->json(
                        [
                            "error" => "Upload Cloudinary Gagal",
                            "detail" => $response->json(),
                        ],
                        500,
                    );
                }
            } catch (\Exception $e) {
                return response()->json(["error" => $e->getMessage()], 500);
            }
        }

        DB::transaction(function () use ($data, $request, $product) {
            $stockToAdd = $data["stock"] ?? 0;

            // Remove fields that should not be mass updated to product table directly if necessary
            // e.g. stock, image (since image uses image_url)
            unset($data["stock"]);
            if (isset($data["image"])) {
                unset($data["image"]);
            }

            $product->update($data);

            if ($stockToAdd > 0) {
                // Determine current price or updated price
                $price = $data["price"] ?? $product->price;

                $product->stocks()->create([
                    "stock_on_hand" => $stockToAdd,
                ]);

                $transaction = Transaction::create([
                    "user_id" => $request->user()->id,
                    "trx_type" => "ADJUSTMENT",
                    "trx_date" => now(),
                    "payment_method" => "CASH",
                    "paid_at" => now(),
                    "total_amount" => $stockToAdd * $price,
                ]);

                TransactionItem::create([
                    "transaction_id" => $transaction->id,
                    "product_id" => $product->id,
                    "quantity" => $stockToAdd,
                    "unit_price" => $price,
                    "line_price" => $stockToAdd * $price,
                ]);
            }
        });

        return response()->json([
            "message" => "Product updated successfully.",
            "data" => $product->fresh(["category", "stocks"]),
        ]);
    }

    #[OA\Delete(
        path: "/products/{product}",
        summary: "Delete a product",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "product", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Product deleted", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string", example: "Product deleted successfully."),
                ],
                type: "object"
            )),
            new OA\Response(response: 404, description: "Product not found"),
        ]
    )]
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            "message" => "Product deleted successfully.",
        ]);
    }
}
