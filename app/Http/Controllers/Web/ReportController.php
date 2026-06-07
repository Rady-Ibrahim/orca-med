<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ArabicTextShaper;
use App\Services\DashboardService;
use App\Services\CompanyService;
use App\Services\ProvinceService;
use App\Services\SupplierService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private DashboardService $dashboard,
        private CompanyService $companyService,
        private ProvinceService $provinceService,
        private SupplierService $supplierService,
        private ReportService $reportService,
        private ArabicTextShaper $arabicShaper,
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
            'company_id' => $companyId,
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

        $totalRevenue = $sales->sum(fn ($sale) => $sale->lineRevenue());

        $totalDiscount = $sales->sum(function ($sale) {
            $gross = (float) $sale->quantity * (float) ($sale->unit_price ?? 0);

            return $gross * ((float) ($sale->discount ?? 0) / 100);
        });

        $avgDiscount = $sales->isNotEmpty() 
            ? $sales->avg('discount') 
            : 0;

        $topPharmacy = $sales->groupBy('pharmacy_id')
            ->map(fn ($group) => [
                'name' => $group->first()->pharmacy?->name ?? 'غير معروف',
                'revenue' => $group->sum(fn ($s) => $s->lineRevenue()),
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
                    'revenue' => $group->sum(fn ($s) => $s->lineRevenue()),
                    'quantity' => $group->sum('quantity'),
                ])
                ->sortByDesc('revenue')
                ->take(10)
                ->values(),
        ];
    }

    public function exportSales(Request $request)
    {
        $user = auth()->user();
        $from = $request->input('from');
        $to = $request->input('to');
        $supplierId = $request->input('supplier_id');

        $filters = [
            'from' => $from,
            'to' => $to,
            'supplier_id' => $supplierId,
            'per_page' => 10000,
        ];

        $sales = $this->reportService->salesReport($user, $filters);

        return $this->arabicShaper->downloadPdfView('reports.pdf.sales', [
            'sales' => $sales,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'supplier_id' => $supplierId,
            ],
        ], 'sales-report-'.now()->format('Y-m-d').'.pdf');
    }

    public function exportProductsReport(Request $request)
    {
        $user = auth()->user();
        $from = $request->input('from');
        $to = $request->input('to');
        $companyId = $request->input('company_id');
        $supplierId = $request->input('supplier_id');

        $productsReport = $this->reportService->productsReport($user, [
            'from' => $from,
            'to' => $to,
            'company_id' => $companyId,
            'supplier_id' => $supplierId,
            'limit' => null,
        ]);

        $stats = $this->dashboard->getStats($user, [
            'from' => $from,
            'to' => $to,
            'supplier_id' => $supplierId,
        ]);

        return $this->arabicShaper->downloadPdfView('reports.pdf.products', [
            'top_products' => $productsReport['top'],
            'bottom_products' => $productsReport['bottom'],
            'by_company' => $productsReport['by_company'],
            'totals' => $stats['totals'],
            'filters' => [
                'from' => $from,
                'to' => $to,
                'company_id' => $companyId,
                'supplier_id' => $supplierId,
            ],
        ], 'products-report-'.now()->format('Y-m-d-H-i-s').'.pdf');
    }

    public function exportSalesExcel(Request $request): StreamedResponse
    {
        $user = auth()->user();
        $from = $request->input('from');
        $to = $request->input('to');
        $supplierId = $request->input('supplier_id');

        $filters = [
            'from' => $from,
            'to' => $to,
            'supplier_id' => $supplierId,
            'per_page' => 10000,
        ];

        $sales = $this->reportService->salesReport($user, $filters);

        return response()->streamDownload(function () use ($sales) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['التاريخ', 'الصنف', 'الكود', 'الكمية', 'السعر', 'الخصم', 'الإجمالي', 'المحافظة', 'الصيدلية']);

            foreach ($sales as $sale) {
                $total = $sale->quantity * $sale->unit_price * (1 - $sale->discount / 100);
                fputcsv($out, [
                    $sale->sold_at?->format('Y-m-d') ?? '-',
                    $sale->product?->name ?? '-',
                    $sale->product?->code ?? '-',
                    $sale->quantity,
                    $sale->unit_price,
                    $sale->discount . '%',
                    round($total, 2),
                    $sale->province?->name ?? '-',
                    $sale->pharmacy?->name ?? '-',
                ]);
            }

            fclose($out);
        }, 'sales-report-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportProductsExcel(Request $request): StreamedResponse
    {
        $user = auth()->user();
        $from = $request->input('from');
        $to = $request->input('to');
        $companyId = $request->input('company_id');
        $supplierId = $request->input('supplier_id');

        $productsReport = $this->reportService->productsReport($user, [
            'from' => $from,
            'to' => $to,
            'company_id' => $companyId,
            'supplier_id' => $supplierId,
            'limit' => null,
        ]);

        return response()->streamDownload(function () use ($productsReport) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, ['القسم', 'الصنف', 'الكود', 'الكمية', 'عدد المبيعات', 'النسبة المئوية']);
            fputcsv($out, ['أعلى المنتجات مبيعاً']);
            foreach ($productsReport['top'] as $product) {
                fputcsv($out, [
                    'أعلى المنتجات',
                    $product->name,
                    $product->code ?? '-',
                    $product->total_quantity,
                    $product->sales_count,
                    $product->percentage . '%',
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['أقل المنتجات مبيعاً']);
            foreach ($productsReport['bottom'] as $product) {
                fputcsv($out, [
                    'أقل المنتجات',
                    $product->name,
                    $product->code ?? '-',
                    $product->total_quantity,
                    $product->sales_count,
                    $product->percentage . '%',
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['المنتجات حسب الشركة']);
            fputcsv($out, ['الشركة', 'عدد المنتجات', 'إجمالي المبيعات']);
            foreach ($productsReport['by_company'] as $company) {
                fputcsv($out, [
                    $company->company_name,
                    $company->products_count,
                    $company->total_sold,
                ]);
            }

            fclose($out);
        }, 'products-report-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
