<?php

use App\Http\Controllers\Api\PharmacyAccessRequestController;
use App\Http\Controllers\Api\SaleImportController;
use App\Http\Middleware\CheckPharmacyAccess;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
  // Auth routes will be added in phase 1

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::middleware(EnsureUserIsAdmin::class)->prefix('admin')->group(function () {
            Route::post('sales/import', [SaleImportController::class, 'store']);
            Route::get('upload-batches', fn () => response()->json(['message' => 'TODO']));
            Route::get('upload-batches/{batch}', fn () => response()->json(['message' => 'TODO']));

            Route::post('pharmacy-access-requests/{pharmacyAccessRequest}/approve', [PharmacyAccessRequestController::class, 'approve']);
            Route::post('pharmacy-access-requests/{pharmacyAccessRequest}/reject', [PharmacyAccessRequestController::class, 'reject']);
        });

        Route::middleware(CheckPharmacyAccess::class)->group(function () {
            Route::get('pharmacies', fn () => response()->json(['message' => 'TODO: PharmacyController@index']));
            Route::get('sales', fn () => response()->json(['message' => 'TODO: SaleController@index']));
        });

        Route::post('pharmacy-access-requests', [PharmacyAccessRequestController::class, 'store']);
    });
});
