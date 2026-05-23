<?php

namespace App\Services;

use App\Models\AnalyticsProductPharmacyRollup;
use App\Models\AnalyticsProductProvinceRollup;
use App\Models\AnalyticsProductRollup;
use App\Models\Product;
use App\Models\Province;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompanyAnalyticsService
{
    public function assertProductBelongsToCompany(User $user, Product $product): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if (! $user->isCompanyUser() || $user->company_id !== $product->company_id) {
            abort(403, 'غير مصرح بالوصول لهذا الصنف.');
        }
    }

    /**
     * @return Collection<int, object{name: string, code: string, product_id: int, total_quantity: int, sale_count: int}>
     */
    public function productsWithTotals(User $user, ?string $search = null): Collection
    {
        if (! $user->isCompanyUser() || ! $user->company_id) {
            abort(403);
        }

        $companyId = $user->company_id;

        $rollupExists = AnalyticsProductRollup::query()
            ->whereIn('product_id', Product::query()->where('company_id', $companyId)->select('id'))
            ->exists();

        $query = Product::query()
            ->where('company_id', $companyId)
            ->when($search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            }));

        if ($rollupExists) {
            return $query
                ->leftJoin('analytics_product_rollups as r', 'r.product_id', '=', 'products.id')
                ->select([
                    'products.id as product_id',
                    'products.name',
                    'products.code',
                    DB::raw('COALESCE(r.total_quantity, 0) as total_quantity'),
                    DB::raw('COALESCE(r.sale_count, 0) as sale_count'),
                ])
                ->orderByDesc('total_quantity')
                ->get();
        }

        return $query
            ->leftJoin('sales', 'sales.product_id', '=', 'products.id')
            ->select([
                'products.id as product_id',
                'products.name',
                'products.code',
                DB::raw('COALESCE(SUM(sales.quantity), 0) as total_quantity'),
                DB::raw('COUNT(sales.id) as sale_count'),
            ])
            ->groupBy('products.id', 'products.name', 'products.code')
            ->orderByDesc('total_quantity')
            ->get();
    }

    /**
     * @return Collection<int, object{province_id: int, province_name: string, total_quantity: int, sale_count: int}>
     */
    public function productBreakdownByProvince(User $user, Product $product): Collection
    {
        $this->assertProductBelongsToCompany($user, $product);

        $fromRollup = AnalyticsProductProvinceRollup::query()
            ->where('product_id', $product->id)
            ->join('provinces', 'provinces.id', '=', 'analytics_product_province_rollups.province_id')
            ->select([
                'provinces.id as province_id',
                'provinces.name as province_name',
                'analytics_product_province_rollups.total_quantity',
                'analytics_product_province_rollups.sale_count',
            ])
            ->orderByDesc('analytics_product_province_rollups.total_quantity')
            ->get();

        if ($fromRollup->isNotEmpty()) {
            return $fromRollup;
        }

        return Sale::query()
            ->where('product_id', $product->id)
            ->join('provinces', 'provinces.id', '=', 'sales.province_id')
            ->select([
                'provinces.id as province_id',
                'provinces.name as province_name',
                DB::raw('SUM(sales.quantity) as total_quantity'),
                DB::raw('COUNT(sales.id) as sale_count'),
            ])
            ->groupBy('provinces.id', 'provinces.name')
            ->orderByDesc('total_quantity')
            ->get();
    }

    /**
     * @return Collection<int, object{pharmacy_id: int, pharmacy_name: string, total_quantity: int, sale_count: int}>
     */
    public function productPharmaciesInProvince(User $user, Product $product, Province $province): Collection
    {
        $this->assertProductBelongsToCompany($user, $product);

        $fromRollup = AnalyticsProductPharmacyRollup::query()
            ->where('product_id', $product->id)
            ->where('province_id', $province->id)
            ->join('pharmacies', 'pharmacies.id', '=', 'analytics_product_pharmacy_rollups.pharmacy_id')
            ->select([
                'pharmacies.id as pharmacy_id',
                'pharmacies.name as pharmacy_name',
                'analytics_product_pharmacy_rollups.total_quantity',
                'analytics_product_pharmacy_rollups.sale_count',
            ])
            ->orderByDesc('analytics_product_pharmacy_rollups.total_quantity')
            ->get();

        if ($fromRollup->isNotEmpty()) {
            return $fromRollup;
        }

        return Sale::query()
            ->where('sales.product_id', $product->id)
            ->where('sales.province_id', $province->id)
            ->join('pharmacies', 'pharmacies.id', '=', 'sales.pharmacy_id')
            ->select([
                'pharmacies.id as pharmacy_id',
                'pharmacies.name as pharmacy_name',
                DB::raw('SUM(sales.quantity) as total_quantity'),
                DB::raw('COUNT(sales.id) as sale_count'),
            ])
            ->groupBy('pharmacies.id', 'pharmacies.name')
            ->orderByDesc('total_quantity')
            ->get();
    }

    /**
     * @return array{p1: array{total_quantity: int, sale_count: int}, p2: array{total_quantity: int, sale_count: int}}
     */
    public function comparePeriods(User $user, string $period1From, string $period1To, string $period2From, string $period2To): array
    {
        if (! $user->isCompanyUser() || ! $user->company_id) {
            abort(403);
        }

        $companyId = $user->company_id;

        $aggregate = function (string $from, string $to) use ($companyId) {
            $row = Sale::query()
                ->whereHas('product', fn ($q) => $q->where('company_id', $companyId))
                ->whereDate('sold_at', '>=', $from)
                ->whereDate('sold_at', '<=', $to)
                ->selectRaw('COALESCE(SUM(quantity),0) as total_quantity, COUNT(*) as sale_count')
                ->first();

            return [
                'total_quantity' => (int) ($row->total_quantity ?? 0),
                'sale_count' => (int) ($row->sale_count ?? 0),
            ];
        };

        return [
            'period_1' => array_merge([
                'from' => $period1From,
                'to' => $period1To,
            ], $aggregate($period1From, $period1To)),
            'period_2' => array_merge([
                'from' => $period2From,
                'to' => $period2To,
            ], $aggregate($period2From, $period2To)),
        ];
    }
}
