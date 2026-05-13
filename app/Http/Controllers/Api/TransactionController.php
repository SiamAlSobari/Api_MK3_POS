<?php

// =============================================================================
// FILE: TransactionController.php
// DESKRIPSI: Controller untuk mengelola TRANSAKSI jual beli.
//            Mendukung 3 tipe transaksi: SALE, PURCHASE, dan ADJUSTMENT.
//
// KONSEP PENTING:
// - SALE = Penjualan (stok berkurang)
// - PURCHASE = Pembelian stok baru (stok bertambah)
// - ADJUSTMENT = Penyesuaian stok fisik (stok di-set ke angka tertentu)
// - Setiap transaksi punya: 1 header (Transaction) + banyak item (TransactionItem)
//   Ini disebut pola "Master-Detail" / "Header-Lines"
// =============================================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Stock;

class TransactionController extends Controller
{
    // =========================================================================
    // METHOD: store
    // URL: POST /api/transactions
    // FUNGSI: Menyimpan transaksi baru (SALE/PURCHASE/ADJUSTMENT) beserta
    //         item-item barangnya, dan mengupdate stok secara otomatis.
    //
    // ALUR KERJA:
    // 1. Validasi semua input (tipe transaksi, tanggal, metode bayar, items)
    // 2. Dalam DB::transaction (agar atomik / semua atau tidak sama sekali):
    //    a. Buat record Transaction (header)
    //    b. Loop setiap item:
    //       - Hitung line_price (harga total per item)
    //       - Buat record TransactionItem
    //       - Update stok sesuai tipe transaksi:
    //         * SALE -> stok dikurangi (decrement)
    //         * PURCHASE -> stok ditambah (increment)
    //         * ADJUSTMENT -> stok di-set ke angka yang diinputkan
    //    c. Update total_amount di header transaksi
    // 3. Kembalikan response dengan data transaksi lengkap
    //
    // CATATAN: DB::transaction() dengan return di dalamnya -> nilai return
    //          akan diteruskan keluar. Jadi response()->json() di dalam
    //          DB::transaction() akan menjadi return value method store().
    // =========================================================================
    public function store(Request $request)
    {
        // Validasi input sesuai field yang dibutuhkan
        // 'in:SALE,PURCHASE,ADJUSTMENT' = nilai harus salah satu dari 3 tipe ini
        // 'items' = array dari barang-barang yang ditransaksikan
        // 'items.*.product_id' = setiap item harus punya product_id yang valid di DB
        // 'items.*.quantity' = jumlah barang, minimal 1
        // 'items.*.unit_price' = harga satuan per barang
        $request->validate([
            "trx_type" => "required|in:SALE,PURCHASE,ADJUSTMENT",
            "trx_date" => "required|date",
            "payment_method" => "required",
            "items" => "required|array",
            "items.*.product_id" => "required|exists:products,id",
            "items.*.quantity" => "required|integer|min:1",
            "items.*.unit_price" => "required|numeric",
        ]);

        // DB::transaction() memastikan semua operasi database di dalamnya
        // berhasil semua atau gagal semua (ACID - Atomicity)
        return DB::transaction(function () use ($request) {
            // 1. Simpan data utama ke tabel Transactions (header transaksi)
            //    total_amount diisi 0 dulu, nanti di-update setelah semua item dihitung
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
                // Hitung harga total per item (quantity x harga satuan)
                $linePrice = $item["quantity"] * $item["unit_price"];
                $totalAmount += $linePrice;

                // Create Transaction Item (detail barang dalam transaksi)
                TransactionItem::create([
                    "transaction_id" => $transaction->id,
                    "product_id" => $item["product_id"],
                    "quantity" => $item["quantity"],
                    "unit_price" => $item["unit_price"],
                    "line_price" => $linePrice,
                ]);

                // Update Stock berdasarkan tipe transaksi
                // Cari record stok berdasarkan product_id
                $stock = Stock::where(
                    "product_id",
                    $item["product_id"],
                )->first();

                if ($stock) {
                    // Stok sudah ada di database
                    if ($request->trx_type === "SALE") {
                        // SALE: Kurangi stok (barang terjual keluar)
                        // decrement() = mengurangi nilai kolom secara atomik
                        $stock->decrement("stock_on_hand", $item["quantity"]);
                    } elseif ($request->trx_type === "PURCHASE") {
                        // PURCHASE: Tambah stok (barang masuk dari pembelian)
                        // increment() = menambah nilai kolom secara atomik
                        $stock->increment("stock_on_hand", $item["quantity"]);
                    } elseif ($request->trx_type === "ADJUSTMENT") {
                        // Jika adjustment, ubah stok menjadi sama persis dengan quantity yang diinputkan (stok fisik aktual)
                        // Ini dipakai saat stok fisik berbeda dengan stok di sistem
                        $stock->update(["stock_on_hand" => $item["quantity"]]);
                    }
                } else {
                    // Jika stok belum ada (produk baru belum punya record stok)
                    // Buat record stok baru hanya untuk PURCHASE atau ADJUSTMENT
                    if (
                        $request->trx_type === "PURCHASE" ||
                        $request->trx_type === "ADJUSTMENT"
                    ) {
                        Stock::create([
                            "product_id" => $item["product_id"],
                            "stock_on_hand" => $item["quantity"],
                        ]);
                    }
                    // Catatan: SALE tidak buat stok baru karena tidak masuk akal
                    // menjual barang yang belum punya stok
                }
            }

            // 3. Update total akhir di tabel Transactions setelah semua item dihitung
            $transaction->update(["total_amount" => $totalAmount]);

            // Kembalikan response dengan data transaksi + relasi items
            // load("items") = lazy eager load relasi items setelah create
            return response()->json(
                [
                    "message" => "Transaksi berhasil disimpan!",
                    "data" => $transaction->load("items"),
                ],
                201,
            );
        });
    }

    // =========================================================================
    // METHOD: index
    // URL: GET /api/transactions
    // FUNGSI: Mengambil semua riwayat transaksi milik user yang login.
    //
    // CARA KERJA:
    // - with(["items.product.stocks"]) = Eager Loading bertingkat:
    //   Transaction -> TransactionItem -> Product -> Stock
    //   Ini memuat semua relasi sekaligus agar tidak terjadi N+1 query problem
    // =========================================================================
    // 1. Fungsi untuk melihat riwayat semua transaksi berdasarkan user yang login (History)
    public function index(Request $request)
    {
        // Ambil semua transaksi milik user beserta detail item, produk, dan stoknya
        // with() = Eager Loading untuk menghindari N+1 query
        $transactions = Transaction::with(["items.product.stocks"])
            ->where("user_id", $request->user()->id)
            ->get();

        return response()->json([
            "message" => "Daftar riwayat transaksi berhasil diambil",
            "data" => $transactions,
        ]);
    }

    // =========================================================================
    // METHOD: show
    // URL: GET /api/transactions/{id}
    // FUNGSI: Mengambil detail satu transaksi berdasarkan ID.
    //
    // CARA KERJA:
    // - with(["user", "items.product"]) = Muat relasi user (siapa yang buat)
    //   dan items beserta data produknya
    // - find($id) = cari berdasarkan primary key, return null jika tidak ada
    // =========================================================================
    // 2. Fungsi untuk melihat detail satu transaksi (Detail)
    public function show($id)
    {
        // Mengambil transaksi tertentu beserta item barang dan data produknya [cite: 15, 27]
        // find($id) = SELECT * FROM transactions WHERE id = ?
        $transaction = Transaction::with(["user", "items.product"])->find($id);

        // Jika transaksi tidak ditemukan, kembalikan 404
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
