<?php

namespace App\Models;

use App\Enums\UploadBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UploadBatch extends Model
{
    protected $fillable = [
        'uploaded_by',
        'company_id',
        'supplier_id',
        'province_id',
        'warehouse_id',
        'original_filename',
        'stored_path',
        'status',
        'total_rows',
        'success_count',
        'error_count',
        'duplicate_count',
        'error_report_path',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => UploadBatchStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(UploadBatchError::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
