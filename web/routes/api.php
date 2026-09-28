<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\StockController;
use App\Http\Controllers\API\OrderController;

// ----------------------------------------------------------------
// Public routes — no auth needed
// ----------------------------------------------------------------
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ----------------------------------------------------------------
// Protected routes — need Bearer token
// ----------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Transactions
    Route::apiResource('transactions', TransactionController::class);
    Route::get('/transactions-summary', [TransactionController::class, 'summary']);

    // Products
    Route::apiResource('products', ProductController::class);

    // Stock
    Route::apiResource('stock', StockController::class);
    Route::post('/stock/{stock}/adjust', [StockController::class, 'adjust']);

    // Orders
    Route::apiResource('orders', OrderController::class);
});