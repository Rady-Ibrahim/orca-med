<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        ]);

        $result = $this->saleImportService->process(
            $request->file('file'),
            $request->user(),
        );

        return response()->json([
            'message' => $result->message,
            'success' => $result->success,
            'batch' => $result->batch,
        ], $result->success ? 200 : 422);
    }
}
