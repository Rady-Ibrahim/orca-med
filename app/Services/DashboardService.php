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

    /**
     * @return array<string, mixed>
     */
    public function getStats(User $user, array $filters = []): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        $salesQuery = Sale::query();
        $this->scopeSales($salesQuery, $user);

        if ($from) {
            $salesQuery->whereDate('sold_at', '>=', $from);
        }
        if ($to) {
            $salesQuery->whereDate('sold_at', '<=', $to);
        }

        $productsQuery = Product::query();
        $this->scopeProducts($productsQuery, $user);

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
                    'provinces' => (clone $salesQuery)->distinct('province_id')->count('province_id'),
                    'suppliers' => (clone $salesQuery)->distinct('supplier_id')->count('supplier_id'),
                ],
                'charts' => [
                    'sales_by_province' => $this->salesByProvince($user, $from, $to),
                    'top_suppliers' => $this->topSuppliers($user, $from, $to),
                    'top_products' => $this->topProducts($user, $from, $to, 10),
                    'bottom_products' => $this->bottomProducts($user, $from, $to, 10),
                    'sales_over_time' => $this->salesOverTime($user, $from, $to),
                    'products_by_company' => $this->productsByCompanyForWarehouse($wid),
                ],
            ];
        }

        return [
            'totals' => [
                'provinces' => Province::count(),
                'suppliers' => Supplier::count(),
                'pharmacies' => Pharmacy::count(),
                'products' => (clone $productsQuery)->count(),
                'sales_count' => (clone $salesQuery)->count(),
                'quantity_sold' => (int) (clone $salesQuery)->sum('quantity'),
            ],
            'charts' => [
                'sales_by_province' => $this->salesByProvince($user, $from, $to),
                'top_suppliers' => $this->topSuppliers($user, $from, $to),
                'top_products' => $this->topProducts($user, $from, $to, 10),
                'bottom_products' => $this->bottomProducts($user, $from, $to, 10),
                'sales_over_time' => $this->salesOverTime($user, $from, $to),
                'products_by_company' => $this->productsByCompany($user),
            ],
        ];
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function salesByProvince(User $user, ?string $from, ?string $to): array
    {
        $query = Sale::query()
            ->join('provinces', 'sales.province_id', '=', 'provinces.id')
            ->select('provinces.name as label', DB::raw('SUM(sales.quantity) as value'))
            ->groupBy('provinces.id', 'provinces.name')
            ->orderByDesc('value')
            ->limit(10);

        $this->scopeSales($query, $user);
        $this->applyDateFilter($query, $from, $to);

        return $query->get()->map(fn ($row) => [
            'label' => $row->label,
            'value' => (int) $row->value,
        ])->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function topSuppliers(User $user, ?string $from, ?string $to, int $limit = 10): array
    {
        $query = Sale::query()
            ->join('suppliers', 'sales.supplier_id', '=', 'suppliers.id')
            ->select('suppliers.name as label', DB::raw('COUNT(*) as value'))
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('value')
            ->limit($limit);

        $this->scopeSales($query, $user);
        $this->applyDateFilter($query, $from, $to);

        return $query->get()->map(fn ($row) => [
            'label' => $row->label,
            'value' => (int) $row->value,
        ])->all();
    }

    /**
     * @return list<array{label: string, value: int, code: string|null}>
     */
    private function topProducts(User $user, ?string $from, ?string $to, int $limit): array
    {
        return $this->rankedProducts($user, $from, $to, $limit, 'desc');
    }

    /**
     * @return list<array{label: string, value: int, code: string|null}>
     */
    private function bottomProducts(User $user, ?string $from, ?string $to, int $limit): array
    {
        return $this->rankedProducts($user, $from, $to, $limit, 'asc');
    }

    /**
     * @return list<array{label: string, value: int, code: string|null}>
     */
    private function rankedProducts(User $user, ?string $from, ?string $to, int $limit, string $direction): array
    {
        $query = Sale::query()
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->select(
                'products.name as label',
                'products.code',
                DB::raw('SUM(sales.quantity) as value')
            )
            ->groupBy('products.id', 'products.name', 'products.code')
            ->orderBy('value', $direction)
            ->limit($limit);

        $this->scopeSales($query, $user);
        $this->applyDateFilter($query, $from, $to);

        return $query->get()->map(fn ($row) => [
            'label' => $row->label,
            'code' => $row->code,
            'value' => (int) $row->value,
        ])->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function salesOverTime(User $user, ?string $from, ?string $to): array
    {
        $query = Sale::query()
            ->select(DB::raw('DATE(sold_at) as label'), DB::raw('SUM(quantity) as value'))
            ->groupBy(DB::raw('DATE(sold_at)'))
            ->orderBy('label')
            ->limit(30);

        $this->scopeSales($query, $user);
        $this->applyDateFilter($query, $from, $to);

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
}
