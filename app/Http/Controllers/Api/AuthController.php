<?php

// =============================================================================
// FILE: AuthController.php
// DESKRIPSI: Controller ini menangani semua proses AUTENTIKASI user,
//            yaitu Register (daftar akun baru), Login, dan Cek Session.
//
// KONSEP PENTING:
// - Controller adalah kelas yang berisi fungsi-fungsi untuk menangani
//   request dari frontend.
// - Setiap method public di sini dipanggil oleh route di file api.php
// - Laravel Sanctum digunakan untuk sistem token autentikasi.
//   Token ini dikirim frontend di header "Authorization: Bearer <token>"
// - JsonResponse = tipe return berupa JSON (format standar API)
// =============================================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    // =========================================================================
    // METHOD: register
    // URL: POST /api/auth/register
    // FUNGSI: Mendaftarkan user baru ke sistem.
    //
    // ALUR KERJA:
    // 1. Validasi input (nama, email unik, password minimal 6 karakter)
    // 2. Buat user baru di database
    // 3. Panggil seedInitialUserData() untuk mengisi data contoh (produk, kategori)
    // 4. Kembalikan response JSON dengan data user baru (status 201 = Created)
    // =========================================================================
    public function register(Request $request): JsonResponse
    {
        // Validasi input dari frontend:
        // - 'required' = wajib diisi
        // - 'string' = harus berupa teks
        // - 'email' = harus format email yang valid
        // - 'unique:users,email' = email tidak boleh sudah terdaftar di tabel users
        // - 'min:6' = minimal 6 karakter
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        // Buat user baru di tabel 'users'
        // Password otomatis di-hash oleh model User (mutator di model)
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        // Jalankan fungsi seed terpisah
        // Ini mengisi data contoh agar user baru tidak melihat aplikasi kosong
        $this->seedInitialUserData($user);

        // Kembalikan response sukses dengan HTTP status 201 (Created)
        return response()->json([
            'message' => 'Register berhasil.',
            'user' => $user,
        ], 201);
    }

    // =========================================================================
    // METHOD: seedInitialUserData (Private - hanya dipanggil internal)
    // FUNGSI: Mengisi data awal (kategori, produk, stok, transaksi pembelian)
    //         untuk user yang baru register agar aplikasi tidak kosong.
    //
    // ALUR KERJA:
    // 1. Bungkus semua operasi dalam DB::transaction (jika salah satu gagal,
    //    SEMUA dibatalkan - ini disebut "atomicity")
    // 2. Buat transaksi induk bertipe PURCHASE (pembelian awal)
    // 3. Loop setiap item data awal:
    //    - Buat kategori -> buat produk -> buat stok -> catat item transaksi
    // 4. Update total_amount transaksi setelah semua item dihitung
    // =========================================================================
    private function seedInitialUserData(User $user): void
    {
        // DB::transaction() = jika salah satu query gagal, semua query di dalamnya
        // akan di-rollback (dibatalkan). Ini menjaga konsistensi data.
        DB::transaction(function () use ($user) {
            // Data contoh produk yang akan dibuat untuk user baru
            $initialData = [
                ['category' => 'Kebutuhan Rumah Tangga', 'product' => 'Sabun cuci piring', 'price' => 15000, 'stock' => 12],
                ['category' => 'Makanan', 'product' => 'Mi instant', 'price' => 3000, 'stock' => 40], // 1 dus mi instan
                ['category' => 'Minuman', 'product' => 'Air mineral 600ml', 'price' => 3500, 'stock' => 24], // 1 dus air mineral
                ['category' => 'Sembako', 'product' => 'Beras 5 kg', 'price' => 70000, 'stock' => 5], // Cukup 5 karung
            ];

            // 1. Buat Transaksi Induk bertipe PURCHASE (pembelian stok awal)
            //    total_amount diisi 0 dulu, nanti di-update setelah semua item dihitung
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'trx_type' => 'PURCHASE',
                'trx_date' => now(),
                'payment_method' => 'CASH',
                'paid_at' => now(),
                'total_amount' => 0,
            ]);

            $totalAmount = 0;

            // Loop setiap data contoh untuk membuat kategori, produk, stok, dan item transaksi
            foreach ($initialData as $data) {
                // 2. Buat Kategori untuk user ini
                $category = Category::create([
                    'name' => $data['category'],
                    'isActive' => true,
                    'user_id' => $user->id,
                ]);

                // 3. Buat Produk yang terhubung ke kategori di atas
                $product = Product::create([
                    'name' => $data['product'],
                    'price' => $data['price'],
                    'description' => $data['product'] . ' adalah produk contoh.',
                    'image_url' => 'https://placehold.co/400x400?text=' . urlencode($data['product']),
                    'category_id' => $category->id,
                    'is_active' => true,
                    'user_id' => $user->id,
                ]);

                // 4. Tambah Stok awal ke tabel stocks
                //    stocks() adalah relasi hasMany di model Product
                $stokAwal = $data['stock'];
                $product->stocks()->create([
                    'stock_on_hand' => $stokAwal,
                ]);

                // 5. Catat Item Transaksi (detail barang yang dibeli)
                //    line_price = harga total per item (qty x harga satuan)
                $linePrice = $stokAwal * $product->price;
                $totalAmount += $linePrice;

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'quantity' => $stokAwal,
                    'unit_price' => $product->price,
                    'line_price' => $linePrice,
                ]);
            }

            // 6. Update Total Amount Transaksi setelah semua item selesai dihitung
            $transaction->update(['total_amount' => $totalAmount]);
        });
    }

    // =========================================================================
    // METHOD: login
    // URL: POST /api/auth/login
    // FUNGSI: Memverifikasi kredensial user dan mengembalikan token API.
    //
    // ALUR KERJA:
    // 1. Validasi input (email dan password)
    // 2. Cari user berdasarkan email
    // 3. Cek apakah password cocok menggunakan Hash::check()
    // 4. Jika cocok, buat token baru via Sanctum -> kembalikan token ke frontend
    // 5. Jika tidak cocok, kembalikan error 401 (Unauthorized)
    //
    // Token ini nanti dikirim frontend di header setiap request:
    //   Authorization: Bearer <token_disini>
    // =========================================================================
    public function login(Request $request): JsonResponse
    {
        // Validasi: email dan password wajib diisi
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Cari user berdasarkan email di database
        // first() = ambil 1 record pertama yang cocok, atau null jika tidak ada
        $user = User::where('email', $credentials['email'])->first();

        // Cek apakah user ditemukan DAN password cocok
        // Hash::check() membandingkan password plain text dengan hash di database
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            // Jika gagal, kembalikan error 401 (Unauthorized)
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        // Buat token API baru menggunakan Laravel Sanctum
        // 'mobile-app' adalah nama token (untuk identifikasi saja)
        // plainTextToken = token dalam bentuk teks biasa yang dikirim ke frontend
        $token = $user->createToken('mobile-app')->plainTextToken;

        // Kembalikan token dan data user ke frontend
        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }
    
    // =========================================================================
    // METHOD: checkSession
    // URL: GET /api/auth/session
    // FUNGSI: Mengecek apakah token yang dikirim frontend masih valid.
    //         Digunakan frontend saat buka aplikasi untuk mengecek status login.
    //
    // CATATAN: Route ini sudah dilindungi middleware auth:sanctum,
    //          jadi jika token tidak valid, Laravel otomatis menolak dengan 401
    //          SEBELUM method ini dipanggil. Method ini hanya dipanggil jika
    //          token sudah valid.
    // =========================================================================
    public function checkSession(Request $request): JsonResponse
    {
        // $request->user() mengembalikan objek User berdasarkan token Sanctum
        // Jika token valid, pasti ada user-nya
        if ($request->user()) {
            return response()->json([
                'message' => 'Session valid.',
                'user' => $request->user(),
            ]);
        } else {
            // Kasus ini seharusnya jarang terjadi karena middleware sudah cek duluan
            return response()->json([
                'message' => 'Session tidak valid.',
            ], 401);
        }
    }
}
