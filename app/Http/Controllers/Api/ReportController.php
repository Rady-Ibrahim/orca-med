<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PharmacyResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\SaleResource;
use App\Http\Resources\SupplierResource;
use App\Services\PharmacyAccessService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function sales(Request $request): JsonResponse
    {
        $filters = $this->filterRules($request);

        return SaleResource::collection(
            $this->reportService->salesReport($request->user(), $filters)
        )->response();
    }

    public function products(Request $request): JsonResponse
    {
        $filters = $this->filterRules($request);

        return response()->json(
            $this->reportService->productsReport($request->user(), $filters)
        );
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1'],
            'province_id' => ['nullable', 'exists:provinces,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'product_id' => ['nullable', 'exists:products,id'],
        ]);

        $result = $this->reportService->search(
            $request->user(),
            $data['q'],
            $data
        );

        PharmacyResource::$maskCounter = 0;
        SupplierResource::$maskCounter = 0;

        $request->attributes->set(
            'mask_pharmacies',
            app(PharmacyAccessService::class)
                ->shouldMaskPharmacies($request->user(), $data['product_id'] ?? null)
        );

        return response()->json([
            'products' => ProductResource::collection($result['products']),
            'pharmacies' => PharmacyResource::collection($result['pharmacies']),
            'suppliers' => SupplierResource::collection($result['suppliers']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filterRules(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'province_id' => ['nullable', 'exists:provinces,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'pharmacy_id' => ['nullable', 'exists:pharmacies,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'search' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }
}
