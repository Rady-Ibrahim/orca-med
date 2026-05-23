<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PharmacyResource;
use App\Models\Pharmacy;
use App\Services\PharmacyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PharmacyController extends Controller
{
    public function __construct(
        private readonly PharmacyService $pharmacyService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        PharmacyResource::$maskCounter = 0;

        return PharmacyResource::collection(
            $this->pharmacyService->list($request->only(['province_id', 'supplier_id', 'search', 'per_page']))
        )->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'province_id' => ['nullable', 'exists:provinces,id'],
            'name' => ['required', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:100', 'unique:pharmacies,license_number'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]);

        return (new PharmacyResource($this->pharmacyService->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Pharmacy $pharmacy): JsonResponse
    {
        $pharmacy->load(['supplier', 'province']);

        return (new PharmacyResource($pharmacy))->response();
    }

    public function update(Request $request, Pharmacy $pharmacy): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['sometimes', 'exists:suppliers,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'province_id' => ['sometimes', 'exists:provinces,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:100', 'unique:pharmacies,license_number,'.$pharmacy->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]);

        return (new PharmacyResource($this->pharmacyService->update($pharmacy, $data)))->response();
    }

    public function destroy(Pharmacy $pharmacy): JsonResponse
    {
        $this->pharmacyService->delete($pharmacy);

        return response()->json(['message' => 'تم الحذف.']);
    }
}
