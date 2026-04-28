<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\AiRunController;

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/session', [AuthController::class, 'checkSession'])->middleware('auth:sanctum');
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API is healthy',
        'timestamp' => now()->toISOString(),
    ]);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->apiResource('products', App\Http\Controllers\Api\ProductController::class);
Route::middleware('auth:sanctum')->apiResource('categories', App\Http\Controllers\Api\CategoryController::class);
Route::patch('categories/{id}/status', [CategoryController::class, 'updateStatus']);
Route::patch('categories/products', [CategoryController::class, 'getCategoriesWithProducts']);

Route::middleware('auth:sanctum')->prefix('transactions')->group(function () {
    Route::get('/', [TransactionController::class, 'index']);     // list / history
    Route::get('/{id}', [TransactionController::class, 'show']);  // detail
    Route::post('/', [TransactionController::class, 'store']);    // Simpan Transaksi Baru / Adjustment
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/billing/subscribe', [BillingController::class, 'subscribe']);
    Route::get('/billing/active', [BillingController::class, 'active']);
});

Route::post('/billing/webhook', [BillingController::class, 'webhook']);

Route::middleware('auth:sanctum')->prefix('ai')->group(function () {
    Route::get('/runs/latest', [AiRunController::class, 'latest']);
    Route::patch('/recommendations/{recommendationId}/action', [AiRunController::class, 'updateAction']);
});