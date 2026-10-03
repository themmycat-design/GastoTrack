<?php

use App\Http\Controllers\API\AnalyticsController;
use App\Http\Controllers\API\AiAssistantController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BusinessController;
use App\Http\Controllers\API\GoalController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\StaffController;
use App\Http\Controllers\API\StockController;
use App\Http\Controllers\API\SyncController;
use App\Http\Controllers\API\TransactionController;
use Illuminate\Support\Facades\Route;

$registerPublicRoutes = function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/product-images/{product}', [ProductController::class, 'image'])
        ->whereNumber('product');
};

$registerProtectedRoutes = function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/auth/me', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    Route::get('/business', [BusinessController::class, 'show']);
    Route::put('/business', [BusinessController::class, 'update']);

    Route::get('/transactions-summary', [TransactionController::class, 'summary']);
    Route::get('/transactions/summary', [TransactionController::class, 'summary']);
    Route::post('/transactions/batch', [TransactionController::class, 'batch']);
    Route::apiResource('transactions', TransactionController::class);
    Route::apiResource('products', ProductController::class);
    Route::get('/stock/{stock}/movements', [StockController::class, 'movements']);
    Route::post('/stock/{stock}/adjust', [StockController::class, 'adjust']);
    Route::apiResource('stock', StockController::class);
    Route::apiResource('orders', OrderController::class);

    Route::post('/goals/{goal}/complete', [GoalController::class, 'complete']);
    Route::apiResource('goals', GoalController::class);
    Route::get('/staff/{staff}/activity', [StaffController::class, 'activity']);
    Route::apiResource('staff', StaffController::class)->only(['index', 'show', 'store', 'destroy']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::prefix('analytics')->group(function (): void {
        Route::get('/summary', [AnalyticsController::class, 'summary']);
        Route::get('/category-breakdown', [AnalyticsController::class, 'categories']);
        Route::get('/top-products', [AnalyticsController::class, 'topProducts']);
        Route::get('/stock-value', [AnalyticsController::class, 'stockValue']);
        Route::get('/trends', [AnalyticsController::class, 'trends']);
    });
    Route::post('/sync', [SyncController::class, 'sync'])->middleware('throttle:10,1');
    Route::post('/ai/chat', [AiAssistantController::class, 'chat'])->middleware('throttle:20,1');
};

$registerPublicRoutes();
Route::middleware(['auth:sanctum', 'business.active', 'throttle:300,1'])
    ->name('api.')
    ->group($registerProtectedRoutes);

Route::prefix('v1')->name('v1.')->group(function () use ($registerPublicRoutes, $registerProtectedRoutes): void {
    $registerPublicRoutes();
    Route::middleware(['auth:sanctum', 'business.active', 'throttle:300,1'])
        ->name('api.')
        ->group($registerProtectedRoutes);
});
