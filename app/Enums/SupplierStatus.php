<?php

namespace App\Enums;

enum SupplierStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case UnderReview = 'under_review';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Suspended => 'Suspended',
            self::UnderReview => 'Under Review',
        };
    }

    /**
     * Whether this supplier may be selected on new requisitions and POs.
     */
    public function canBeSelectedForNewOrders(): bool
    {
        return $this === self::Active;
    }
}
