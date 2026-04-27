<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Stock;

class TransactionController extends Controller
{
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

            // 2. Simpan setiap barang ke tabel TransactionItems & Update Stok
            foreach ($request->items as $item) {
                $linePrice = $item["quantity"] * $item["unit_price"];
                $totalAmount += $linePrice;

                // Create Transaction Item
                TransactionItem::create([
                    "transaction_id" => $transaction->id,
                    "product_id" => $item["product_id"],
                    "quantity" => $item["quantity"],
                    "unit_price" => $item["unit_price"],
                    "line_price" => $linePrice,
                ]);

                // Update Stock
                $stock = Stock::where(
                    "product_id",
                    $item["product_id"],
                )->first();

                if ($stock) {
                    if ($request->trx_type === "SALE") {
                        $stock->decrement("stock_on_hand", $item["quantity"]);
                    } elseif ($request->trx_type === "PURCHASE") {
                        $stock->increment("stock_on_hand", $item["quantity"]);
                    } elseif ($request->trx_type === "ADJUSTMENT") {
                        // Jika adjustment, ubah stok menjadi sama persis dengan quantity yang diinputkan (stok fisik aktual)
                        $stock->update(["stock_on_hand" => $item["quantity"]]);
                    }
                } else {
                    // Jika stok belum ada
                    if (
                        $request->trx_type === "PURCHASE" ||
                        $request->trx_type === "ADJUSTMENT"
                    ) {
                        Stock::create([
                            "product_id" => $item["product_id"],
                            "stock_on_hand" => $item["quantity"],
                        ]);
                    }
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

    // 1. Fungsi untuk melihat riwayat semua transaksi berdasarkan user yang login (History)
    public function index(Request $request)
    {
        $transactions = Transaction::with(["items.product.stocks"])
            ->where("user_id", $request->user()->id)
            ->get();

        return response()->json([
            "message" => "Daftar riwayat transaksi berhasil diambil",
            "data" => $transactions,
        ]);
    }

    // 2. Fungsi untuk melihat detail satu transaksi (Detail)
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
