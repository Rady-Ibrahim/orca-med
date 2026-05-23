<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name',
        'contact_email',
        'contact_phone',
        'is_active',
        'sensitive_view_password',
    ];

    protected $hidden = [
        'sensitive_view_password',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sensitive_view_password' => 'hashed',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function pharmacyAccessRequests(): HasMany
    {
        return $this->hasMany(PharmacyAccessRequest::class);
    }
}
