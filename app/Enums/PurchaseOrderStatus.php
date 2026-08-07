<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::PartiallyFulfilled => 'Partially Fulfilled',
            self::Fulfilled => 'Fulfilled',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Statuses this status is allowed to move to.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted => [self::Approved, self::Draft, self::Cancelled],
            self::Approved => [self::PartiallyFulfilled, self::Fulfilled, self::Cancelled],
            self::PartiallyFulfilled => [self::PartiallyFulfilled, self::Fulfilled, self::Cancelled],
            self::Fulfilled, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * A purchase order may only receive goods once it has been approved.
     */
    public function canReceiveStock(): bool
    {
        return in_array($this, [self::Approved, self::PartiallyFulfilled], true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Fulfilled, self::Cancelled], true);
    }
}
