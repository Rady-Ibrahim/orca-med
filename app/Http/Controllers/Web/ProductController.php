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

        if ($user->isCompanyUser() && ! $user->hasAnalyticsAccess()) {
            $perPage = $request->input('per_page', 15);
            $products = Product::query()->whereRaw('1 = 0')->paginate($perPage);
            $companies = collect([]);
        } else {
            $products = $this->productService->list($user, $request->only(['search', 'company_id', 'per_page']));
            $companies = $user->isAdmin() 
                ? $this->companyService->list(['per_page' => 200])->getCollection()
                : collect([]);
        }

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

    /**
     * Simple LIKE search for the merge modal — no similarity threshold.
     */
    public function mergeSearch(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'query'      => 'required|string|min:1',
            'company_id' => 'required|integer|exists:companies,id',
            'exclude_id' => 'nullable|integer',
        ]);

        $query     = $request->input('query');
        $companyId = (int) $request->input('company_id');
        $excludeId = $request->input('exclude_id');

        $products = Product::where('company_id', $companyId)
            ->where('name', 'like', '%' . $query . '%')
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'price']);

        return response()->json([
            'results' => $products->map(fn ($p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'price' => number_format((float) $p->price, 2),
            ])->values(),
        ]);
    }

    /**
     * Merge $product (duplicate) into the canonical product chosen by the user.
     * All sales of $product are re-pointed to the canonical, an alias is saved,
     * then the duplicate is deleted.
     */
    public function merge(Request $request, Product $product): RedirectResponse
    {
        $user = auth()->user();
        $this->productService->assertCompanyAccess($user, $product);

        $data = $request->validate([
            'canonical_product_id' => ['required', 'integer', 'exists:products,id', 'different:product'],
        ]);

        $canonical = Product::findOrFail($data['canonical_product_id']);

        // Must be same company
        if ($canonical->company_id !== $product->company_id) {
            return back()->with('error', 'لا يمكن الدمج بين منتجات شركات مختلفة.');
        }

        if ($canonical->id === $product->id) {
            return back()->with('error', 'لا يمكن دمج المنتج مع نفسه.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($product, $canonical) {
            // Re-point all sales
            \App\Models\Sale::where('product_id', $product->id)
                ->update(['product_id' => $canonical->id]);

            // Save alias so future imports resolve correctly
            \App\Models\ProductAlias::updateOrCreate(
                ['alias_name' => $product->name],
                ['product_id' => $canonical->id]
            );

            // Remove any alias pointing to the duplicate to avoid FK conflicts
            \App\Models\ProductAlias::where('product_id', $product->id)->delete();

            $product->delete();
        });

        return redirect()->route('products.index')
            ->with('status', "تم دمج \"{$product->name}\" في \"{$canonical->name}\" بنجاح.");
    }
}
