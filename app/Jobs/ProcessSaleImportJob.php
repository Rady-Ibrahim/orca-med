<?php

namespace App\Jobs;

use App\Enums\UploadBatchStatus;
use App\Models\Sale;
use App\Models\UploadBatch;
use App\Services\AnalyticsRollupService;
use App\Services\QuantitySummaryService;
use App\Services\SaleImportService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessSaleImportJob
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $timeout = 600; // 10 minutes
    public int $tries = 1;

    public function __construct(
        public int $uploadBatchId,
    ) {}

    public function handle(SaleImportService $importService, AnalyticsRollupService $rollupService, QuantitySummaryService $quantityService): void
    {
        $batch = UploadBatch::find($this->uploadBatchId);
        if (! $batch) {
            return;
        }

        try {
            $result = $importService->processBatch($batch);

            if ($result->success && $result->batch->success_count > 0) {
                $productIds = Sale::query()
                    ->where('upload_batch_id', $batch->id)
                    ->distinct()
                    ->pluck('product_id')
                    ->all();

                $rollupService->rebuildForProductIds($productIds);

                // Rebuild quantity summaries for the company
                $quantityService->rebuildForCompany($batch->company_id);
            }
        } catch (Throwable $e) {
            $batch->update([
                'status' => UploadBatchStatus::Failed,
                'completed_at' => now(),
                'notes' => 'Job: '.$e->getMessage(),
            ]);

            throw $e;
        }
    }
}
