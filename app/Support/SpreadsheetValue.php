<?php

namespace App\Support;

class SpreadsheetValue
{
    public static function escapeFormula(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $first = substr($value, 0, 1);
        if ($first === '-' && is_numeric($value)) {
            return $value;
        }

        return in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }
}
