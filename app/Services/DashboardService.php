<?php

namespace App\Services;

use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Province;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Concerns\AppliesCompanyScope;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    use AppliesCompanyScope;

    private QuantitySummaryService $quantitySummary;

    public function __construct(QuantitySummaryService $quantitySummary)
    {
        $this->quantitySummary = $quantitySummary;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(User $user, array $filters = []): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $supplierId = $filters['supplier_id'] ?? null;
        $companyId = $filters['company_id'] ?? null;
        $productLimit = $filters['product_limit'] ?? null;
        $hasAnalyticsAccess = $user->hasAnalyticsAccess();

        $salesQuery = Sale::query();
        $this->scopeSales($salesQuery, $user);

        if ($from) {
            $salesQuery->whereDate('sold_at', '>=', $from);
        }
        if ($to) {
            $salesQuery->whereDate('sold_at', '<=', $to);
        }

        $this->applySupplierFilter($salesQuery, $supplierId);

        // Admin can filter by company
        if ($user->isAdmin() && $companyId) {
            $salesQuery->whereHas('uploadBatch', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        $productsQuery = Product::query();
        $this->scopeProducts($productsQuery, $user);
        if ($supplierId) {
            $productsQuery->whereHas('sales', fn ($q) => $q->where('supplier_id', $supplierId));
        }

        // Admin can filter products by company
        if ($user->isAdmin() && $companyId) {
            $productsQuery->where('company_id', $companyId);
        }

        // Calculate total revenue using the financial formula
        $totalRevenue = $this->calculateTotalRevenue($salesQuery);

        if ($user->isWarehouseUser() && $user->warehouse_id) {
            $wid = $user->warehouse_id;

            $productIds = Sale::query()->where('warehouse_id', $wid)->distinct()->pluck('product_id');

            return [
                'totals' => [
                    'warehouses' => 1,
                    'pharmacies' => Pharmacy::where('warehouse_id', $wid)->count(),
                    'products' => Product::whereIn('id', $productIds)->count(),
                    'sales_count' => (clone $salesQuery)->count(),
                    'quantity_sold' => (int) (clone $salesQuery)->sum('quantity'),
                    'total_revenue' => $hasAnalyticsAccess ? round($totalRevenue, 2) : null,
                    'provinces' => (clone $salesQuery)->distinct('province_id')->count('province_id'),
                    'suppliers' => (clone $salesQuery)->distinct('supplier_id')->count('supplier_id'),
                ],
                'charts' => $hasAnalyticsAccess ? [
                    'sales_by_province' => $this->salesByProvince($user, $from, $to, $filters),
                    'top_suppliers' => $this->topSuppliers($user, $from, $to, 10, $filters),
                    'top_products' => $this->topProducts($user, $from, $to, $productLimit, $filters),
                    'bottom_products' => $this->bottomProducts($user, $from, $to, $productLimit, $filters),
                    'sales_over_time' => $this->salesOverTime($user, $from, $to, $filters),
                    'products_by_company' => $this->productsByCompanyForWarehouse($wid),
                ] : [],
            ];
        }

        // For company users: return general stats without analytics access, full stats with access
        if ($user->isCompanyUser()) {
            return [
                'totals' => [
                    'provinces' => (clone $salesQuery)->distinct('province_id')->count('province_id'),
                    'suppliers' => (clone $salesQuery)->distinct('supplier_id')->count('supplier_id'),
                    'pharmacies' => (clone $salesQuery)->distinct('pharmacy_id')->count('pharmacy_id'),
                    'products' => (clone $productsQuery)->count(),
                    'sales_count' => (clone $salesQuery)->count(),
                    'quantity_sold' => (int) (clone $salesQuery)->sum('quantity'),
                    'total_revenue' => $hasAnalyticsAccess ? round($totalRevenue, 2) : null,
                ],
                'charts' => $hasAnalyticsAccess ? [
                    'sales_by_province' => $this->salesByProvince($user, $from, $to, $filters),
                    'top_suppliers' => $this->topSuppliers($user, $from, $to, 10, $filters),
                    'top_products' => $this->topProducts($user, $from, $to, $productLimit, $filters),
                    'bottom_products' => $this->bottomProducts($user, $from, $to, $productLimit, $filters),
                    'sales_over_time' => $this->salesOverTime($user, $from, $to, $filters),
                    'products_by_company' => $this->productsByCompany($user),
                ] : [],
            ];
        }

        // Admin: always sees full stats + stats by company
        // If filters are applied, use filtered counts; otherwise use total counts
        $hasFilters = $from || $to || $supplierId || $companyId;
        return [
            'totals' => [
                'provinces' => $hasFilters
                    ? (clone $salesQuery)->distinct('province_id')->count('province_id')
                    : Province::count(),
                'suppliers' => $hasFilters
                    ? (clone $salesQuery)->distinct('supplier_id')->count('supplier_id')
                    : Supplier::count(),
                'pharmacies' => $hasFilters
                    ? (clone $salesQuery)->distinct('pharmacy_id')->count('pharmacy_id')
                    : Pharmacy::count(),
                'products' => (clone $productsQuery)->count(),
                'sales_count' => (clone $salesQuery)->count(),
                'quantity_sold' => (int) (clone $salesQuery)->sum('quantity'),
                'total_revenue' => round($totalRevenue, 2),
            ],
            'charts' => [
                'sales_by_province' => $this->salesByProvince($user, $from, $to, $filters),
                'top_suppliers' => $this->topSuppliers($user, $from, $to, 10, $filters),
                'top_products' => $this->topProducts($user, $from, $to, $productLimit, $filters),
                'bottom_products' => $this->bottomProducts($user, $from, $to, $productLimit, $filters),
                'sales_over_time' => $this->salesOverTime($user, $from, $to, $filters),
                'products_by_company' => $this->productsByCompany($user),
            ],
            'stats_by_company' => $this->getStatsByCompany($from, $to),
        ];
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function salesByProvince(User $user, ?string $from, ?string $to, array $filters = []): array
    {
        $supplierId = $filters['supplier_id'] ?? null;
        $companyId = $filters['company_id'] ?? null;

        $query = Sale::query()
            ->join('provinces', 'sales.province_id', '=', 'provinces.id')
            ->select('provinces.name as label', DB::raw('SUM(sales.quantity) as value'))
            ->groupBy('provinces.id', 'provinces.name')
            ->orderByDesc('value')
            ->limit(10);

        $this->scopeSales($query, $user);
        $this->applyDateFilter($query, $from, $to);
        $this->applySupplierFilter($query, $supplierId);

        // Admin can filter by company
        if ($user->isAdmin() && $companyId) {
            $query->whereHas('uploadBatch', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        return $query->get()->map(fn ($row) => [
            'label' => $row->label,
            'value' => (int) $row->value,
        ])->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function topSuppliers(User $user, ?string $from, ?string $to, ?int $limit = 10, array $filters = []): array
    {
        $supplierId = $filters['supplier_id'] ?? null;
        $companyId = $filters['company_id'] ?? null;

        $query = Sale::query()
            ->join('suppliers', 'sales.supplier_id', '=', 'suppliers.id')
            ->select('suppliers.name as label', DB::raw('COUNT(*) as value'))
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('value');

        if ($limit) {
            $query->limit($limit);
        }

        $this->scopeSales($query, $user);
        $this->applyDateFilter($query, $from, $to);
        $this->applySupplierFilter($query, $supplierId);

        // Admin can filter by company
        if ($user->isAdmin() && $companyId) {
            $query->whereHas('uploadBatch', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        return $query->get()->map(fn ($row) => [
            'label' => $row->label,
            'value' => (int) $row->value,
        ])->all();
    }

    /**
     * @return list<array{label: string, value: int, code: string|null}>
     */
    private function topProducts(User $user, ?string $from, ?string $to, ?int $limit = 10, array $filters = []): array
    {
        return $this->rankedProducts($user, $from, $to, $limit, 'desc', $filters);
    }

    /**
     * @return list<array{label: string, value: int, code: string|null}>
     */
    private function bottomProducts(User $user, ?string $from, ?string $to, ?int $limit = 10, array $filters = []): array
    {
        return $this->rankedProducts($user, $from, $to, $limit, 'asc', $filters);
    }

    /**
     * @return list<array{label: string, value: int, code: string|null}>
     */
    private function rankedProducts(User $user, ?string $from, ?string $to, ?int $limit, string $direction, array $filters = []): array
    {
        $supplierId = $filters['supplier_id'] ?? null;
        $companyId = $filters['company_id'] ?? null;

        $totalVolumeQuery = Sale::query();
        $this->scopeSales($totalVolumeQuery, $user);
        $this->applyDateFilter($totalVolumeQuery, $from, $to);
        $this->applySupplierFilter($totalVolumeQuery, $supplierId);

        // Admin can filter by company
        if ($user->isAdmin() && $companyId) {
            $totalVolumeQuery->whereHas('uploadBatch', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        $totalVolume = (int) $totalVolumeQuery->sum('quantity');

        $query = Sale::query()
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->select(
                'products.name as label',
                'products.code',
                DB::raw('SUM(sales.quantity) as value')
            )
            ->groupBy('products.id', 'products.name', 'products.code')
            ->orderBy('value', $direction);

        $this->scopeSales($query, $user);
        $this->applyDateFilter($query, $from, $to);
        $this->applySupplierFilter($query, $supplierId);

        // Admin can filter by company
        if ($user->isAdmin() && $companyId) {
            $query->whereHas('uploadBatch', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        if (is_numeric($limit)) {
            $query->limit((int) $limit);
        }

        return $query->get()->map(fn ($row) => [
            'label' => $row->label,
            'code' => $row->code,
            'value' => (int) $row->value,
            'percentage' => $totalVolume ? round(($row->value / $totalVolume) * 100, 2) : 0,
        ])->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function salesOverTime(User $user, ?string $from, ?string $to, array $filters = []): array
    {
        $supplierId = $filters['supplier_id'] ?? null;
        $companyId = $filters['company_id'] ?? null;

        $query = Sale::query()
            ->select(DB::raw('DATE(sold_at) as label'), DB::raw('SUM(quantity) as value'))
            ->groupBy(DB::raw('DATE(sold_at)'))
            ->orderBy('label')
            ->limit(30);

        $this->scopeSales($query, $user);
        $this->applyDateFilter($query, $from, $to);
        $this->applySupplierFilter($query, $supplierId);

        // Admin can filter by company
        if ($user->isAdmin() && $companyId) {
            $query->whereHas('uploadBatch', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        return $query->get()->map(fn ($row) => [
            'label' => (string) $row->label,
            'value' => (int) $row->value,
        ])->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function productsByCompany(User $user): array
    {
        $query = Product::query()
            ->join('companies', 'products.company_id', '=', 'companies.id')
            ->select('companies.name as label', DB::raw('COUNT(*) as value'))
            ->groupBy('companies.id', 'companies.name');

        $this->scopeProducts($query, $user);

        return $query->get()->map(fn ($row) => [
            'label' => $row->label,
            'value' => (int) $row->value,
        ])->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function productsByCompanyForWarehouse(int $warehouseId): array
    {
        return Sale::query()
            ->where('warehouse_id', $warehouseId)
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->join('companies', 'products.company_id', '=', 'companies.id')
            ->select('companies.name as label', DB::raw('COUNT(DISTINCT products.id) as value'))
            ->groupBy('companies.id', 'companies.name')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'value' => (int) $row->value,
            ])->all();
    }

    private function applyDateFilter($query, ?string $from, ?string $to): void
    {
        if ($from) {
            $query->whereDate('sales.sold_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('sales.sold_at', '<=', $to);
        }
    }

    private function applySupplierFilter($query, ?int $supplierId): void
    {
        if ($supplierId) {
            $query->where('sales.supplier_id', $supplierId);
        }
    }

    private function calculateTotalRevenue($query): float
    {
        return (float) $query->sum(DB::raw('quantity * unit_price * (1 - discount / 100)'));
    }

    /**
     * @return list<array{company_id: int, company_name: string, sales_count: int, quantity_sold: int, total_revenue: float}>
     */
    private function getStatsByCompany(?string $from, ?string $to): array
    {
        $query = Sale::query()
            ->join('upload_batches', 'sales.upload_batch_id', '=', 'upload_batches.id')
            ->join('companies', 'upload_batches.company_id', '=', 'companies.id')
            ->select(
                'companies.id as company_id',
                'companies.name as company_name',
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(sales.quantity) as quantity_sold'),
                DB::raw('SUM(sales.quantity * sales.unit_price * (1 - sales.discount / 100)) as total_revenue')
            )
            ->groupBy('companies.id', 'companies.name')
            ->orderByDesc('quantity_sold');

        $this->applyDateFilter($query, $from, $to);

        return $query->get()->map(function ($row) {
            return [
                'company_id' => $row->company_id,
                'company_name' => $row->company_name,
                'sales_count' => (int) $row->sales_count,
                'quantity_sold' => (int) $row->quantity_sold,
                'total_revenue' => round((float) $row->total_revenue, 2),
            ];
        })->all();
    }

    /**
     * Get quantity summaries for the dashboard
     * Returns totals only for non-activated companies, full details for activated ones
     */
    public function getQuantitySummaries(User $user, array $filters = []): array
    {
        return $this->quantitySummary->getSummaries($user, $filters);
    }

    /**
     * Get pharmacy details for activated companies
     */
    public function getPharmacyDetails(User $user, array $filters = []): array
    {
        return $this->quantitySummary->getPharmacyDetails($user, $filters);
    }
}
