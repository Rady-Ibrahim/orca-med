<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyAnalyticsController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\PharmacyAccessRequestController;
use App\Http\Controllers\Api\PharmacyController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProvinceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SaleImportController;
use App\Http\Controllers\Api\SensitiveUnlockController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UploadBatchController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Middleware\CheckPharmacyAccess;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::post('sensitive-unlock', [SensitiveUnlockController::class, 'store']);

        Route::prefix('company')->group(function () {
            Route::get('analytics/products', [CompanyAnalyticsController::class, 'products']);
            Route::get('analytics/products/{product}/provinces', [CompanyAnalyticsController::class, 'productProvinces']);
            Route::get('analytics/products/{product}/provinces/{province}/pharmacies', [CompanyAnalyticsController::class, 'productPharmacies']);
            Route::get('analytics/compare', [CompanyAnalyticsController::class, 'compare']);
        });

        Route::get('dashboard', [DashboardController::class, 'index']);

        Route::get('reports/sales', [ReportController::class, 'sales']);
        Route::get('reports/products', [ReportController::class, 'products']);
        Route::get('reports/sales/export', [ExportController::class, 'sales']);
        Route::get('search', [ReportController::class, 'search']);

        Route::post('pharmacy-access-requests', [PharmacyAccessRequestController::class, 'store']);

        Route::middleware('admin.or.warehouse.upload')->group(function () {
            Route::post('sales/import', [SaleImportController::class, 'store']);
            Route::get('upload-batches', [UploadBatchController::class, 'index']);
            Route::get('upload-batches/{uploadBatch}', [UploadBatchController::class, 'show']);
            Route::get('upload-batches/{uploadBatch}/errors', [UploadBatchController::class, 'downloadErrors']);
        });

        Route::middleware(CheckPharmacyAccess::class)->group(function () {
            Route::get('provinces', [ProvinceController::class, 'index']);
            Route::get('provinces/{province}', [ProvinceController::class, 'show']);
            Route::get('suppliers', [SupplierController::class, 'index']);
            Route::get('suppliers/{supplier}', [SupplierController::class, 'show']);
            Route::get('pharmacies', [PharmacyController::class, 'index']);
            Route::get('pharmacies/{pharmacy}', [PharmacyController::class, 'show']);
            Route::get('products', [ProductController::class, 'index']);
            Route::get('products/{product}', [ProductController::class, 'show']);
            Route::get('sales', [SaleController::class, 'index']);
            Route::get('sales/{sale}', [SaleController::class, 'show']);
        });

        Route::middleware(EnsureUserIsAdmin::class)->prefix('admin')->as('api.')->group(function () {
            Route::post('provinces', [ProvinceController::class, 'store']);
            Route::put('provinces/{province}', [ProvinceController::class, 'update']);
            Route::patch('provinces/{province}', [ProvinceController::class, 'update']);
            Route::delete('provinces/{province}', [ProvinceController::class, 'destroy']);

            Route::post('suppliers', [SupplierController::class, 'store']);
            Route::put('suppliers/{supplier}', [SupplierController::class, 'update']);
            Route::patch('suppliers/{supplier}', [SupplierController::class, 'update']);
            Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);

            Route::post('pharmacies', [PharmacyController::class, 'store']);
            Route::put('pharmacies/{pharmacy}', [PharmacyController::class, 'update']);
            Route::patch('pharmacies/{pharmacy}', [PharmacyController::class, 'update']);
            Route::delete('pharmacies/{pharmacy}', [PharmacyController::class, 'destroy']);

            Route::post('products', [ProductController::class, 'store']);
            Route::put('products/{product}', [ProductController::class, 'update']);
            Route::patch('products/{product}', [ProductController::class, 'update']);
            Route::delete('products/{product}', [ProductController::class, 'destroy']);

            Route::post('sales', [SaleController::class, 'store']);
            Route::put('sales/{sale}', [SaleController::class, 'update']);
            Route::patch('sales/{sale}', [SaleController::class, 'update']);
            Route::delete('sales/{sale}', [SaleController::class, 'destroy']);

            Route::apiResource('companies', CompanyController::class);
            Route::patch('companies/{company}/sensitive-view-password', [CompanyController::class, 'updateSensitiveViewPassword']);

            Route::apiResource('warehouses', WarehouseController::class);

            Route::apiResource('users', UserController::class);

            Route::get('pharmacy-access-requests', [PharmacyAccessRequestController::class, 'index']);
            Route::post('pharmacy-access-requests/{pharmacyAccessRequest}/approve', [PharmacyAccessRequestController::class, 'approve']);
            Route::post('pharmacy-access-requests/{pharmacyAccessRequest}/reject', [PharmacyAccessRequestController::class, 'reject']);
        });
    });
});
