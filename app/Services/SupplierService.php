<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Supplier::query()
            ->with('province')
            ->withCount('pharmacies')
            ->when($filters['province_id'] ?? null, fn ($q, $id) => $q->where('province_id', $id))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): Supplier
    {
        return Supplier::create($data)->load('province');
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier->fresh()->load('province');
    }

    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
    }
}
