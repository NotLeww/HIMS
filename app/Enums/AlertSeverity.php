<?php

namespace App\Enums;

enum AlertSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
        };
    }

    /**
     * Higher weight sorts first on the alerts dashboard.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Critical => 3,
            self::Warning => 2,
            self::Info => 1,
        };
    }

    /**
     * Tailwind classes used by the alert badges in the SWS views.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Critical => 'bg-red-100 text-red-700',
            self::Warning => 'bg-amber-100 text-amber-700',
            self::Info => 'bg-sky-100 text-sky-700',
        };
    }
}
