<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MedicineController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    // Every signed-in role
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'password']);

    Route::get('/dashboard', DashboardController::class);
    Route::get('/settings', [SettingController::class, 'show']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/medicines', [MedicineController::class, 'index']);
    Route::get('/sales', [SaleController::class, 'index']);
    Route::get('/sales/{sale}', [SaleController::class, 'show']);
    Route::post('/sales', [SaleController::class, 'store']);

    // Inventory & reporting
    Route::middleware('role:admin,pharmacist')->group(function () {
        Route::apiResource('medicines', MedicineController::class)->except('index');
        Route::post('/medicines/{medicine}/image', [MedicineController::class, 'uploadImage']);
        Route::delete('/medicines/{medicine}/image', [MedicineController::class, 'deleteImage']);
        Route::apiResource('categories', CategoryController::class)->except('index', 'show');
        Route::get('/suppliers/compare', [SupplierController::class, 'compare']);
        Route::apiResource('suppliers', SupplierController::class)->except('show');
        Route::get('/batches', [BatchController::class, 'index']);
        Route::post('/batches', [BatchController::class, 'store']);
        Route::put('/batches/{batch}', [BatchController::class, 'update']);
        Route::get('/batches/{batch}/adjustments', [BatchController::class, 'adjustments']);
        Route::post('/sales/{sale}/void', [SaleController::class, 'void']);
        Route::get('/reports', ReportController::class);
    });

    // Administration
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class)->except('show');
        Route::put('/settings', [SettingController::class, 'update']);
    });
});
