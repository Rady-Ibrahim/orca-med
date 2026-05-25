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
            'company_id' => ['required', 'exists:companies,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'province_id' => ['required', 'exists:provinces,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
        ]);

        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json(['message' => 'فقط الأدمن يمكنه رفع الملفات.'], 403);
        }

        $batch = $this->saleImportService->createQueuedBatch(
            $request->file('file'),
            $user,
            $request->integer('company_id'),
            $request->integer('supplier_id'),
            $request->integer('province_id'),
            $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null
        );

        ProcessSaleImportJob::dispatch($batch->id)->afterResponse();

        return response()->json([
            'message' => 'تم استلام الملف وجاري المعالجة في الخلفية.',
            'batch' => $batch->fresh(),
        ], 202);
    }
}
