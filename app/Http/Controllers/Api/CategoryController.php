<?php

// =============================================================================
// FILE: CategoryController.php
// DESKRIPSI: Controller untuk mengelola data KATEGORI produk.
//            Menggunakan pola CRUD (Create, Read, Update, Delete).
//
// KONSEP PENTING:
// - Setiap kategori "milik" satu user (user_id). Jadi user A tidak bisa
//   melihat atau mengedit kategori milik user B.
// - Route Model Binding: Laravel otomatis mengambil data Category dari
//   database berdasarkan ID di URL. Misal URL /categories/5, maka
//   parameter $category sudah berisi objek Category dengan id=5.
// =============================================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // =========================================================================
    // METHOD: index
    // URL: GET /api/categories
    // FUNGSI: Mengambil semua kategori milik user yang sedang login.
    //
    // CARA KERJA:
    // - $request->user()->id = ID user yang sedang login (dari token Sanctum)
    // - Category::where('user_id', ...) = filter hanya kategori milik user ini
    // =========================================================================
    public function index(Request $request)
    {
        // Ambil ID user dari token yang dikirim frontend
        $userId = $request->user()->id;

        // Query: SELECT * FROM categories WHERE user_id = ?
        $categories = Category::where("user_id", $userId)->get();

        return response()->json([
            "message" => "Daftar kategori berhasil diambil",
            "data" => $categories,
        ]);
    }

    // =========================================================================
    // METHOD: getCategoriesWithProducts
    // URL: GET /api/categories/products
    // FUNGSI: Mengambil semua kategori BESERTA produk-produk di dalamnya.
    //
    // CARA KERJA:
    // - with("products") = Eager Loading, mengambil relasi products sekaligus
    //   dalam 1 query (lebih efisien daripada lazy loading yang query satu-satu)
    // - $request->user()->categories() = mengakses relasi hasMany di model User
    // =========================================================================
    public function getCategoriesWithProducts(Request $request): JsonResponse
    {
        // Ambil semua kategori milik user beserta produk-produknya
        // Ini menghasilkan query: SELECT * FROM categories WHERE user_id = ?
        // + SELECT * FROM products WHERE category_id IN (...)
        $categories = $request->user()->categories()->with("products")->get();

        return response()->json([
            "message" => "Daftar kategori berhasil diambil",
            "data" => $categories,
        ]);
    }

    // =========================================================================
    // METHOD: store
    // URL: POST /api/categories
    // FUNGSI: Membuat kategori baru.
    //
    // ALUR KERJA:
    // 1. Validasi input (nama wajib, deskripsi opsional)
    // 2. Tambahkan user_id agar kategori terhubung ke user yang login
    // 3. Simpan ke database -> kembalikan response 201 (Created)
    // =========================================================================
    public function store(Request $request): JsonResponse
    {
        // Validasi: nama wajib (required), deskripsi boleh kosong (nullable)
        $data = $request->validate([
            "name" => ["required", "string", "max:255"],
            "description" => ["nullable", "string"],
        ]);

        // Tambahkan user_id agar kategori ini terhubung ke user yang login
        $data["user_id"] = $request->user()->id;

        // Simpan ke database menggunakan mass assignment
        // Category::create() = INSERT INTO categories (...) VALUES (...)
        $category = Category::create($data);

        return response()->json(
            [
                "message" => "Category created successfully.",
                "data" => $category,
            ],
            201, // HTTP 201 = Created (data baru berhasil dibuat)
        );
    }

    // =========================================================================
    // METHOD: show
    // URL: GET /api/categories/{id}
    // FUNGSI: Mengambil detail satu kategori berdasarkan ID.
    //
    // KEAMANAN: Cek apakah kategori ini milik user yang login.
    //           Jika bukan, kembalikan 404 (Not Found) bukan 403 (Forbidden)
    //           agar tidak membocorkan bahwa data itu ada tapi milik orang lain.
    //
    // CATATAN: Parameter $category otomatis di-resolve oleh Laravel dari URL
    //          berkat fitur "Route Model Binding"
    // =========================================================================
    public function show(Request $request, Category $category): JsonResponse
    {
        // Cek kepemilikan: pastikan kategori ini milik user yang sedang login
        if ($category->user_id !== $request->user()->id) {
            return response()->json(["message" => "Kategori tidak ditemukan"], 404);
        }

        return response()->json([
            "message" => "Category retrieved successfully.",
            "data" => $category,
        ]);
    }

    // =========================================================================
    // METHOD: update
    // URL: PUT/PATCH /api/categories/{id}
    // FUNGSI: Mengubah data kategori yang sudah ada.
    //
    // ALUR KERJA:
    // 1. Cek kepemilikan (user_id)
    // 2. Validasi input (field tidak wajib karena ini update parsial)
    // 3. Update data di database
    // 4. Kembalikan data yang sudah di-update menggunakan fresh()
    //
    // CATATAN: fresh() = mengambil ulang data terbaru dari database
    // =========================================================================
    public function update(Request $request, Category $category): JsonResponse
    {
        // Cek kepemilikan
        if ($category->user_id !== $request->user()->id) {
            return response()->json(["message" => "Kategori tidak ditemukan"], 404);
        }

        // Validasi: field tidak required karena ini partial update
        // User bisa update nama saja, deskripsi saja, atau keduanya
        $data = $request->validate([
            "name" => ["string", "max:255"],
            "description" => ["nullable", "string"],
        ]);

        // Update data di database: UPDATE categories SET ... WHERE id = ?
        $category->update($data);

        return response()->json([
            "message" => "Category updated successfully.",
            // fresh() = ambil data terbaru dari database setelah update
            "data" => $category->fresh(),
        ]);
    }

    // =========================================================================
    // METHOD: destroy
    // URL: DELETE /api/categories/{id}
    // FUNGSI: Menghapus kategori berdasarkan ID.
    //
    // KEAMANAN: Cek kepemilikan sebelum menghapus.
    // =========================================================================
    public function destroy(Request $request, Category $category): JsonResponse
    {
        // Cek kepemilikan
        if ($category->user_id !== $request->user()->id) {
            return response()->json(["message" => "Kategori tidak ditemukan"], 404);
        }

        // Hapus dari database: DELETE FROM categories WHERE id = ?
        $category->delete();

        return response()->json([
            "message" => "Category deleted successfully.",
        ]);
    }

    // =========================================================================
    // METHOD: updateStatus
    // URL: PATCH /api/categories/{id}/status
    // FUNGSI: Mengubah status aktif/nonaktif kategori (toggle on/off).
    //
    // ALUR KERJA:
    // 1. Cari kategori berdasarkan ID DAN user_id (keamanan)
    // 2. Validasi input: is_active harus boolean (true/false)
    // 3. Update kolom is_active di database
    //
    // CATATAN: Berbeda dengan show/update/destroy yang pakai Route Model Binding,
    //          method ini manual query karena route-nya custom (bukan apiResource)
    // =========================================================================
    public function updateStatus(Request $request, $id)
    {
        // Cari kategori berdasarkan ID DAN user_id sekaligus (keamanan)
        // Ini memastikan user hanya bisa update status kategorinya sendiri
        $category = Category::where('user_id', $request->user()->id)->find($id);

        // Jika tidak ditemukan (ID salah atau bukan milik user ini)
        if (!$category) {
            return response()->json(
                ["message" => "Kategori tidak ditemukan"],
                404,
            );
        }

        // Validasi: is_active wajib dan harus boolean (true/false)
        $validated = $request->validate([
            "is_active" => ["required", "boolean"],
        ]);

        // Update status di database
        $category->update([
            "is_active" => $validated['is_active'],
        ]);

        return response()->json([
            "message" => "Status kategori berhasil diubah!",
            "data" => $category,
        ]);
    }
}
