<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PharmacyAccessRequest;
use App\Services\PharmacyAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PharmacyAccessRequestController extends Controller
{
    public function __construct(
        private readonly PharmacyAccessService $pharmacyAccessService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'request_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $accessRequest = $this->pharmacyAccessService->requestAccess(
            $request->user(),
            (int) $data['product_id'],
            $data['request_note'] ?? null,
        );

        return response()->json([
            'message' => 'تم إرسال طلب الوصول للمراجعة.',
            'data' => $accessRequest->load(['product', 'company']),
        ], 201);
    }

    public function approve(Request $request, PharmacyAccessRequest $pharmacyAccessRequest): JsonResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $approved = $this->pharmacyAccessService->approve(
            $pharmacyAccessRequest,
            $request->user(),
            $data['admin_note'] ?? null,
        );

        return response()->json([
            'message' => 'تمت الموافقة على طلب الوصول.',
            'data' => $approved,
        ]);
    }

    public function reject(Request $request, PharmacyAccessRequest $pharmacyAccessRequest): JsonResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $rejected = $this->pharmacyAccessService->reject(
            $pharmacyAccessRequest,
            $request->user(),
            $data['admin_note'] ?? null,
        );

        return response()->json([
            'message' => 'تم رفض طلب الوصول.',
            'data' => $rejected,
        ]);
    }
}
