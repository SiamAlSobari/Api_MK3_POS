<?php

// =============================================================================
// FILE: ProductController.php
// DESKRIPSI: Controller untuk mengelola data PRODUK.
//            Termasuk upload gambar ke Cloudinary dan pengelolaan stok.
//
// KONSEP PENTING:
// - Setiap produk terhubung ke: user (pemilik), category, dan stocks
// - Gambar produk di-upload ke Cloudinary (layanan cloud storage gambar)
// - Saat buat/update produk dengan stok, otomatis dibuat record Transaction
//   bertipe PURCHASE/ADJUSTMENT sebagai catatan riwayat stok
// =============================================================================

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
    // =========================================================================
    // METHOD: index
    // URL: GET /api/products
    // FUNGSI: Mengambil semua produk milik user yang sedang login.
    //
    // CARA KERJA:
    // - with(["category", "stocks"]) = Eager Loading, mengambil relasi
    //   category dan stocks sekaligus dalam query yang efisien.
    //   Tanpa ini, Laravel akan melakukan query tambahan untuk setiap produk
    //   (masalah N+1 query).
    // =========================================================================
    public function index(Request $request): JsonResponse
    {
        // Ambil semua produk milik user, beserta data kategori dan stoknya
        $products = Product::where("user_id", $request->user()->id)
            ->with(["category", "stocks"]) // Eager load relasi agar tidak N+1 query
            ->get();

        return response()->json([
            "message" => "Products retrieved successfully.",
            "data" => $products,
        ]);
    }

    // =========================================================================
    // METHOD: store
    // URL: POST /api/products
    // FUNGSI: Membuat produk baru beserta upload gambar dan pencatatan stok awal.
    //
    // ALUR KERJA:
    // 1. Validasi semua input (nama, harga, stok, gambar, kategori)
    // 2. Jika ada file gambar -> upload ke Cloudinary -> simpan URL-nya
    // 3. Bungkus dalam DB::transaction agar atomik (semua atau tidak sama sekali):
    //    a. Buat record produk baru
    //    b. Buat record stok awal
    //    c. Jika stok > 0, buat record transaksi PURCHASE sebagai catatan
    // 4. Kembalikan response dengan data produk baru
    // =========================================================================
    public function store(Request $request): JsonResponse
    {
        // Validasi input dari frontend:
        // - 'image' => gambar, maksimal 2MB (2048 KB)
        // - 'exists:categories,id' = category_id harus ada di tabel categories
        $data = $request->validate([
            "name" => ["required", "string", "max:255"],
            "price" => ["required", "numeric", "min:0"],
            "description" => ["nullable", "string"],
            "stock" => ["required", "integer", "min:0"],
            "image" => ["nullable", "image", "max:2048"],
            "category_id" => ["nullable", "exists:categories,id"],
        ]);

        // Tambahkan user_id agar produk terhubung ke user yang login
        $data["user_id"] = $request->user()->id;

        // =====================================================================
        // BAGIAN UPLOAD GAMBAR KE CLOUDINARY
        // Cloudinary = layanan cloud untuk menyimpan gambar/media
        // Alur: Frontend kirim file -> Backend upload ke Cloudinary -> Dapat URL
        // URL tersebut yang disimpan di database (bukan file-nya langsung)
        // =====================================================================
        if ($request->hasFile("image")) {
            try {
                // Ambil file gambar dari request
                $file = $request->file("image");

                // Ambil kredensial Cloudinary dari file .env
                $cloudName = env("CLOUDINARY_CLOUD_NAME");
                $apiKey = env("CLOUDINARY_API_KEY");
                $apiSecret = env("CLOUDINARY_API_SECRET");

                // Kirim gambar ke API Cloudinary menggunakan HTTP Client Laravel
                // asMultipart() = kirim sebagai multipart/form-data (format upload file)
                // withBasicAuth() = autentikasi menggunakan API key & secret
                $response = Http::asMultipart()
                    ->withBasicAuth($apiKey, $apiSecret)
                    ->post(
                        "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload",
                        [
                            "file" => fopen($file->getRealPath(), "r"),
                            "folder" => "pos_products", // Folder di Cloudinary
                        ],
                    );

                // Jika upload berhasil, simpan URL gambar ke data produk
                if ($response->successful()) {
                    $data["image_url"] = $response->json()["secure_url"];
                } else {
                    // Jika upload gagal, kembalikan error 500
                    return response()->json(
                        [
                            "error" => "Upload Cloudinary Gagal",
                            "detail" => $response->json(),
                        ],
                        500,
                    );
                }
            } catch (\Exception $e) {
                // Tangkap error yang tidak terduga (misal: koneksi gagal)
                return response()->json(["error" => $e->getMessage()], 500);
            }
        }

        // =====================================================================
        // BAGIAN SIMPAN PRODUK + STOK + TRANSAKSI (dalam DB Transaction)
        // DB::transaction() menjamin semua operasi di dalamnya berhasil semua
        // atau gagal semua (atomik). Jika salah satu gagal, semuanya di-rollback.
        // =====================================================================
        $product =DB::transaction(function () use (
            $data,
            $request,
        ) {
            // 1. Buat record produk baru di tabel products
            $product = Product::create($data);

            // 2. Buat record stok awal di tabel stocks
            //    stocks() = relasi hasMany dari model Product
            $product->stocks()->create([
                "stock_on_hand" => $data["stock"],
            ]);

            // 3. Jika stok awal > 0, buat transaksi PURCHASE sebagai catatan
            //    bahwa stok ini "dibeli" / masuk ke sistem
            if ($data["stock"] > 0) {
                // Buat transaksi induk (header)
                $transaction = Transaction::create([
                    "user_id" => $request->user()->id,
                    "trx_type" => "PURCHASE",    // Tipe: pembelian stok
                    "trx_date" => now(),
                    "payment_method" => "CASH",
                    "paid_at" => now(),
                    "total_amount" => $data["stock"] * $data["price"],
                ]);

                // Buat item transaksi (detail barang)
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

    // =========================================================================
    // METHOD: show
    // URL: GET /api/products/{id}
    // FUNGSI: Mengambil detail satu produk beserta kategori dan stoknya.
    //
    // CATATAN: $product otomatis di-resolve oleh Route Model Binding
    //          load() = Lazy Eager Loading (muat relasi setelah model di-load)
    // =========================================================================
    public function show(Product $product): JsonResponse
    {
        // load() memuat relasi category dan stocks untuk produk ini
        // Berbeda dengan with() yang dipakai di query builder,
        // load() dipakai setelah model sudah ada (lazy eager loading)
        $product->load(["category", "stocks"]);

        return response()->json([
            "message" => "Product retrieved successfully.",
            "data" => $product,
        ]);
    }

    // =========================================================================
    // METHOD: update
    // URL: PUT/PATCH /api/products/{id}
    // FUNGSI: Mengubah data produk yang sudah ada + tambah stok jika ada.
    //
    // ALUR KERJA:
    // 1. Validasi input (semua field opsional karena partial update)
    // 2. Jika ada gambar baru -> upload ke Cloudinary (sama seperti store)
    // 3. Dalam DB::transaction:
    //    a. Update data produk (nama, harga, deskripsi, dll)
    //    b. Jika ada stok baru > 0:
    //       - Buat record stok baru
    //       - Buat transaksi ADJUSTMENT sebagai catatan penambahan stok
    //
    // PERBEDAAN dengan store:
    // - store = buat produk baru (PURCHASE)
    // - update = ubah produk yang sudah ada, stok tambahan dicatat sebagai ADJUSTMENT
    // =========================================================================
    public function update(Request $request, Product $product): JsonResponse
    {
        // Validasi: semua field opsional (tidak required) karena partial update
        $data = $request->validate([
            "name" => ["string", "max:255"],
            "price" => ["numeric", "min:0"],
            "description" => ["nullable", "string"],
            "stock" => ["integer", "min:0"],
            "category_id" => ["nullable", "exists:categories,id"],
            "image" => ["nullable", "image", "max:2048"],
        ]);

        // Upload gambar ke Cloudinary jika ada file gambar baru
        // (logika sama persis dengan method store di atas)
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

        // =====================================================================
        // UPDATE PRODUK + TAMBAH STOK (dalam DB Transaction)
        // =====================================================================
        DB::transaction(function () use ($data, $request, $product) {
            // Simpan nilai stok yang akan ditambahkan sebelum dihapus dari $data
            $stockToAdd = $data["stock"] ?? 0;
            
            // Remove fields that should not be mass updated to product table directly if necessary
            // e.g. stock, image (since image uses image_url)
            // Hapus field 'stock' dari $data karena stok disimpan di tabel terpisah
            unset($data["stock"]);
            // Hapus field 'image' (file) karena yang disimpan adalah 'image_url' (URL)
            if (isset($data["image"])) unset($data["image"]);

            // Update data produk di tabel products
            $product->update($data);

            // Jika ada stok yang perlu ditambahkan
            if ($stockToAdd > 0) {
                // Determine current price or updated price
                // Gunakan harga yang baru di-update, atau harga lama jika tidak diubah
                $price = $data["price"] ?? $product->price;

                // Buat record stok baru di tabel stocks
                $product->stocks()->create([
                    "stock_on_hand" => $stockToAdd,
                ]);

                // Buat transaksi ADJUSTMENT sebagai catatan penambahan stok
                // ADJUSTMENT berbeda dengan PURCHASE:
                // - PURCHASE = pembelian stok pertama kali
                // - ADJUSTMENT = penyesuaian/penambahan stok setelahnya
                $transaction = Transaction::create([
                    "user_id" => $request->user()->id,
                    "trx_type" => "ADJUSTMENT",
                    "trx_date" => now(),
                    "payment_method" => "CASH",
                    "paid_at" => now(),
                    "total_amount" => $stockToAdd * $price,
                ]);

                // Catat detail item dalam transaksi adjustment
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
            // fresh() = ambil ulang data terbaru dari database beserta relasi
            "data" => $product->fresh(["category", "stocks"]),
        ]);
    }

    // =========================================================================
    // METHOD: destroy
    // URL: DELETE /api/products/{id}
    // FUNGSI: Menghapus produk dari database.
    // =========================================================================
    public function destroy(Product $product): JsonResponse
    {
        // Hapus produk: DELETE FROM products WHERE id = ?
        $product->delete();

        return response()->json([
            "message" => "Product deleted successfully.",
        ]);
    }
}
