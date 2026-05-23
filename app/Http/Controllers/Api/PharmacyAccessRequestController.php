<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PharmacyAccessRequestResource;
use App\Models\PharmacyAccessRequest;
use App\Models\Product;
use App\Services\PharmacyAccessService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PharmacyAccessRequestController extends Controller
{
    public function __construct(
        private readonly PharmacyAccessService $pharmacyAccessService,
        private readonly ProductService $productService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,approved,rejected'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = PharmacyAccessRequest::query()
            ->with(['company', 'product', 'requester'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('requested_at');

        return PharmacyAccessRequestResource::collection(
            $query->paginate($filters['per_page'] ?? 15)
        )->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'request_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $this->productService->assertCompanyAccess($request->user(), $product);

        $accessRequest = $this->pharmacyAccessService->requestAccess(
            $request->user(),
            (int) $data['product_id'],
            $data['request_note'] ?? null,
        );

        return response()->json([
            'message' => 'تم إرسال طلب الوصول للمراجعة.',
            'data' => new PharmacyAccessRequestResource($accessRequest->load(['product', 'company'])),
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
            'data' => new PharmacyAccessRequestResource($approved->load(['product', 'company'])),
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
            'data' => new PharmacyAccessRequestResource($rejected->load(['product', 'company'])),
        ]);
    }
}
