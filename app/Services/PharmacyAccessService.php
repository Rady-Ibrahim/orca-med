<?php

namespace App\Services;

use App\Enums\AccessRequestStatus;
use App\Models\PharmacyAccessRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PharmacyAccessService
{
    public function hasApprovedAccess(?int $companyId, ?int $productId): bool
    {
        if (! $companyId || ! $productId) {
            return false;
        }

        return PharmacyAccessRequest::query()
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('status', AccessRequestStatus::Approved)
            ->exists();
    }

    public function shouldMaskPharmacies(User $user, ?int $productId): bool
    {
        if ($user->isAdmin()) {
            return false;
        }

        if (! $user->isCompanyUser() || ! $productId) {
            return true;
        }

        return ! $this->hasApprovedAccess($user->company_id, $productId);
    }

    public function maskPharmacyName(int $displayIndex): string
    {
        return 'Pharmacy #'.$displayIndex;
    }

    public function maskSupplierName(int $displayIndex): string
    {
        return 'Supplier #'.$displayIndex;
    }

    public function requestAccess(User $user, int $productId, ?string $note = null): PharmacyAccessRequest
    {
        $companyId = $user->company_id;

        if (! $companyId) {
            throw new \InvalidArgumentException('Company user must belong to a company.');
        }

        $pending = PharmacyAccessRequest::query()
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('status', AccessRequestStatus::Pending)
            ->first();

        if ($pending) {
            return $pending;
        }

        return PharmacyAccessRequest::create([
            'company_id' => $companyId,
            'product_id' => $productId,
            'requested_by' => $user->id,
            'status' => AccessRequestStatus::Pending,
            'request_note' => $note,
            'requested_at' => now(),
        ]);
    }

    public function approve(PharmacyAccessRequest $request, User $admin, ?string $note = null): PharmacyAccessRequest
    {
        return DB::transaction(function () use ($request, $admin, $note) {
            $request->update([
                'status' => AccessRequestStatus::Approved,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'admin_note' => $note,
            ]);

            return $request->fresh();
        });
    }

    public function reject(PharmacyAccessRequest $request, User $admin, ?string $note = null): PharmacyAccessRequest
    {
        return DB::transaction(function () use ($request, $admin, $note) {
            $request->update([
                'status' => AccessRequestStatus::Rejected,
                'approved_by' => $admin->id,
                'rejected_at' => now(),
                'admin_note' => $note,
            ]);

            return $request->fresh();
        });
    }
}
