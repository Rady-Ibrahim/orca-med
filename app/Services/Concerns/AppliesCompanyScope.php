<?php

namespace App\Services\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait AppliesCompanyScope
{
    protected function scopeProducts(Builder $query, User $user): Builder
    {
        if ($user->isCompanyUser() && $user->company_id) {
            return $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    protected function scopeSales(Builder $query, User $user): Builder
    {
        if ($user->isWarehouseUser() && $user->warehouse_id) {
            return $query->where('warehouse_id', $user->warehouse_id);
        }

        if ($user->isCompanyUser() && $user->company_id) {
            return $query->whereHas('product', fn (Builder $q) => $q->where('company_id', $user->company_id));
        }

        return $query;
    }

    protected function companyIdForUser(User $user): ?int
    {
        return $user->isCompanyUser() ? $user->company_id : null;
    }
}
