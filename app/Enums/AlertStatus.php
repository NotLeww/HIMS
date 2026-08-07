<?php

namespace App\Enums;

enum AlertStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Acknowledged => 'Acknowledged',
            self::Resolved => 'Resolved',
        };
    }

    /**
     * Open and acknowledged alerts still describe a live condition, so the
     * alert generator must not raise a duplicate for them.
     */
    public function isActive(): bool
    {
        return $this !== self::Resolved;
    }
}
