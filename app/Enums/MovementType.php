<?php

namespace App\Enums;

enum MovementType: string
{
    case StockIn = 'stock_in';
    case StockOut = 'stock_out';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';
    case Disposal = 'disposal';
    case Issuance = 'issuance';
    case ReturnToSupplier = 'return_to_supplier';

    public function label(): string
    {
        return match ($this) {
            self::StockIn => 'Stock In',
            self::StockOut => 'Stock Out',
            self::Transfer => 'Transfer',
            self::Adjustment => 'Adjustment',
            self::Disposal => 'Disposal',
            self::Issuance => 'Issuance',
            self::ReturnToSupplier => 'Return to Supplier',
        };
    }

    /**
     * Whether this movement removes stock from its source location.
     */
    public function decrementsSource(): bool
    {
        return in_array($this, [
            self::StockOut,
            self::Transfer,
            self::Disposal,
            self::Issuance,
            self::ReturnToSupplier,
        ], true);
    }

    /**
     * Whether this movement adds stock to its destination location.
     */
    public function incrementsDestination(): bool
    {
        return in_array($this, [self::StockIn, self::Transfer], true);
    }

    /**
     * Adjustments carry a signed quantity and are applied directly rather
     * than moving stock between two locations.
     */
    public function isAdjustment(): bool
    {
        return $this === self::Adjustment;
    }

    public function requiresSourceLocation(): bool
    {
        return $this->decrementsSource();
    }

    public function requiresDestinationLocation(): bool
    {
        return $this->incrementsDestination();
    }
}
