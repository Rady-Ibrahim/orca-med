<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Company = 'company';
    case Warehouse = 'warehouse';

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isCompany(): bool
    {
        return $this === self::Company;
    }

    public function isWarehouse(): bool
    {
        return $this === self::Warehouse;
    }
}
