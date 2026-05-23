<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Province;
use App\Services\CompanyAnalyticsService;
use App\Services\PharmacyAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyAnalyticsController extends Controller
{
    public function __construct(
        private readonly CompanyAnalyticsService $analyticsService,
        private readonly PharmacyAccessService $pharmacyAccessService,
    ) {}

    public function products(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $rows = $this->analyticsService->productsWithTotals($request->user(), $data['search'] ?? null);

        return response()->json(['data' => $rows]);
    }

    public function productProvinces(Request $request, Product $product): JsonResponse
    {
        $rows = $this->analyticsService->productBreakdownByProvince($request->user(), $product);

        return response()->json(['data' => $rows]);
    }

    public function productPharmacies(Request $request, Product $product, Province $province): JsonResponse
    {
        $rows = $this->analyticsService->productPharmaciesInProvince($request->user(), $product, $province);

        $mask = $this->pharmacyAccessService->shouldMaskPharmacies($request->user(), $product->id);
        $i = 0;
        $data = $rows->map(function ($row) use ($mask, &$i) {
            $name = $row->pharmacy_name;
            if ($mask) {
                $name = $this->pharmacyAccessService->maskPharmacyName(++$i);
            }

            return [
                'pharmacy_id' => $row->pharmacy_id,
                'pharmacy_name' => $name,
                'total_quantity' => (int) $row->total_quantity,
                'sale_count' => (int) $row->sale_count,
                'is_masked' => $mask,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function compare(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_1_from' => ['required', 'date'],
            'period_1_to' => ['required', 'date', 'after_or_equal:period_1_from'],
            'period_2_from' => ['required', 'date'],
            'period_2_to' => ['required', 'date', 'after_or_equal:period_2_from'],
        ]);

        return response()->json(
            $this->analyticsService->comparePeriods(
                $request->user(),
                $data['period_1_from'],
                $data['period_1_to'],
                $data['period_2_from'],
                $data['period_2_to'],
            )
        );
    }
}
