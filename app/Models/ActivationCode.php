<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'company_id',
        'duration_days',
        'max_uses',
        'used_count',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isAvailable(): bool
    {
        return $this->is_active
            && (!$this->expires_at || $this->expires_at->isFuture())
            && $this->used_count < $this->max_uses;
    }
}
