<?php

use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\AdminUserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/profile', [AuthController::class, 'updateProfile']);

    Route::apiResource('addresses', AddressController::class);

    Route::apiResource('orders', OrderController::class)->only(['index', 'show', 'store']);
    Route::patch('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{id}/pay', [OrderController::class, 'pay']);
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus'])
        ->middleware('admin');

    Route::post('/cart/merge', [CartController::class, 'merge']);

    Route::get('/admin/users', [AdminUserController::class, 'index'])
        ->middleware('admin');
    Route::get('/admin/users/{id}/orders', [AdminUserController::class, 'orders'])
        ->middleware('admin');
});

Route::prefix('v1')->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::apiResource('categories', CategoryController::class);

    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/', [CartController::class, 'store']);
        Route::patch('/{productId}', [CartController::class, 'update']);
        Route::delete('/{productId}', [CartController::class, 'destroy']);
    });

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});