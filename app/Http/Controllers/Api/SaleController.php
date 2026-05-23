<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PharmacyResource;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        PharmacyResource::$maskCounter = 0;

        $filters = $request->validate([
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

        return SaleResource::collection(
            $this->saleService->list($request->user(), $filters)
        )->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'pharmacy_id' => ['required', 'exists:pharmacies,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'sold_at' => ['required', 'date'],
        ]);

        return (new SaleResource($this->saleService->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Sale $sale): JsonResponse
    {
        $sale->load(['product.company', 'pharmacy.supplier', 'province']);

        return (new SaleResource($sale))->response();
    }

    public function update(Request $request, Sale $sale): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['sometimes', 'exists:products,id'],
            'pharmacy_id' => ['sometimes', 'exists:pharmacies,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'sold_at' => ['sometimes', 'date'],
        ]);

        return (new SaleResource($this->saleService->update($sale, $data)))->response();
    }

    public function destroy(Sale $sale): JsonResponse
    {
        $this->saleService->delete($sale);

        return response()->json(['message' => 'تم الحذف.']);
    }
}
