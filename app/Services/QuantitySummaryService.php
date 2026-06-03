<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Province;
use App\Models\QuantitySummary;
use App\Models\Sale;
use App\Models\User;
use App\Services\Concerns\AppliesCompanyScope;
use Illuminate\Support\Facades\DB;

class QuantitySummaryService
{
    use AppliesCompanyScope;

    /**
     * Get quantity summaries for a user
     * Returns totals grouped by product and province (for non-activated companies)
     * or full details (for activated companies and admins)
     */
    public function getSummaries(User $user, array $filters = []): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        // For non-activated companies: show only totals
        if ($user->isCompanyUser() && !$user->hasAnalyticsAccess()) {
            return $this->getTotalsOnly($user, $from, $to);
        }

        // For activated companies and admins: show full details
        return $this->getFullDetails($user, $from, $to);
    }

    /**
     * Get totals only (for non-activated companies)
     * Grouped by product and province
     */
    private function getTotalsOnly(User $user, ?string $from, ?string $to): array
    {
        $query = Sale::query()
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->join('provinces', 'sales.province_id', '=', 'provinces.id')
            ->join('suppliers', 'sales.supplier_id', '=', 'suppliers.id')
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'suppliers.name as supplier_name',
                'provinces.id as province_id',
                'provinces.name as province_name',
                DB::raw('SUM(sales.quantity) as total_quantity')
            )
            ->groupBy('products.id', 'products.name', 'suppliers.name', 'provinces.id', 'provinces.name')
            ->orderByDesc('total_quantity');

        $this->scopeSales($query, $user);
        $this->applyDateFilter($query, $from, $to);

        $results = $query->get();

        // Get unique pharmacy counts per product (with same filters)
        $pharmacyQuery = Sale::query();
        $this->scopeSales($pharmacyQuery, $user);
        $this->applyDateFilter($pharmacyQuery, $from, $to);

        $pharmacyCounts = $pharmacyQuery
            ->select('product_id', DB::raw('COUNT(DISTINCT pharmacy_id) as pharmacy_count'))
            ->groupBy('product_id')
            ->pluck('pharmacy_count', 'product_id');

        // Group by product
        $byProduct = $results->groupBy('product_id')->map(function ($items) use ($pharmacyCounts) {
            $first = $items->first();
            return [
                'product_id' => $first->product_id,
                'product_name' => $first->product_name,
                'supplier_name' => $first->supplier_name,
                'pharmacy_count' => $pharmacyCounts[$first->product_id] ?? 0,
                'total_quantity' => $items->sum('total_quantity'),
                'by_province' => $items->map(fn ($item) => [
                    'province_id' => $item->province_id,
                    'province_name' => $item->province_name,
                    'quantity' => $item->total_quantity,
                ])->values()->all(),
            ];
        })->values()->all();

        // Overall totals
        $overall = [
            'total_products' => count($byProduct),
            'total_quantity' => $results->sum('total_quantity'),
        ];

        return [
            'type' => 'totals_only',
            'by_product' => $byProduct,
            'overall' => $overall,
        ];
    }

    /**
     * Get full details (for activated companies and admins)
     */
    private function getFullDetails(User $user, ?string $from, ?string $to): array
    {
        $query = Sale::query()
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->join('provinces', 'sales.province_id', '=', 'provinces.id')
            ->join('pharmacies', 'sales.pharmacy_id', '=', 'pharmacies.id')
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'products.code as product_code',
                'provinces.id as province_id',
                'provinces.name as province_name',
                'pharmacies.id as pharmacy_id',
                'pharmacies.name as pharmacy_name',
                DB::raw('SUM(sales.quantity) as total_quantity')
            )
            ->groupBy('products.id', 'products.name', 'products.code', 'provinces.id', 'provinces.name', 'pharmacies.id', 'pharmacies.name')
            ->orderByDesc('total_quantity');

        $this->scopeSales($query, $user);
        $this->applyDateFilter($query, $from, $to);

        $results = $query->get();

        // Group by product
        $byProduct = $results->groupBy('product_id')->map(function ($items) {
            $first = $items->first();
            return [
                'product_id' => $first->product_id,
                'product_name' => $first->product_name,
                'product_code' => $first->product_code,
                'total_quantity' => $items->sum('total_quantity'),
                'by_province' => $items->groupBy('province_id')->map(function ($provinceItems) {
                    $provinceFirst = $provinceItems->first();
                    return [
                        'province_id' => $provinceFirst->province_id,
                        'province_name' => $provinceFirst->province_name,
                        'quantity' => $provinceItems->sum('total_quantity'),
                        'by_pharmacy' => $provinceItems->map(fn ($item) => [
                            'pharmacy_id' => $item->pharmacy_id,
                            'pharmacy_name' => $item->pharmacy_name,
                            'quantity' => $item->total_quantity,
                        ])->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return [
            'type' => 'full_details',
            'by_product' => $byProduct,
        ];
    }

    /**
     * Rebuild quantity summaries for a company
     * Called after import to update cached summaries
     */
    public function rebuildForCompany(int $companyId): void
    {
        // Clear existing summaries for this company
        QuantitySummary::where('company_id', $companyId)->delete();

        // Get all sales for this company
        $sales = Sale::query()
            ->whereHas('uploadBatch', fn ($q) => $q->where('company_id', $companyId))
            ->with('uploadBatch')
            ->get();

        // Group and insert summaries
        $summaries = $sales
            ->groupBy(function (Sale $sale) {
                return implode('|', [
                    $sale->product_id ?? 'null',
                    $sale->province_id ?? 'null',
                    $sale->warehouse_id ?? 'null',
                ]);
            })
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'company_id' => optional($first->uploadBatch)->company_id,
                    'product_id' => $first->product_id,
                    'province_id' => $first->province_id,
                    'warehouse_id' => $first->warehouse_id,
                    'total_quantity' => $group->sum('quantity'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->filter(fn ($summary) => ! is_null($summary['company_id']))
            ->values()
            ->all();

        // Insert in chunks
        foreach (array_chunk($summaries, 500) as $chunk) {
            QuantitySummary::insert($chunk);
        }
    }

    /**
     * Rebuild quantity summaries for specific product IDs
     */
    public function rebuildForProductIds(array $productIds): void
    {
        foreach ($productIds as $productId) {
            $product = Product::find($productId);
            if (!$product) continue;

            $this->rebuildForCompany($product->company_id);
        }
    }

    /**
     * Get pharmacy details for activated companies
     * Returns detailed list of pharmacies with quantities
     */
    public function getPharmacyDetails(User $user, array $filters = []): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $productId = $filters['product_id'] ?? null;
        $provinceId = $filters['province_id'] ?? null;
        $pharmacyId = $filters['pharmacy_id'] ?? null;

        $query = Sale::query()
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->join('pharmacies', 'sales.pharmacy_id', '=', 'pharmacies.id')
            ->join('provinces', 'sales.province_id', '=', 'provinces.id')
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'pharmacies.id as pharmacy_id',
                'pharmacies.name as pharmacy_name',
                'pharmacies.phone as pharmacy_phone',
                'pharmacies.address as pharmacy_address',
                'provinces.id as province_id',
                'provinces.name as province_name',
                DB::raw('SUM(sales.quantity) as total_quantity'),
                DB::raw('AVG(sales.unit_price) as avg_unit_price'),
                DB::raw('COUNT(*) as sales_count')
            )
            ->groupBy(
                'products.id', 'products.name',
                'pharmacies.id', 'pharmacies.name', 'pharmacies.phone', 'pharmacies.address',
                'provinces.id', 'provinces.name'
            )
            ->orderByDesc('total_quantity');

        $this->scopeSales($query, $user);
        $this->applyDateFilter($query, $from, $to);

        // Apply optional filters
        if ($productId) {
            $query->where('products.id', $productId);
        }
        if ($provinceId) {
            $query->where('provinces.id', $provinceId);
        }
        if ($pharmacyId) {
            $query->where('pharmacies.id', $pharmacyId);
        }

        return $query->paginate(50)->toArray();
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
