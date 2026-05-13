<?php

// =============================================================================
// FILE: routes/api.php
// DESKRIPSI: File ini berisi semua definisi "rute" (route) untuk API backend.
//            Route adalah peta yang menghubungkan URL request dari frontend
//            ke fungsi (method) tertentu di Controller.
//
// KONSEP PENTING:
// - Route::get(...)   -> Untuk mengambil/membaca data (READ)
// - Route::post(...)  -> Untuk membuat/mengirim data baru (CREATE)
// - Route::patch(...) -> Untuk mengubah sebagian data (PARTIAL UPDATE)
// - Route::put(...)   -> Untuk mengubah seluruh data (FULL UPDATE)
// - Route::delete(...)-> Untuk menghapus data (DELETE)
//
// - middleware('auth:sanctum') -> Middleware yang memastikan user sudah login
//   (memiliki token valid). Jika belum login, akan ditolak 401 Unauthorized.
//
// - prefix('xxx') -> Menambahkan awalan pada URL, misal prefix('auth')
//   membuat semua route di dalamnya menjadi /api/auth/...
//
// - group(function(){}) -> Mengelompokkan beberapa route agar bisa berbagi
//   konfigurasi yang sama (misal middleware atau prefix).
//
// - apiResource('xxx', Controller) -> Shortcut Laravel untuk membuat 5 route
//   CRUD sekaligus: index, store, show, update, destroy.
// =============================================================================

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\AiRunController;
use App\Http\Controllers\Api\ReportController;

// =============================================================================
// GRUP ROUTE: AUTENTIKASI (Login, Register, Cek Session)
// URL Dasar: /api/auth/...
// Tidak butuh login kecuali checkSession
// =============================================================================
Route::prefix('auth')->group(function (): void {
    // POST /api/auth/login -> Fungsi login user, mengembalikan token
    Route::post('/login', [AuthController::class, 'login']);

    // POST /api/auth/register -> Fungsi registrasi user baru + seed data awal
    Route::post('/register', [AuthController::class, 'register']);

    // GET /api/auth/session -> Cek apakah token masih valid (butuh login)
    Route::get('/session', [AuthController::class, 'checkSession'])->middleware('auth:sanctum');
});

// =============================================================================
// ROUTE: HEALTH CHECK
// URL: GET /api/health
// Fungsi: Endpoint sederhana untuk mengecek apakah server API berjalan normal.
//         Biasanya dipakai oleh monitoring tools atau frontend untuk ping server.
// =============================================================================
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API is healthy',
        'timestamp' => now()->toISOString(),
    ]);
});

// =============================================================================
// ROUTE: GET DATA USER YANG SEDANG LOGIN
// URL: GET /api/user
// Fungsi: Mengembalikan data user yang sedang login berdasarkan token.
//         $request->user() otomatis mengambil user dari token Sanctum.
// =============================================================================
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// =============================================================================
// ROUTE: CRUD PRODUK & KATEGORI (menggunakan apiResource)
// apiResource otomatis membuat route:
//   GET    /api/products         -> index    (ambil semua produk)
//   POST   /api/products         -> store    (buat produk baru)
//   GET    /api/products/{id}    -> show     (ambil detail 1 produk)
//   PUT    /api/products/{id}    -> update   (update produk)
//   DELETE /api/products/{id}    -> destroy  (hapus produk)
// Semua butuh login (auth:sanctum)
// =============================================================================
Route::middleware('auth:sanctum')->apiResource('products', App\Http\Controllers\Api\ProductController::class);
Route::middleware('auth:sanctum')->apiResource('categories', App\Http\Controllers\Api\CategoryController::class);

// PATCH /api/categories/{id}/status -> Update status aktif/nonaktif kategori
Route::patch('categories/{id}/status', [CategoryController::class, 'updateStatus'])->middleware('auth:sanctum');

// GET /api/categories/products -> Ambil semua kategori beserta produk di dalamnya
Route::get('categories/products', [CategoryController::class, 'getCategoriesWithProducts'])->middleware('auth:sanctum');

// =============================================================================
// GRUP ROUTE: TRANSAKSI
// URL Dasar: /api/transactions/...
// Semua butuh login (auth:sanctum)
// =============================================================================
Route::middleware('auth:sanctum')->prefix('transactions')->group(function () {
    Route::get('/', [TransactionController::class, 'index']);     // list / history
    Route::get('/{id}', [TransactionController::class, 'show']);  // detail
    Route::post('/', [TransactionController::class, 'store']);    // Simpan Transaksi Baru / Adjustment
});

// =============================================================================
// GRUP ROUTE: BILLING (Langganan & Laporan)
// URL Dasar: /api/billing/... dan /api/reports
// Semua butuh login (auth:sanctum)
// =============================================================================
Route::middleware('auth:sanctum')->group(function () {
    // POST /api/billing/subscribe -> Buat langganan baru (PRO / PRO_MAX) via Midtrans
    Route::post('/billing/subscribe', [BillingController::class, 'subscribe']);

    // GET /api/billing/active -> Cek apakah user punya langganan yang masih aktif
    Route::get('/billing/active', [BillingController::class, 'active']);

    // GET /api/reports -> Ambil ringkasan laporan penjualan (hari ini, minggu, bulan, dll)
    Route::get('/reports', [ReportController::class, 'index']);
});

// =============================================================================
// ROUTE: WEBHOOK MIDTRANS (Pembayaran)
// URL: POST /api/billing/webhook
// PENTING: Route ini TIDAK pakai middleware auth karena dipanggil oleh
//          server Midtrans secara otomatis (bukan oleh user/frontend).
//          Midtrans mengirim notifikasi status pembayaran ke URL ini.
// =============================================================================
// Webhook dipisah dari middleware auth karena dipanggil oleh server Midtrans (publik)
Route::post('/billing/webhook', [BillingController::class, 'webhook']);

// Endpoint khusus testing webhook via Postman
Route::post('/billing/webhook-test', [BillingController::class, 'webhookTest']);


// =============================================================================
// GRUP ROUTE: AI (Analisis Stok & Jam Sibuk)
// URL Dasar: /api/ai/...
// Semua butuh login (auth:sanctum)
// Fitur ini khusus user dengan langganan PRO_MAX
// =============================================================================
Route::middleware('auth:sanctum')->prefix('ai')->group(function () {
    // GET /api/ai/runs/latest/stocks -> Ambil hasil analisis stok AI terbaru
    Route::get('/runs/latest/stocks', [AiRunController::class, 'latestStocks']);

    // GET /api/ai/runs/latest/busy-hours -> Ambil hasil prediksi jam sibuk terbaru
    Route::get('/runs/latest/busy-hours', [AiRunController::class, 'latestBusyHours']);

    // POST /api/ai/runs/analyze -> Jalankan analisis AI untuk rekomendasi restock produk
    Route::post('/runs/analyze', [AiRunController::class, 'analyze']);

    // POST /api/ai/runs/analyze-busy-hours -> Jalankan analisis AI untuk prediksi jam sibuk
    Route::post('/runs/analyze-busy-hours', [AiRunController::class, 'analyzeBusyHours']);

    // PATCH /api/ai/recommendations/{id}/action -> Tandai rekomendasi AI sebagai DONE/IGNORE
    Route::patch('/recommendations/{recommendationId}/action', [AiRunController::class, 'updateAction']);
});

// =============================================================================
// GRUP ROUTE: LAPORAN DETAIL
// URL Dasar: /api/reports/...
// =============================================================================
Route::middleware('auth:sanctum')->prefix('reports')->group(function () {
    // GET /api/reports/sales-history -> Riwayat penjualan dengan pagination
    Route::get('/sales-history', [ReportController::class, 'salesHistory']);
});
