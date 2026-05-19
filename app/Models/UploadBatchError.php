<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadBatchError extends Model
{
    protected $fillable = [
        'upload_batch_id',
        'row_number',
        'column',
        'error_type',
        'message',
        'row_data',
    ];

    protected function casts(): array
    {
        return [
            'row_data' => 'array',
        ];
    }

    public function uploadBatch(): BelongsTo
    {
        return $this->belongsTo(UploadBatch::class);
    }
}
