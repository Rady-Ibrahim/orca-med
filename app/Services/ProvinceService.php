<?php

namespace App\Services;

use App\Models\Province;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProvinceService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Province::query()
            ->withCount(['suppliers', 'pharmacies'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function all(): Collection
    {
        return Province::orderBy('name')->get();
    }

    public function create(array $data): Province
    {
        return Province::create($data);
    }

    public function update(Province $province, array $data): Province
    {
        $province->update($data);

        return $province->fresh();
    }

    public function delete(Province $province): void
    {
        $province->delete();
    }
}
