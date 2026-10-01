<?php

namespace App\Support;

final class MetricDetails
{
    public static function from(iterable $records, int $total, callable $format, string $empty): array
    {
        $details = collect($records)
            ->take(5)
            ->map($format)
            ->filter(fn (mixed $detail): bool => is_string($detail) && trim($detail) !== '')
            ->values();

        if ($details->isEmpty()) {
            return [$empty];
        }

        $remaining = max(0, $total - $details->count());
        if ($remaining > 0) {
            $details->push(number_format($remaining).' more — open the card to view all');
        }

        return $details->all();
    }
}
