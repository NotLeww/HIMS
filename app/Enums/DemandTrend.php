<?php

namespace App\Enums;

/**
 * Which way consumption is moving across the analysis window.
 *
 * Derived by comparing the second half of the window against the first, so it
 * answers "is this item being used faster lately?" — the question that decides
 * whether a forecast based on the average is likely to run short.
 */
enum DemandTrend: string
{
    case Increasing = 'increasing';
    case Stable = 'stable';
    case Decreasing = 'decreasing';
    case Insufficient = 'insufficient_data';

    public function label(): string
    {
        return match ($this) {
            self::Increasing => 'Increasing',
            self::Stable => 'Stable',
            self::Decreasing => 'Decreasing',
            self::Insufficient => 'Not enough data',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Increasing => 'warning',
            self::Stable => 'success',
            self::Decreasing => 'primary',
            self::Insufficient => 'neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Increasing => 'arrow-trending-up',
            self::Decreasing => 'arrow-trending-down',
            default => 'minus',
        };
    }

    /**
     * A 15% swing either way is treated as movement; anything inside that band
     * is noise on the small daily volumes a hospital storeroom sees.
     */
    public static function fromChange(float $earlier, float $later): self
    {
        if ($earlier <= 0.0 && $later <= 0.0) {
            return self::Insufficient;
        }

        if ($earlier <= 0.0) {
            return self::Increasing;
        }

        $change = ($later - $earlier) / $earlier;

        return match (true) {
            $change > 0.15 => self::Increasing,
            $change < -0.15 => self::Decreasing,
            default => self::Stable,
        };
    }
}
