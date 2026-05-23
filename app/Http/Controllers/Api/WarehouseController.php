<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly WarehouseService $warehouseService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->warehouseService->list($request->only(['search', 'per_page']))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['wholesale', 'retail'])],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'province_id' => ['nullable', 'exists:provinces,id'],
        ]);

        $warehouse = $this->warehouseService->create($data);
        $this->warehouseService->ensureShadowSupplier($warehouse);

        return response()->json(['data' => $warehouse->load('province')], 201);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        $warehouse->loadCount(['pharmacies', 'uploadBatches']);

        return response()->json(['data' => $warehouse->load('province')]);
    }

    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['wholesale', 'retail'])],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'province_id' => ['nullable', 'exists:provinces,id'],
        ]);

        return response()->json([
            'data' => $this->warehouseService->update($warehouse, $data),
        ]);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->warehouseService->delete($warehouse);

        return response()->json(['message' => 'تم الحذف.']);
    }
}
