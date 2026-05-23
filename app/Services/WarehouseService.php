<?php

namespace App\Services;

use App\Models\Province;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WarehouseService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Warehouse::query()
            ->with('province')
            ->withCount(['pharmacies', 'uploadBatches'])
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function create(array $data): Warehouse
    {
        return Warehouse::create($data)->load('province');
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        $warehouse->update($data);

        return $warehouse->fresh()->load('province');
    }

    public function delete(Warehouse $warehouse): void
    {
        $warehouse->delete();
    }

    /**
     * One logical supplier row per warehouse (for legacy supplier_id on pharmacies).
     */
    public function ensureShadowSupplier(Warehouse $warehouse): Supplier
    {
        $existing = Supplier::query()->where('warehouse_id', $warehouse->id)->first();
        if ($existing) {
            return $existing;
        }

        $provinceId = $warehouse->province_id ?? Province::query()->value('id');

        return Supplier::create([
            'warehouse_id' => $warehouse->id,
            'province_id' => $provinceId,
            'name' => 'مخزن: '.$warehouse->name,
            'phone' => $warehouse->phone,
            'address' => $warehouse->address,
        ]);
    }
}
