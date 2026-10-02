<?php

use App\Support\BlindIndex;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const ENCRYPTED_COLUMNS = [
        'privacy_requests' => [
            'requestor_name',
            'requestor_email',
            'details',
            'resolution_notes',
            'export_payload',
            'package_manifest',
            'exclusions_summary',
        ],
        'security_incidents' => [
            'description',
            'affected_system_or_data',
            'breach_assessment',
            'containment_actions',
            'remediation_notes',
            'metadata',
        ],
    ];

    /** @var array<string, list<string>> */
    private const JSON_COLUMNS = [
        'privacy_requests' => ['export_payload', 'package_manifest', 'exclusions_summary'],
        'security_incidents' => ['metadata'],
    ];

    public function up(): void
    {
        BlindIndex::phone('09000000000');
        $this->assertUserPhonesCanBeEncrypted();
        $this->assertEncryptedColumnsAreReadable();

        DB::transaction(function (): void {
            $this->encryptUserPhones();

            foreach (self::ENCRYPTED_COLUMNS as $table => $columns) {
                $this->encryptColumns($table, $columns);
            }
        });
    }

    public function down(): void
    {
        $this->assertUserPhonesCanBeDecrypted();
        $this->assertEncryptedColumnsAreReadable();

        DB::transaction(function (): void {
            foreach (self::ENCRYPTED_COLUMNS as $table => $columns) {
                $this->decryptColumns($table, $columns);
            }

            $this->decryptUserPhones();
        });
    }

    private function assertUserPhonesCanBeEncrypted(): void
    {
        DB::table('users')
            ->select(['id', 'phone'])
            ->whereNotNull('phone')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $value = (string) $user->phone;

                    if ($this->isPlaintextPhone($value)) {
                        BlindIndex::phone($value);

                        continue;
                    }

                    try {
                        $plaintext = Crypt::decryptString($value);
                    } catch (DecryptException $exception) {
                        throw new RuntimeException(
                            "Cannot safely encrypt users.phone for user ID {$user->id}.",
                            previous: $exception,
                        );
                    }

                    if (! $this->isPlaintextPhone($plaintext)) {
                        throw new RuntimeException("Cannot safely index users.phone for user ID {$user->id}.");
                    }
                }
            });
    }

    private function assertUserPhonesCanBeDecrypted(): void
    {
        $this->assertUserPhonesCanBeEncrypted();
    }

    private function assertEncryptedColumnsAreReadable(): void
    {
        foreach (self::ENCRYPTED_COLUMNS as $table => $columns) {
            DB::table($table)
                ->select(['id', ...$columns])
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($table, $columns): void {
                    foreach ($rows as $row) {
                        foreach ($columns as $column) {
                            $value = $row->{$column};

                            if (! is_string($value) || ! $this->looksLikeEncryptedPayload($value)) {
                                continue;
                            }

                            try {
                                $plaintext = Crypt::decryptString($value);
                            } catch (DecryptException $exception) {
                                throw new RuntimeException(
                                    "Cannot safely decrypt {$table}.{$column} for row ID {$row->id}.",
                                    previous: $exception,
                                );
                            }

                            if (in_array($column, self::JSON_COLUMNS[$table] ?? [], true)) {
                                json_decode($plaintext, true, flags: JSON_THROW_ON_ERROR);
                            }
                        }
                    }
                });
        }
    }

    private function encryptUserPhones(): void
    {
        DB::table('users')
            ->select(['id', 'phone'])
            ->whereNotNull('phone')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $value = (string) $user->phone;
                    $plaintext = $this->isPlaintextPhone($value)
                        ? $value
                        : Crypt::decryptString($value);

                    DB::table('users')->where('id', $user->id)->update([
                        'phone' => $this->isPlaintextPhone($value) ? Crypt::encryptString($plaintext) : $value,
                        'phone_blind_index' => BlindIndex::phone($plaintext),
                    ]);
                }
            });
    }

    /** @param list<string> $columns */
    private function encryptColumns(string $table, array $columns): void
    {
        DB::table($table)
            ->select(['id', ...$columns])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table, $columns): void {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column) {
                        $value = $row->{$column};

                        if ($value !== null && (! is_string($value) || ! $this->looksLikeEncryptedPayload($value))) {
                            $updates[$column] = Crypt::encryptString((string) $value);
                        }
                    }

                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
    }

    private function decryptUserPhones(): void
    {
        DB::table('users')
            ->select(['id', 'phone'])
            ->whereNotNull('phone')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $value = (string) $user->phone;

                    DB::table('users')->where('id', $user->id)->update([
                        'phone' => $this->isPlaintextPhone($value) ? $value : Crypt::decryptString($value),
                        'phone_blind_index' => null,
                    ]);
                }
            });
    }

    /** @param list<string> $columns */
    private function decryptColumns(string $table, array $columns): void
    {
        DB::table($table)
            ->select(['id', ...$columns])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table, $columns): void {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columns as $column) {
                        $value = $row->{$column};

                        if (is_string($value) && $this->looksLikeEncryptedPayload($value)) {
                            $updates[$column] = Crypt::decryptString($value);
                        }
                    }

                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
    }

    private function looksLikeEncryptedPayload(string $value): bool
    {
        $decoded = base64_decode($value, true);

        if (! is_string($decoded)) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload)
            && isset($payload['iv'], $payload['value'], $payload['mac']);
    }

    private function isPlaintextPhone(string $value): bool
    {
        return preg_match('/^09[0-9]{9}$/D', $value) === 1;
    }
};
