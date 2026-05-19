<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $fillable = ['name'];

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
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
