<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'province_id',
        'name',
        'phone',
        'address',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function pharmacies(): HasMany
    {
        return $this->hasMany(Pharmacy::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
