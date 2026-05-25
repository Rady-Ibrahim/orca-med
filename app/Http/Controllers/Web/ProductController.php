<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CompanyService;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService,
        private CompanyService $companyService,
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $products = $this->productService->list($user, $request->only(['search', 'company_id', 'per_page']));
        $companies = $user->isAdmin() 
            ? $this->companyService->list(['per_page' => 200])->getCollection()
            : collect([]);

        return view('products.index', compact('products', 'companies'));
    }

    public function create(): View
    {
        $user = auth()->user();
        $companies = $user->isAdmin()
            ? $this->companyService->list(['per_page' => 200])->getCollection()
            : collect([$user->company]);

        return view('products.create', compact('companies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'unique:products,code'],
            'price' => ['required', 'numeric', 'min:0'],
            'company_id' => ['required', 'exists:companies,id'],
        ]);

        $product = $this->productService->create($data);

        return redirect()->route('products.index')->with('status', 'تمت إضافة الصنف بنجاح.');
    }

    public function edit(Product $product): View
    {
        $user = auth()->user();
        $this->productService->assertCompanyAccess($user, $product);

        $companies = $user->isAdmin()
            ? $this->companyService->list(['per_page' => 200])->getCollection()
            : collect([$user->company]);

        return view('products.edit', compact('product', 'companies'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $user = auth()->user();
        $this->productService->assertCompanyAccess($user, $product);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'unique:products,code,' . $product->id],
            'price' => ['required', 'numeric', 'min:0'],
            'company_id' => ['required', 'exists:companies,id'],
        ]);

        $this->productService->update($product, $data);

        return redirect()->route('products.index')->with('status', 'تم تحديث الصنف بنجاح.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $user = auth()->user();
        $this->productService->assertCompanyAccess($user, $product);

        $this->productService->delete($product);

        return redirect()->route('products.index')->with('status', 'تم حذف الصنف بنجاح.');
    }
}
