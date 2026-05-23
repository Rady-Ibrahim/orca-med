<?php

namespace App\Services;

use App\Models\UploadBatch;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class UploadBatchService
{
    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = UploadBatch::query()
            ->with(['uploader', 'warehouse'])
            ->withCount('errors')
            ->when($user->isWarehouseUser(), fn ($q) => $q->where('warehouse_id', $user->warehouse_id))
            ->orderByDesc('created_at');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function findForUser(int $id, User $user): UploadBatch
    {
        $batch = UploadBatch::with(['uploader', 'warehouse', 'errors'])->findOrFail($id);

        if ($user->isWarehouseUser() && $batch->warehouse_id !== $user->warehouse_id) {
            abort(403, 'غير مصرح.');
        }

        return $batch;
    }

    public function downloadErrorReport(UploadBatch $batch): ?string
    {
        if (! $batch->error_report_path || ! Storage::disk('local')->exists($batch->error_report_path)) {
            return null;
        }

        return Storage::disk('local')->path($batch->error_report_path);
    }
}
