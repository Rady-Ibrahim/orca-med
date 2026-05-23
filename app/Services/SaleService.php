<?php

namespace App\Services;

use App\Models\Pharmacy;
use App\Models\Sale;
use App\Models\User;
use App\Services\Concerns\AppliesCompanyScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SaleService
{
    use AppliesCompanyScope;

    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Sale::query()
            ->with(['product.company', 'pharmacy.supplier', 'province']);

        $this->scopeSales($query, $user);

        return $query
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->where('product_id', $id))
            ->when($filters['province_id'] ?? null, fn ($q, $id) => $q->where('province_id', $id))
            ->when($filters['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($filters['pharmacy_id'] ?? null, fn ($q, $id) => $q->where('pharmacy_id', $id))
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->whereHas('product', fn ($pq) => $pq->where('company_id', $id)))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('sold_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('sold_at', '<=', $to))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"));
            })
            ->orderByDesc('sold_at')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $pharmacy = Pharmacy::with('supplier')->findOrFail($data['pharmacy_id']);

            $sale = Sale::create([
                'product_id' => $data['product_id'],
                'pharmacy_id' => $pharmacy->id,
                'supplier_id' => $pharmacy->supplier_id,
                'province_id' => $pharmacy->province_id,
                'warehouse_id' => $pharmacy->warehouse_id,
                'quantity' => $data['quantity'],
                'sold_at' => $data['sold_at'],
                'import_hash' => $this->buildImportHash(
                    (int) $data['product_id'],
                    $pharmacy->id,
                    $data['sold_at'],
                    (int) $data['quantity'],
                    $pharmacy->warehouse_id
                ),
            ]);

            return $sale->load(['product.company', 'pharmacy.supplier', 'province']);
        });
    }

    public function update(Sale $sale, array $data): Sale
    {
        if (isset($data['pharmacy_id'])) {
            $pharmacy = Pharmacy::findOrFail($data['pharmacy_id']);
            $data['supplier_id'] = $pharmacy->supplier_id;
            $data['province_id'] = $pharmacy->province_id;
            $data['warehouse_id'] = $pharmacy->warehouse_id;
        }

        $sale->update($data);

        if (isset($data['quantity']) || isset($data['sold_at']) || isset($data['product_id']) || isset($data['pharmacy_id'])) {
            $sale->refresh();
            $sale->update([
                'import_hash' => $this->buildImportHash(
                    $sale->product_id,
                    $sale->pharmacy_id,
                    $sale->sold_at->format('Y-m-d'),
                    $sale->quantity,
                    $sale->warehouse_id
                ),
            ]);
        }

        return $sale->fresh()->load(['product.company', 'pharmacy.supplier', 'province']);
    }

    public function delete(Sale $sale): void
    {
        $sale->delete();
    }

    private function buildImportHash(int $productId, int $pharmacyId, string $soldAt, int $quantity, ?int $warehouseId = null): string
    {
        $w = $warehouseId ?? 0;

        return hash('sha256', "{$productId}|{$pharmacyId}|{$soldAt}|{$quantity}|{$w}");
    }
}
