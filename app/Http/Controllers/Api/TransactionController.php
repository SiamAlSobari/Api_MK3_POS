<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Stock;
use OpenApi\Attributes as OA;

class TransactionController extends Controller
{
    #[OA\Post(
        path: "/transactions",
        summary: "Create a new transaction (SALE/PURCHASE/ADJUSTMENT)",
        tags: ["Transactions"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/TransactionStoreRequest")
        ),
        responses: [
            new OA\Response(response: 201, description: "Transaction created", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", ref: "#/components/schemas/Transaction"),
                ],
                type: "object"
            )),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        // Validasi input sesuai field yang dibutuhkan
        $request->validate([
            "trx_type" => "required|in:SALE,PURCHASE,ADJUSTMENT",
            "trx_date" => "required|date",
            "payment_method" => "required",
            "items" => "required|array",
            "items.*.product_id" => "required|exists:products,id",
            "items.*.quantity" => "required|integer|min:1",
            "items.*.unit_price" => "required|numeric",
        ]);

        return DB::transaction(function () use ($request) {
            // 1. Simpan data utama ke tabel Transactions
            $transaction = Transaction::create([
                "user_id" => $request->user()->id ?? 1,
                "trx_type" => $request->trx_type,
                "trx_date" => $request->trx_date,
                "payment_method" => $request->payment_method,
                "paid_at" => now(),
                "total_amount" => 0,
            ]);

            $totalAmount = 0;
            $itemsData = [];
            $stockChanges = [];

            // 2. Kumpulkan semua data item dulu
            foreach ($request->items as $item) {
                $linePrice = $item["quantity"] * $item["unit_price"];
                $totalAmount += $linePrice;

                $itemsData[] = [
                    "transaction_id" => $transaction->id,
                    "product_id" => $item["product_id"],
                    "quantity" => $item["quantity"],
                    "unit_price" => $item["unit_price"],
                    "line_price" => $linePrice,
                    "created_at" => now(),
                    "updated_at" => now(),
                ];

                if ($request->trx_type === "SALE") {
                    $stockChanges[$item["product_id"]] = ($stockChanges[$item["product_id"]] ?? 0) - $item["quantity"];
                } elseif ($request->trx_type === "PURCHASE") {
                    $stockChanges[$item["product_id"]] = ($stockChanges[$item["product_id"]] ?? 0) + $item["quantity"];
                } elseif ($request->trx_type === "ADJUSTMENT") {
                    $stockChanges[$item["product_id"]] = $item["quantity"];
                }
            }

            // 3. Batch insert semua item (1 query)
            TransactionItem::insert($itemsData);

            // 4. Batch fetch stock (1 query)
            $existingStocks = Stock::whereIn("product_id", array_keys($stockChanges))
                ->get()
                ->keyBy("product_id");

            // 5. Update stock di memory
            foreach ($stockChanges as $productId => $change) {
                $stock = $existingStocks->get($productId);
                if ($stock) {
                    if ($request->trx_type === "ADJUSTMENT") {
                        $stock->update(["stock_on_hand" => $change]);
                    } else {
                        $stock->increment("stock_on_hand", $change);
                    }
                } elseif ($request->trx_type !== "SALE") {
                    Stock::create([
                        "product_id" => $productId,
                        "stock_on_hand" => max(0, $change),
                    ]);
                }
            }

            // 3. Update total akhir di tabel Transactions setelah semua item dihitung
            $transaction->update(["total_amount" => $totalAmount]);

            return response()->json(
                [
                    "message" => "Transaksi berhasil disimpan!",
                    "data" => $transaction->load("items"),
                ],
                201,
            );
        });
    }

    #[OA\Get(
        path: "/transactions",
        summary: "List all transactions (latest 50)",
        tags: ["Transactions"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Transactions retrieved", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Transaction")),
                ],
                type: "object"
            )),
        ]
    )]
    public function index(Request $request)
    {
        $transactions = Transaction::with(["items.product.stocks"])
            ->where("user_id", $request->user()->id)
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            "message" => "Daftar riwayat transaksi berhasil diambil",
            "data" => $transactions,
        ]);
    }

    #[OA\Get(
        path: "/transactions/{id}",
        summary: "Get transaction detail",
        tags: ["Transactions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Transaction detail", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", ref: "#/components/schemas/Transaction"),
                ],
                type: "object"
            )),
            new OA\Response(response: 404, description: "Transaction not found"),
        ]
    )]
    public function show($id)
    {
        // Mengambil transaksi tertentu beserta item barang dan data produknya [cite: 15, 27]
        $transaction = Transaction::with(["user", "items.product"])->find($id);

        if (!$transaction) {
            return response()->json(
                ["message" => "Transaksi tidak ditemukan"],
                404,
            );
        }

        return response()->json([
            "message" => "Detail transaksi berhasil ditemukan",
            "data" => $transaction,
        ]);
    }
}
