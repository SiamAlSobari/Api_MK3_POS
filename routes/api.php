<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

