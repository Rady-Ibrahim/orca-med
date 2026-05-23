<?php

namespace App\Services;

use App\Models\Pharmacy;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PharmacyService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Pharmacy::query()
            ->with(['supplier', 'province'])
            ->when($filters['province_id'] ?? null, fn ($q, $id) => $q->where('province_id', $id))
            ->when($filters['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
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
