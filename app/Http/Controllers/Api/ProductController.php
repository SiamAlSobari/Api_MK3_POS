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

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $products = Product::where("user_id", $request->user()->id)
            ->with(["category", "stocks"])
            ->get();

        return response()->json([
            "message" => "Products retrieved successfully.",
            "data" => $products,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
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

        $product =DB::transaction(function () use (
            $data,
            $request,
        ) {
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

    /**
     * Display the specified resource.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(["category", "stocks"]);

        return response()->json([
            "message" => "Product retrieved successfully.",
            "data" => $product,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            "name" => ["string", "max:255"],
            "price" => ["numeric", "min:0"],
            "description" => ["nullable", "string"],
            "stock" => ["integer", "min:0"],
            "category_id" => ["nullable", "exists:categories,id"],
        ]);

        $product->update($data);

        return response()->json([
            "message" => "Product updated successfully.",
            "data" => $product->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            "message" => "Product deleted successfully.",
        ]);
    }
}
