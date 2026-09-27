<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

final class BlindIndex
{
    public static function phone(string $value): string
    {
        $normalized = trim($value);

        if (preg_match('/^09[0-9]{9}$/D', $normalized) !== 1) {
            throw new InvalidArgumentException('The phone number cannot be blind-indexed because its format is invalid.');
        }

        return hash_hmac('sha256', "users.phone\0{$normalized}", self::key());
    }

    private static function key(): string
    {
        $configured = config('database.encryption.blind_index_key');

        if (! is_string($configured) || trim($configured) === '') {
            throw new RuntimeException('DB_BLIND_INDEX_KEY must be configured before encrypted searchable fields can be used.');
        }

        $key = str_starts_with($configured, 'base64:')
            ? base64_decode(substr($configured, 7), true)
            : $configured;

        if (! is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('DB_BLIND_INDEX_KEY must contain at least 32 bytes of key material.');
        }

        return $key;
    }
}
