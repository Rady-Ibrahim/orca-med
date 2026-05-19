<?php

namespace App\DTOs;

use App\Models\UploadBatch;

readonly class SaleImportResult
{
    public function __construct(
        public UploadBatch $batch,
        public bool $success,
        public string $message,
    ) {}
}
