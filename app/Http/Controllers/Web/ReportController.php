<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\CompanyService;
use App\Services\ProvinceService;
use App\Services\SupplierService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private DashboardService $dashboard,
        private CompanyService $companyService,
        private ProvinceService $provinceService,
        private SupplierService $supplierService,
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $from = $request->input('from');
        $to = $request->input('to');
        $companyId = $request->input('company_id');
        $supplierId = $request->input('supplier_id');
        
        $hasAnalyticsAccess = $user->hasAnalyticsAccess() || $user->isAdmin();

        $stats = $this->dashboard->getStats($user, [
            'from' => $from,
            'to' => $to,
            'supplier_id' => $supplierId,
        ]);

        // Load filter options for admin
        $companies = $user->isAdmin()
            ? $this->companyService->list(['per_page' => 200])->getCollection()
            : collect([]);
        $provinces = $this->provinceService->list(['per_page' => 200])->getCollection();
        $suppliers = $this->supplierService->list(['per_page' => 200])->getCollection();

        // Additional KPIs for reports (admin always sees, company only if has access)
        $additionalKPIs = ($user->isAdmin() || $hasAnalyticsAccess) 
            ? $this->getAdditionalKPIs($user, $from, $to, $companyId, $supplierId) 
            : null;

        return view('reports.index', [
            'totals' => $stats['totals'],
            'charts' => $stats['charts'],
            'additional_kpis' => $additionalKPIs,
            'companies' => $companies,
            'provinces' => $provinces,
            'suppliers' => $suppliers,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'company_id' => $companyId,
                'supplier_id' => $supplierId,
            ],
            'has_analytics_access' => $hasAnalyticsAccess,
        ]);
    }

    private function getAdditionalKPIs($user, ?string $from, ?string $to, ?int $companyId = null, ?int $supplierId = null): array
    {
        $query = \App\Models\Sale::query();
        
        // Apply scope based on user role
        if ($user->isCompanyUser()) {
            $query->whereHas('uploadBatch', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });
        }

        // Admin can filter by company
        if ($user->isAdmin() && $companyId) {
            $query->whereHas('uploadBatch', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($from) {
            $query->whereDate('sold_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('sold_at', '<=', $to);
        }

        $sales = $query->get();

        $totalRevenue = $sales->sum(function ($sale) {
            return $sale->quantity * $sale->unit_price * (1 - $sale->discount / 100);
        });

        $totalDiscount = $sales->sum(function ($sale) {
            return $sale->quantity * $sale->unit_price * ($sale->discount / 100);
        });

        $avgDiscount = $sales->isNotEmpty() 
            ? $sales->avg('discount') 
            : 0;

        $topPharmacy = $sales->groupBy('pharmacy_id')
            ->map(fn ($group) => [
                'name' => $group->first()->pharmacy?->name ?? 'غير معروف',
                'revenue' => $group->sum(fn ($s) => $s->quantity * $s->unit_price * (1 - $s->discount / 100)),
                'count' => $group->count(),
            ])
            ->sortByDesc('revenue')
            ->first();

        return [
            'total_discount' => round($totalDiscount, 2),
            'avg_discount_percent' => round($avgDiscount, 2),
            'top_pharmacy' => $topPharmacy,
            'revenue_per_product' => $sales->groupBy('product_id')
                ->map(fn ($group) => [
                    'name' => $group->first()->product?->name ?? 'غير معروف',
                    'revenue' => $group->sum(fn ($s) => $s->quantity * $s->unit_price * (1 - $s->discount / 100)),
                    'quantity' => $group->sum('quantity'),
                ])
                ->sortByDesc('revenue')
                ->take(10)
                ->values(),
        ];
    }

    public function exportSales() { abort(501, 'قريباً'); }
}
