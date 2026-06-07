<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Services\Concerns\AppliesCompanyScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    use AppliesCompanyScope;

    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()
            ->with('company')
            ->withCount('sales')
            ->withAvg(['sales' => fn ($q) => $q->where('unit_price', '>', 0)], 'unit_price');

        $this->scopeProducts($query, $user);

        return $query
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): Product
    {
        return Product::create($data)->load('company');
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->fresh()->load('company');
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function assertCompanyAccess(User $user, Product $product): void
    {
        if ($user->isCompanyUser() && $user->company_id !== $product->company_id) {
            abort(403, 'غير مصرح بالوصول لهذا الصنف.');
        }
    }
}
