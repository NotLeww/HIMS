<?php

namespace App\Enums;

enum AlertType: string
{
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    case Overstock = 'overstock';

    public function label(): string
    {
        return match ($this) {
            self::LowStock => 'Low Stock',
            self::OutOfStock => 'Out of Stock',
            self::ExpiringSoon => 'Expiring Soon',
            self::Expired => 'Expired',
            self::Overstock => 'Overstock',
        };
    }

    public function defaultSeverity(): AlertSeverity
    {
        return match ($this) {
            self::OutOfStock, self::Expired => AlertSeverity::Critical,
            self::LowStock, self::ExpiringSoon => AlertSeverity::Warning,
            self::Overstock => AlertSeverity::Info,
        };
    }

    /**
     * Expiry alerts are raised against a specific batch; stock alerts are
     * raised against the item as a whole.
     */
    public function isBatchScoped(): bool
    {
        return in_array($this, [self::ExpiringSoon, self::Expired], true);
    }
}
