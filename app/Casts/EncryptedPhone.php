<?php

namespace App\Casts;

use App\Support\BlindIndex;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

/** @implements CastsAttributes<string|null, string|null> */
class EncryptedPhone implements CastsAttributes, ComparesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('The encrypted phone number must be a string.');
        }

        return Crypt::decryptString($value);
    }

    /** @return array<string, string|null> */
    public function set(
        Model $model,
        string $key,
        #[\SensitiveParameter] mixed $value,
        array $attributes,
    ): array {
        if ($value === null) {
            return [$key => null, "{$key}_blind_index" => null];
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('The phone number must be a string.');
        }

        $current = $attributes[$key] ?? null;

        if (is_string($current)) {
            try {
                if (hash_equals(Crypt::decryptString($current), $value)) {
                    return [
                        $key => $current,
                        "{$key}_blind_index" => BlindIndex::phone($value),
                    ];
                }
            } catch (DecryptException) {
                // Legacy plaintext is handled by the deployment migration.
            }
        }

        return [
            $key => Crypt::encryptString($value),
            "{$key}_blind_index" => BlindIndex::phone($value),
        ];
    }

    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return is_string($firstValue) && is_string($secondValue)
            ? hash_equals($firstValue, $secondValue)
            : $firstValue === $secondValue;
    }
}
