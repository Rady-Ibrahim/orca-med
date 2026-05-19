<?php

namespace App\Enums;

enum AccessRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }
}
