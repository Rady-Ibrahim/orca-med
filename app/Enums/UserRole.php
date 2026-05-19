<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Company = 'company';

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isCompany(): bool
    {
        return $this === self::Company;
    }
}
