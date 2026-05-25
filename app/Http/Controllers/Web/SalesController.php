<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\CompanyService;
use App\Services\ProvinceService;
use App\Services\SupplierService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function __construct(
        private CompanyService $companyService,
        private ProvinceService $provinceService,
        private SupplierService $supplierService,
    ) {}

    public function index(Request $request): View
    {
        $query = Sale::with(['pharmacy', 'product', 'supplier', 'province', 'uploadBatch']);

        // Role-based filtering
        $user = auth()->user();
        if ($user->isCompanyUser()) {
            $query->whereHas('uploadBatch', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });
        }

        // Apply filters
        if ($request->filled('pharmacy_id')) {
            $query->where('pharmacy_id', $request->integer('pharmacy_id'));
        }
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }
        if ($request->filled('province_id')) {
            $query->where('province_id', $request->integer('province_id'));
        }
        if ($request->filled('date_from')) {
            $query->where('sold_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('sold_at', '<=', $request->date('date_to'));
        }

        $sales = $query->orderBy('sold_at', 'desc')->paginate(50);

        // Load filter options
        $companies = $user->isAdmin() 
            ? $this->companyService->list(['per_page' => 200])->getCollection()
            : collect([]);
        $suppliers = $this->supplierService->list(['per_page' => 200])->getCollection();
        $provinces = $this->provinceService->list(['per_page' => 200])->getCollection();

        return view('sales.index', compact('sales', 'companies', 'suppliers', 'provinces'));
    }

    public function show(Sale $sale): View
    {
        $sale->load(['pharmacy', 'product', 'supplier', 'province', 'uploadBatch.company']);

        // Privacy check for company users
        $user = auth()->user();
        if ($user->isCompanyUser()) {
            if ($sale->uploadBatch?->company_id !== $user->company_id) {
                abort(403, 'غير مصرح لك بعرض هذه البيانات');
            }
        }

        return view('sales.show', compact('sale'));
    }
}
