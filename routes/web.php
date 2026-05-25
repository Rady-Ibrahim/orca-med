<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Route;

// ── Guest routes ──────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// ── Auth routes ───────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard (all roles — controller picks view by role)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Admin-only ────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::resource('provinces',       \App\Http\Controllers\Web\ProvinceController::class)->only(['index','create','store','edit','update','destroy']);
        Route::resource('suppliers',       \App\Http\Controllers\Web\SupplierController::class)->only(['index','create','store','edit','update','destroy']);
        Route::resource('pharmacies',      \App\Http\Controllers\Web\PharmacyController::class)->only(['index','create','store','edit','update','destroy','show']);
        Route::resource('users',           \App\Http\Controllers\Web\UserController::class)->only(['index','create','store','edit','update','destroy']);
        Route::resource('companies',       \App\Http\Controllers\Web\CompanyController::class)->only(['index','create','store','edit','update','destroy']);
        Route::resource('access-requests', \App\Http\Controllers\Web\PharmacyAccessRequestController::class)->only(['index','update']);
    });

    // ── Admin-only (Imports) ───────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('/imports',              [\App\Http\Controllers\Web\ImportController::class, 'index'])->name('imports.index');
        Route::post('/imports',             [\App\Http\Controllers\Web\ImportController::class, 'store'])->name('imports.store');
        Route::get('/imports/template',     [\App\Http\Controllers\Web\ImportController::class, 'template'])->name('imports.template');
        Route::get('/imports/{batch}',      [\App\Http\Controllers\Web\ImportController::class, 'show'])->name('imports.show');
        Route::get('/imports/{batch}/errors', [\App\Http\Controllers\Web\ImportController::class, 'downloadErrors'])->name('imports.download-errors');
        Route::get('/activation-codes',     [\App\Http\Controllers\Web\ActivationCodeController::class, 'index'])->name('activation-codes.index');
        Route::post('/activation-codes',    [\App\Http\Controllers\Web\ActivationCodeController::class, 'store'])->name('activation-codes.store');
        Route::delete('/activation-codes/{code}', [\App\Http\Controllers\Web\ActivationCodeController::class, 'destroy'])->name('activation-codes.destroy');
    });

    // ── Admin + Company ───────────────────────────────────────
    Route::middleware('role:admin,company')->group(function () {
        Route::resource('products', \App\Http\Controllers\Web\ProductController::class)->only(['index','create','store','edit','update','destroy']);
        Route::resource('sales',    \App\Http\Controllers\Web\SalesController::class)->only(['index','show']);
        Route::get('/reports',      [\App\Http\Controllers\Web\ReportController::class,  'index'])->name('reports.index');
        Route::get('/reports/sales/export', [\App\Http\Controllers\Web\ReportController::class, 'exportSales'])->name('reports.export-sales');
        Route::get('/search',       [\App\Http\Controllers\Web\SearchController::class,  'index'])->name('search.index');
        Route::get('/activation',  [\App\Http\Controllers\Web\ActivationController::class, 'index'])->name('activation.index');
        Route::post('/activation', [\App\Http\Controllers\Web\ActivationController::class, 'activate'])->name('activation.activate');
    });

    // ── Company-only ──────────────────────────────────────────
    Route::middleware('role:company')->group(function () {
        Route::get('/analytics/products',                    [\App\Http\Controllers\Web\CompanyAnalyticsController::class, 'products'])->name('analytics.products');
        Route::get('/analytics/products/{product}/provinces',[\App\Http\Controllers\Web\CompanyAnalyticsController::class, 'provinces'])->name('analytics.provinces');
        Route::get('/analytics/products/{product}/pharmacies',[\App\Http\Controllers\Web\CompanyAnalyticsController::class, 'pharmacies'])->name('analytics.pharmacies');
        Route::post('/sensitive-unlock',                     [\App\Http\Controllers\Web\CompanyAnalyticsController::class, 'sensitiveUnlock'])->name('sensitive-unlock');
        Route::post('/pharmacy-access-requests',             [\App\Http\Controllers\Web\PharmacyAccessRequestController::class, 'store'])->name('pharmacy-access-requests.store');
    });

});
