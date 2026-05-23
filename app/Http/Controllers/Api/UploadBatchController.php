<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UploadBatchResource;
use App\Models\UploadBatch;
use App\Services\UploadBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UploadBatchController extends Controller
{
    public function __construct(
        private readonly UploadBatchService $uploadBatchService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return UploadBatchResource::collection(
            $this->uploadBatchService->list($request->user(), $request->only(['per_page']))
        )->response();
    }

    public function show(Request $request, UploadBatch $uploadBatch): JsonResponse
    {
        $batch = $this->uploadBatchService->findForUser($uploadBatch->id, $request->user());

        return (new UploadBatchResource($batch))->response();
    }

    public function downloadErrors(Request $request, UploadBatch $uploadBatch): BinaryFileResponse|JsonResponse
    {
        $this->uploadBatchService->findForUser($uploadBatch->id, $request->user());

        $path = $this->uploadBatchService->downloadErrorReport($uploadBatch);

        if (! $path) {
            return response()->json(['message' => 'لا يوجد تقرير أخطاء.'], 404);
        }

        return response()->download($path, 'errors-batch-'.$uploadBatch->id.'.csv');
    }
}
