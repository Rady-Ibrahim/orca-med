<?php

namespace App\Console\Commands;

use App\Models\Sale;
use App\Models\UploadBatch;
use App\Models\UploadBatchError;
use App\Services\SaleImportService;
use Illuminate\Console\Command;

class RepairImportBatch extends Command
{
    protected $signature = 'import:repair-batch {batch_id : Upload batch ID to re-import}';

    protected $description = 'Delete sales/errors for a batch and re-process the stored import file';

    public function handle(SaleImportService $importService): int
    {
        $batch = UploadBatch::findOrFail((int) $this->argument('batch_id'));

        $this->warn("Repairing batch #{$batch->id} ({$batch->original_filename})...");

        Sale::where('upload_batch_id', $batch->id)->delete();
        UploadBatchError::where('upload_batch_id', $batch->id)->delete();

        $batch->update([
            'status' => 'queued',
            'success_count' => 0,
            'error_count' => 0,
            'duplicate_count' => 0,
            'error_report_path' => null,
            'completed_at' => null,
        ]);

        $result = $importService->processBatch($batch->fresh());

        $this->info($result->message);

        return $result->success ? self::SUCCESS : self::FAILURE;
    }
}
