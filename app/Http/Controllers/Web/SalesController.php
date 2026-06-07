<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\CompanyService;
use App\Services\ProvinceService;
use App\Services\SupplierService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $user = auth()->user();

        if ($user->isCompanyUser() && ! $user->hasAnalyticsAccess()) {
            $sales = Sale::query()->whereRaw('1 = 0')->paginate(50);
            $tableTotals = ['quantity' => 0, 'gross' => 0, 'revenue' => 0, 'count' => 0];
        } else {
            $baseQuery = $this->filteredSalesQuery($request, $user);

            $tableTotals = [
                'quantity' => (int) (clone $baseQuery)->sum('quantity'),
                'gross' => (float) (clone $baseQuery)->sum(DB::raw('quantity * COALESCE(unit_price, 0)')),
                'revenue' => (float) (clone $baseQuery)->sum(DB::raw(Sale::revenueSql())),
                'count' => (clone $baseQuery)->count(),
            ];

            $sales = (clone $baseQuery)
                ->with(['pharmacy', 'product', 'supplier', 'province', 'uploadBatch'])
                ->orderByDesc('sold_at')
                ->paginate(50)
                ->withQueryString();
        }

        $companies = $user->isAdmin()
            ? $this->companyService->list(['per_page' => 200])->getCollection()
            : collect([]);
        $suppliers = $this->supplierService->list(['per_page' => 200])->getCollection();
        $provinces = $this->provinceService->list(['per_page' => 200])->getCollection();

        return view('sales.index', compact('sales', 'companies', 'suppliers', 'provinces', 'tableTotals'));
    }

    public function show(Sale $sale): View
    {
        $sale->load(['pharmacy', 'product', 'supplier', 'province', 'uploadBatch.company']);

        $user = auth()->user();
        if ($user->isCompanyUser()) {
            if ($sale->uploadBatch?->company_id !== $user->company_id) {
                abort(403, 'غير مصرح لك بعرض هذه البيانات');
            }
        }

        return view('sales.show', compact('sale'));
    }

    private function filteredSalesQuery(Request $request, $user): Builder
    {
        $query = Sale::query();

        if ($user->isCompanyUser()) {
            $query->whereHas('uploadBatch', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });
        }

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
            $query->whereDate('sold_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sold_at', '<=', $request->date('date_to'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', function ($pq) use ($search) {
                    $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                })->orWhereHas('pharmacy', function ($phq) use ($search) {
                    $phq->where('name', 'like', "%{$search}%");
                });
            });
        }

        return $query;
    }
}
