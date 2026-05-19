<?php

namespace App\Models;

use App\Enums\AccessRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyAccessRequest extends Model
{
    protected $fillable = [
        'company_id',
        'product_id',
        'requested_by',
        'status',
        'request_note',
        'admin_note',
        'approved_by',
        'requested_at',
        'approved_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AccessRequestStatus::class,
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
