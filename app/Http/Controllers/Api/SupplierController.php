<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\PharmacyAccessService;
use App\Services\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $supplierService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        SupplierResource::$maskCounter = 0;
        $request->attributes->set('mask_pharmacies', $this->shouldMask($request));

        return SupplierResource::collection(
            $this->supplierService->list($request->only(['province_id', 'search', 'per_page']))
        )->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'province_id' => ['required', 'exists:provinces,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]);

        return (new SupplierResource($this->supplierService->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Supplier $supplier): JsonResponse
    {
        $request->attributes->set('mask_pharmacies', $this->shouldMask($request));
        $supplier->load('province')->loadCount('pharmacies');

        return (new SupplierResource($supplier))->response();
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $data = $request->validate([
            'province_id' => ['sometimes', 'exists:provinces,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]);

        return (new SupplierResource($this->supplierService->update($supplier, $data)))->response();
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->supplierService->delete($supplier);

        return response()->json(['message' => 'تم الحذف.']);
    }

    private function shouldMask(Request $request): bool
    {
        if ($request->user()?->isAdmin()) {
            return false;
        }

        return app(PharmacyAccessService::class)
            ->shouldMaskPharmacies($request->user(), $request->integer('product_id') ?: null);
    }
}
