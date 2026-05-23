<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSaleImportJob;
use App\Services\SaleImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleImportController extends Controller
{
    public function __construct(
        private readonly SaleImportService $saleImportService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', config('sale_import.allowed_extensions')),
                'max:'.((int) config('sale_import.max_file_size_mb') * 1024),
            ],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
        ]);

        $user = $request->user();
        $warehouseId = $user->isWarehouseUser()
            ? $user->warehouse_id
            : ($request->filled('warehouse_id') ? $request->integer('warehouse_id') : null);

        if ($user->isAdmin() && ! $warehouseId) {
            return response()->json(['message' => 'يجب تحديد المخزن (warehouse_id) عند الرفع كمدير.'], 422);
        }

        if ($user->isWarehouseUser() && ! $warehouseId) {
            return response()->json(['message' => 'حساب المخزن غير مرتبط بمخزن.'], 422);
        }

        $batch = $this->saleImportService->createQueuedBatch(
            $request->file('file'),
            $user,
            $warehouseId
        );

        ProcessSaleImportJob::dispatch($batch->id)->afterResponse();

        return response()->json([
            'message' => 'تم استلام الملف وجاري المعالجة في الخلفية.',
            'batch' => $batch->fresh(),
        ], 202);
    }
}
