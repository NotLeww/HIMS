<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertValuesCanBeEncrypted();

        Schema::table('users', function (Blueprint $table): void {
            $table->text('sms_mfa_phone')->nullable()->change();
        });

        DB::transaction(function (): void {
            DB::table('users')
                ->select(['id', 'sms_mfa_phone'])
                ->whereNotNull('sms_mfa_phone')
                ->orderBy('id')
                ->chunkById(200, function ($users): void {
                    foreach ($users as $user) {
                        if ($this->isEncrypted((string) $user->sms_mfa_phone)) {
                            continue;
                        }

                        DB::table('users')->where('id', $user->id)->update([
                            'sms_mfa_phone' => Crypt::encryptString((string) $user->sms_mfa_phone),
                        ]);
                    }
                });
        });
    }

    public function down(): void
    {
        $this->assertValuesCanBeDecrypted();

        DB::transaction(function (): void {
            DB::table('users')
                ->select(['id', 'sms_mfa_phone'])
                ->whereNotNull('sms_mfa_phone')
                ->orderBy('id')
                ->chunkById(200, function ($users): void {
                    foreach ($users as $user) {
                        $value = (string) $user->sms_mfa_phone;

                        if ($this->isPlaintextPhone($value)) {
                            continue;
                        }

                        DB::table('users')->where('id', $user->id)->update([
                            'sms_mfa_phone' => Crypt::decryptString($value),
                        ]);
                    }
                });
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('sms_mfa_phone', 11)->nullable()->change();
        });
    }

    private function assertValuesCanBeEncrypted(): void
    {
        DB::table('users')
            ->select(['id', 'sms_mfa_phone'])
            ->whereNotNull('sms_mfa_phone')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $value = (string) $user->sms_mfa_phone;

                    if (! $this->isPlaintextPhone($value) && ! $this->isEncrypted($value)) {
                        throw new RuntimeException(
                            "Cannot safely encrypt users.sms_mfa_phone for user ID {$user->id}: the value is neither a valid phone number nor decryptable ciphertext."
                        );
                    }
                }
            });
    }

    private function assertValuesCanBeDecrypted(): void
    {
        DB::table('users')
            ->select(['id', 'sms_mfa_phone'])
            ->whereNotNull('sms_mfa_phone')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $value = (string) $user->sms_mfa_phone;

                    if ($this->isPlaintextPhone($value)) {
                        continue;
                    }

                    try {
                        $plaintext = Crypt::decryptString($value);
                    } catch (DecryptException $exception) {
                        throw new RuntimeException(
                            "Cannot safely decrypt users.sms_mfa_phone for user ID {$user->id}.",
                            previous: $exception,
                        );
                    }

                    if (! $this->isPlaintextPhone($plaintext)) {
                        throw new RuntimeException(
                            "Cannot safely restore users.sms_mfa_phone for user ID {$user->id}: decrypted value is invalid."
                        );
                    }
                }
            });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            return $this->isPlaintextPhone(Crypt::decryptString($value));
        } catch (DecryptException) {
            return false;
        }
    }

    private function isPlaintextPhone(string $value): bool
    {
        return preg_match('/^09[0-9]{9}$/D', $value) === 1;
    }
};
