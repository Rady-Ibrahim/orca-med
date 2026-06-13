<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\ArabicTextShaper;
use App\Services\CompanyService;
use App\Services\ProvinceService;
use App\Services\QuantitySummaryService;
use App\Services\SupplierService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesController extends Controller
{
    public function __construct(
        private CompanyService $companyService,
        private ProvinceService $provinceService,
        private SupplierService $supplierService,
        private ArabicTextShaper $arabicShaper,
        private QuantitySummaryService $quantitySummaryService,
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
        if ($request->filled('price_from')) {
            $from = (float) $request->input('price_from');
            // Filter on line total; if no unit_price fall back to unit_price alone
            $query->where(DB::raw('(quantity * COALESCE(unit_price, 0) * (1 - COALESCE(discount, 0) / 100))'), '>=', $from);
        }
        if ($request->filled('price_to')) {
            $to = (float) $request->input('price_to');
            $query->where(DB::raw('(quantity * COALESCE(unit_price, 0) * (1 - COALESCE(discount, 0) / 100))'), '<=', $to);
        }

        return $query;
    }

    // ── Delete ALL sales matching current filters ─────────────
    public function destroyAll(Request $request): RedirectResponse
    {
        $user  = auth()->user();
        $query = $this->filteredSalesQuery($request, $user);

        // Collect company IDs before deletion
        $companyIds = (clone $query)
            ->with('uploadBatch')
            ->get()
            ->map(fn ($s) => $s->uploadBatch?->company_id)
            ->filter()
            ->unique();

        $deleted = $query->delete();

        foreach ($companyIds as $companyId) {
            $this->quantitySummaryService->rebuildForCompany($companyId);
        }

        return back()->with('status', "تم حذف {$deleted} سجل بيع بنجاح.");
    }

    // ── Delete single sale ────────────────────────────────────
    public function destroy(Sale $sale): RedirectResponse
    {
        $user = auth()->user();

        if ($user->isCompanyUser()) {
            abort_unless($sale->uploadBatch?->company_id === $user->company_id, 403);
        }

        $companyId = $sale->uploadBatch?->company_id;
        $sale->delete();

        if ($companyId) {
            $this->quantitySummaryService->rebuildForCompany($companyId);
        }

        return back()->with('status', 'تم حذف سجل البيع بنجاح.');
    }

    // ── Bulk delete selected sales ────────────────────────────
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:sales,id',
        ]);

        $user  = auth()->user();
        $ids   = $request->input('ids');

        $query = Sale::whereIn('id', $ids);

        if ($user->isCompanyUser()) {
            $query->whereHas('uploadBatch', fn ($q) => $q->where('company_id', $user->company_id));
        }

        // Collect company IDs before deletion for summary rebuild
        $companyIds = (clone $query)
            ->with('uploadBatch')
            ->get()
            ->map(fn ($s) => $s->uploadBatch?->company_id)
            ->filter()
            ->unique();

        $deleted = $query->delete();

        foreach ($companyIds as $companyId) {
            $this->quantitySummaryService->rebuildForCompany($companyId);
        }

        return back()->with('status', "تم حذف {$deleted} سجل بيع بنجاح.");
    }

    // ── Export PDF ────────────────────────────────────────────
    public function exportPdf(Request $request)
    {
        $user  = auth()->user();
        $query = $this->filteredSalesQuery($request, $user);

        $sales = (clone $query)
            ->with(['pharmacy', 'product', 'supplier', 'province'])
            ->orderByDesc('sold_at')
            ->limit(5000)
            ->get();

        $totals = [
            'quantity' => (int) (clone $query)->sum('quantity'),
            'gross'    => (float) (clone $query)->sum(DB::raw('quantity * COALESCE(unit_price, 0)')),
            'revenue'  => (float) (clone $query)->sum(DB::raw(Sale::revenueSql())),
            'count'    => (clone $query)->count(),
        ];

        return $this->arabicShaper->downloadPdfView('reports.pdf.sales-direct', [
            'sales'   => $sales,
            'totals'  => $totals,
            'filters' => $request->only(['supplier_id', 'province_id', 'date_from', 'date_to', 'search', 'price_from', 'price_to']),
        ], 'sales-' . now()->format('Y-m-d') . '.pdf');
    }

    // ── Export Excel/CSV ──────────────────────────────────────
    public function exportExcel(Request $request): StreamedResponse
    {
        $user  = auth()->user();
        $query = $this->filteredSalesQuery($request, $user);

        $sales = (clone $query)
            ->with(['pharmacy', 'product', 'supplier', 'province'])
            ->orderByDesc('sold_at')
            ->limit(50000)
            ->get();

        $totals = [
            'quantity' => (int) (clone $query)->sum('quantity'),
            'gross'    => (float) (clone $query)->sum(DB::raw('quantity * COALESCE(unit_price, 0)')),
            'revenue'  => (float) (clone $query)->sum(DB::raw(Sale::revenueSql())),
            'count'    => (clone $query)->count(),
        ];

        return response()->streamDownload(function () use ($sales, $totals) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['التاريخ', 'المنتج', 'الكود', 'الكمية', 'السعر', 'الخصم%', 'الإجمالي', 'الصيدلية', 'المورد', 'المحافظة']);

            foreach ($sales as $sale) {
                fputcsv($out, [
                    $sale->sold_at?->format('Y-m-d') ?? '-',
                    $sale->product?->name ?? '-',
                    $sale->product?->code ?? '-',
                    $sale->quantity,
                    number_format((float) ($sale->unit_price ?? 0), 2),
                    number_format((float) ($sale->discount ?? 0), 2),
                    number_format($sale->lineRevenue(), 2),
                    $sale->pharmacy?->name ?? '-',
                    $sale->supplier?->name ?? '-',
                    $sale->province?->name ?? '-',
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['الإجمالي', '', '', $totals['count'] . ' سجل / ' . number_format($totals['quantity']) . ' وحدة',
                number_format($totals['gross'], 2), '', number_format($totals['revenue'], 2), '', '', '']);
            fclose($out);
        }, 'sales-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
