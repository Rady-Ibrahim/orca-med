<?php

namespace App\Enums;

enum WarehouseType: string
{
    case Wholesale = 'wholesale';
    case Retail = 'retail';

    public function label(): string
    {
        return match ($this) {
            self::Wholesale => 'جملة',
            self::Retail => 'قطاعي',
        };
    }
}
