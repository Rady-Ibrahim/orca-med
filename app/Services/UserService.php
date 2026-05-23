<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return User::query()
            ->with(['company', 'warehouse'])
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): User
    {
        $data['password'] = Hash::make($data['password']);

        if (($data['role'] ?? null) === UserRole::Admin->value || ($data['role'] ?? null) === UserRole::Admin) {
            $data['company_id'] = null;
            $data['warehouse_id'] = null;
        }

        if (($data['role'] ?? null) === UserRole::Warehouse->value || ($data['role'] ?? null) === UserRole::Warehouse) {
            $data['company_id'] = null;
        }

        return User::create($data)->load(['company', 'warehouse']);
    }

    public function update(User $user, array $data): User
    {
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $role = $data['role'] ?? null;
        if ($role !== null) {
            $roleEnum = $role instanceof UserRole ? $role : UserRole::tryFrom((string) $role);
            if ($roleEnum === UserRole::Admin) {
                $data['company_id'] = null;
                $data['warehouse_id'] = null;
            } elseif ($roleEnum === UserRole::Warehouse) {
                $data['company_id'] = null;
            } elseif ($roleEnum === UserRole::Company) {
                $data['warehouse_id'] = null;
            }
        }

        $user->update($data);

        return $user->fresh()->load(['company', 'warehouse']);
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
