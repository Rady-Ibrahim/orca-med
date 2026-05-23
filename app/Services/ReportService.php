<?php

namespace App\Services;

use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Concerns\AppliesCompanyScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    use AppliesCompanyScope;

    public function salesReport(User $user, array $filters = []): LengthAwarePaginator
    {
        return app(SaleService::class)->list($user, $filters);
    }

    /**
     * @return array{top: Collection, bottom: Collection, by_company: Collection}
     */
    public function productsReport(User $user, array $filters = []): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $limit = (int) ($filters['limit'] ?? 10);

        $base = Sale::query()
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                'products.code',
                DB::raw('SUM(sales.quantity) as total_quantity'),
                DB::raw('COUNT(sales.id) as sales_count')
            )
            ->groupBy('products.id', 'products.name', 'products.code');

        $this->scopeSales($base, $user);

        if ($from) {
            $base->whereDate('sales.sold_at', '>=', $from);
        }
        if ($to) {
            $base->whereDate('sales.sold_at', '<=', $to);
        }

        if ($filters['company_id'] ?? null) {
            $base->where('products.company_id', $filters['company_id']);
        }

        $top = (clone $base)->orderByDesc('total_quantity')->limit($limit)->get();
        $bottom = (clone $base)->orderBy('total_quantity')->limit($limit)->get();

        $byCompany = Product::query()
            ->join('companies', 'products.company_id', '=', 'companies.id')
            ->leftJoin('sales', 'sales.product_id', '=', 'products.id')
            ->select(
                'companies.name as company_name',
                DB::raw('COUNT(DISTINCT products.id) as products_count'),
                DB::raw('COALESCE(SUM(sales.quantity), 0) as total_sold')
            )
            ->groupBy('companies.id', 'companies.name');

        $this->scopeProducts($byCompany, $user);

        if ($from) {
            $byCompany->where(function ($q) use ($from) {
                $q->whereNull('sales.sold_at')->orWhereDate('sales.sold_at', '>=', $from);
            });
        }

        return [
            'top' => $top,
            'bottom' => $bottom,
            'by_company' => $byCompany->get(),
        ];
    }

    /**
     * @return array{products: Collection, pharmacies: Collection, suppliers: Collection}
     */
    public function search(User $user, string $term, array $filters = []): array
    {
        $like = '%'.$term.'%';

        $products = Product::query()
            ->with('company')
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like));
        $this->scopeProducts($products, $user);

        if ($filters['company_id'] ?? null) {
            $products->where('company_id', $filters['company_id']);
        }

        $pharmacies = Pharmacy::with(['supplier', 'province'])
            ->where('name', 'like', $like)
            ->when($filters['province_id'] ?? null, fn ($q, $id) => $q->where('province_id', $id))
            ->limit(20)
            ->get();

        $suppliers = Supplier::with('province')
            ->where('name', 'like', $like)
            ->when($filters['province_id'] ?? null, fn ($q, $id) => $q->where('province_id', $id))
            ->limit(20)
            ->get();

        return [
            'products' => $products->limit(20)->get(),
            'pharmacies' => $pharmacies,
            'suppliers' => $suppliers,
        ];
    }
}
