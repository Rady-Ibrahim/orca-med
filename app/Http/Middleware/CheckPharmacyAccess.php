<?php

namespace App\Http\Middleware;

use App\Services\PharmacyAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets request attributes used by API Resources to mask sensitive pharmacy/supplier names
 * until admin approves pharmacy_access_requests for company + product.
 */
class CheckPharmacyAccess
{
    public function __construct(
        private readonly PharmacyAccessService $pharmacyAccessService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $productId = $request->route('product')
            ?? $request->route('product_id')
            ?? ($request->filled('product_id') ? (int) $request->query('product_id') : null);

        $maskPharmacies = $this->pharmacyAccessService->shouldMaskPharmacies($user, $productId);

        $request->attributes->set('mask_pharmacies', $maskPharmacies);
        $request->attributes->set('mask_product_id', $productId);

        return $next($request);
    }
}
