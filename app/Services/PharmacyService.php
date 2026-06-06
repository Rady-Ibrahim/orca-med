<?php

namespace App\Services;

use App\Models\Pharmacy;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PharmacyService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $pharmacyIds = Pharmacy::query()
            ->when($filters['province_id'] ?? null, fn($q, $id) => $q->where('province_id', $id))
            ->when($filters['supplier_id'] ?? null, fn($q, $id) => $q->where('supplier_id', $id))
            ->when($filters['search'] ?? null, fn($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->pluck('id');

        return Pharmacy::query()
            ->with(['supplier', 'province'])
            ->whereIn('pharmacies.id', $pharmacyIds)
            ->leftJoin('sales', 'pharmacies.id', '=', 'sales.pharmacy_id')
            ->leftJoin('products', 'sales.product_id', '=', 'products.id')
            ->select(
                'pharmacies.id',
                'pharmacies.name',
                'pharmacies.supplier_id',
                'pharmacies.province_id',
                'pharmacies.warehouse_id',
                'pharmacies.license_number',
                'pharmacies.phone',
                'pharmacies.address',
                'pharmacies.upload_batch_id',
                'pharmacies.created_at',
                'pharmacies.updated_at',
                DB::raw('COUNT(DISTINCT sales.id) as sales_count'),
                DB::raw('COUNT(DISTINCT products.id) as products_count'),
                DB::raw('COALESCE(SUM(sales.quantity * sales.unit_price * (1 - sales.discount / 100)), 0) as total_revenue')
            )
            ->groupBy(
                'pharmacies.id',
                'pharmacies.name',
                'pharmacies.supplier_id',
                'pharmacies.province_id',
                'pharmacies.warehouse_id',
                'pharmacies.license_number',
                'pharmacies.phone',
                'pharmacies.address',
                'pharmacies.upload_batch_id',
                'pharmacies.created_at',
                'pharmacies.updated_at'
            )
            ->orderBy('pharmacies.name')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): Pharmacy
    {
        if (empty($data['province_id']) && ! empty($data['supplier_id'])) {
            $supplier = Supplier::findOrFail($data['supplier_id']);
            $data['province_id'] = $supplier->province_id;
            if (empty($data['warehouse_id']) && $supplier->warehouse_id) {
                $data['warehouse_id'] = $supplier->warehouse_id;
            }
        }

        return Pharmacy::create($data)->load(['supplier', 'province']);
    }

    public function update(Pharmacy $pharmacy, array $data): Pharmacy
    {
        $pharmacy->update($data);

        return $pharmacy->fresh()->load(['supplier', 'province']);
    }

    public function delete(Pharmacy $pharmacy): void
    {
        $pharmacy->delete();
    }
}
