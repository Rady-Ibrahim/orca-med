<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProvinceResource;
use App\Models\Province;
use App\Services\ProvinceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    public function __construct(
        private readonly ProvinceService $provinceService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ProvinceResource::collection(
            $this->provinceService->list($request->only(['search', 'per_page']))
        )->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:provinces,name'],
        ]);

        $province = $this->provinceService->create($data);

        return (new ProvinceResource($province))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Province $province): JsonResponse
    {
        $province->loadCount(['suppliers', 'pharmacies']);

        return (new ProvinceResource($province))->response();
    }

    public function update(Request $request, Province $province): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:provinces,name,'.$province->id],
        ]);

        return (new ProvinceResource($this->provinceService->update($province, $data)))->response();
    }

    public function destroy(Province $province): JsonResponse
    {
        $this->provinceService->delete($province);

        return response()->json(['message' => 'تم الحذف.']);
    }
}
