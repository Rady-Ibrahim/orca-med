<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ProductResource::collection(
            $this->productService->list($request->user(), $request->only(['company_id', 'search', 'per_page']))
        )->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'unique:products,code'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        return (new ProductResource($this->productService->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $this->productService->assertCompanyAccess($request->user(), $product);
        $product->load('company')->loadCount('sales');

        return (new ProductResource($product))->response();
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['sometimes', 'exists:companies,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:100', 'unique:products,code,'.$product->id],
            'price' => ['sometimes', 'numeric', 'min:0'],
        ]);

        return (new ProductResource($this->productService->update($product, $data)))->response();
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->productService->delete($product);

        return response()->json(['message' => 'تم الحذف.']);
    }
}
