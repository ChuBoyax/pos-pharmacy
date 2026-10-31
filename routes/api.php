<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MedicineController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

/*
| Access is permission-based (see App\Models\Role::PERMISSIONS). The super admin passes
| every check; everyone else gets only what their role was granted.
*/
Route::middleware('auth:sanctum')->group(function () {
    // Every signed-in user
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'password']);

    Route::get('/dashboard', DashboardController::class);
    Route::get('/settings', [SettingController::class, 'show']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/medicines', [MedicineController::class, 'index']);
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::get('/sales', [SaleController::class, 'index']);
    Route::get('/sales/{sale}', [SaleController::class, 'show']);

    // Sales
    Route::post('/sales', [SaleController::class, 'store'])->middleware('can:pos.sell');
    Route::post('/sales/{sale}/void', [SaleController::class, 'void'])->middleware('can:sales.void');

    // Inventory
    Route::middleware('can:medicines.manage')->group(function () {
        Route::apiResource('medicines', MedicineController::class)->except('index');
        Route::post('/medicines/{medicine}/image', [MedicineController::class, 'uploadImage']);
        Route::delete('/medicines/{medicine}/image', [MedicineController::class, 'deleteImage']);
        Route::apiResource('categories', CategoryController::class)->except('index', 'show');
    });

    Route::middleware('can:stock.manage')->group(function () {
        Route::get('/batches', [BatchController::class, 'index']);
        Route::post('/batches', [BatchController::class, 'store']);
        Route::put('/batches/{batch}', [BatchController::class, 'update']);
        Route::get('/batches/{batch}/adjustments', [BatchController::class, 'adjustments']);
    });

    Route::middleware('can:suppliers.manage')->group(function () {
        Route::get('/suppliers/compare', [SupplierController::class, 'compare']);
        Route::apiResource('suppliers', SupplierController::class)->except('index', 'show');
    });

    // Management
    Route::get('/reports', ReportController::class)->middleware('can:reports.view');
    Route::put('/settings', [SettingController::class, 'update'])->middleware('can:settings.manage');

    Route::middleware('can:users.manage')->group(function () {
        Route::apiResource('users', UserController::class)->except('show');
        Route::get('/roles', [RoleController::class, 'index']);
    });

    // Super admin only
    Route::middleware('can:roles.manage')->group(function () {
        Route::apiResource('roles', RoleController::class)->except('index', 'show');
    });
});
