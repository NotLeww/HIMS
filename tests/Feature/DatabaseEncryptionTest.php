<?php

namespace Tests\Feature;

use App\Models\PrivacyRequest;
use App\Models\SecurityIncident;
use App\Models\User;
use App\Support\BlindIndex;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_user_values_are_encrypted_at_rest_and_searchable_by_blind_index(): void
    {
        $phone = '09171234567';
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = User::factory()->create([
            'password' => 'a-secure-password',
            'phone' => $phone,
            'sms_mfa_phone' => $phone,
            'authenticator_secret' => $secret,
        ]);

        $raw = DB::table('users')->where('id', $user->id)->first([
            'password',
            'phone',
            'phone_blind_index',
            'sms_mfa_phone',
            'authenticator_secret',
        ]);

        $this->assertSame('AES-256-CBC', config('app.cipher'));
        $this->assertNotSame($phone, $raw->phone);
        $this->assertNotSame($phone, $raw->sms_mfa_phone);
        $this->assertNotSame($secret, $raw->authenticator_secret);
        $this->assertSame($phone, Crypt::decryptString($raw->phone));
        $this->assertSame(BlindIndex::phone($phone), $raw->phone_blind_index);
        $this->assertSame($phone, Crypt::decryptString($raw->sms_mfa_phone));
        $this->assertSame($secret, Crypt::decryptString($raw->authenticator_secret));
        $this->assertNotSame('a-secure-password', $raw->password);
        $this->assertTrue(Hash::check('a-secure-password', $raw->password));

        $fresh = $user->fresh();
        $this->assertSame($phone, $fresh->phone);
        $this->assertSame($phone, $fresh->sms_mfa_phone);
        $this->assertSame($secret, $fresh->authenticator_secret);
        $this->assertArrayNotHasKey('sms_mfa_phone', $fresh->toArray());
        $this->assertArrayNotHasKey('authenticator_secret', $fresh->toArray());
        $this->assertArrayNotHasKey('phone_blind_index', $fresh->toArray());
        $this->assertTrue(User::query()->wherePhoneNumber($phone)->whereKey($user->id)->exists());

        $fresh->forceFill(['phone' => $phone])->save();
        $this->assertSame(
            $raw->phone,
            DB::table('users')->where('id', $user->id)->value('phone'),
        );

        $newPrimaryPhone = '09170000001';
        $fresh->forceFill(['phone' => $newPrimaryPhone])->save();
        $newPrimaryCiphertext = DB::table('users')->where('id', $user->id)->value('phone');
        $this->assertNotSame($raw->phone, $newPrimaryCiphertext);
        $this->assertSame($newPrimaryPhone, Crypt::decryptString($newPrimaryCiphertext));
        $this->assertTrue(User::query()->wherePhoneNumber($newPrimaryPhone)->whereKey($user->id)->exists());
        $this->assertFalse(User::query()->wherePhoneNumber($phone)->whereKey($user->id)->exists());

        $updatedPhone = '09987654321';
        $fresh->forceFill(['sms_mfa_phone' => $updatedPhone])->save();
        $updatedCiphertext = DB::table('users')->where('id', $user->id)->value('sms_mfa_phone');

        $this->assertNotSame($raw->sms_mfa_phone, $updatedCiphertext);
        $this->assertSame($updatedPhone, Crypt::decryptString($updatedCiphertext));
        $this->assertSame($updatedPhone, $fresh->fresh()->sms_mfa_phone);
    }

    public function test_profile_remains_readable_during_the_legacy_phone_migration_window(): void
    {
        $user = User::factory()->create();
        $legacyPhone = '09175550123';

        DB::table('users')->where('id', $user->id)->update([
            'phone' => $legacyPhone,
            'phone_blind_index' => null,
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee($legacyPhone);

        $this->assertSame($legacyPhone, $user->fresh()->phone);
        $this->assertSame($legacyPhone, DB::table('users')->where('id', $user->id)->value('phone'));
    }

    public function test_primary_phone_cast_still_rejects_unrecognized_values(): void
    {
        $user = User::factory()->create();

        DB::table('users')->where('id', $user->id)->update([
            'phone' => 'unrecognized-value',
            'phone_blind_index' => null,
        ]);

        $this->expectException(DecryptException::class);

        $user->fresh()->phone;
    }

    public function test_privacy_and_security_records_are_encrypted_and_transparently_decrypted(): void
    {
        $privacy = PrivacyRequest::create([
            'requestor_name' => 'Synthetic Requestor',
            'requestor_email' => 'synthetic@example.test',
            'request_type' => PrivacyRequest::TYPE_ACCESS,
            'details' => 'Synthetic personal-data request details.',
            'resolution_notes' => 'Synthetic restricted resolution.',
            'export_payload' => ['profile' => ['department' => 'Testing']],
            'package_manifest' => ['files' => ['profile.json']],
            'exclusions_summary' => [['category' => 'third-party data']],
        ]);
        $incident = SecurityIncident::create([
            'title' => 'Synthetic encryption test',
            'category' => SecurityIncident::CATEGORY_DATA_LEAKAGE_RISK,
            'severity' => SecurityIncident::SEVERITY_HIGH,
            'description' => 'Synthetic restricted incident description.',
            'affected_system_or_data' => 'Synthetic personnel records',
            'breach_assessment' => 'Synthetic assessment.',
            'containment_actions' => 'Synthetic containment.',
            'remediation_notes' => 'Synthetic remediation.',
            'metadata' => ['source' => 'automated-test'],
        ]);

        $rawPrivacy = DB::table('privacy_requests')->where('id', $privacy->id)->first();
        $rawIncident = DB::table('security_incidents')->where('id', $incident->id)->first();

        $this->assertNotSame('Synthetic Requestor', $rawPrivacy->requestor_name);
        $this->assertNotSame('synthetic@example.test', $rawPrivacy->requestor_email);
        $this->assertNotSame('Synthetic personal-data request details.', $rawPrivacy->details);
        $this->assertNotSame('Synthetic restricted incident description.', $rawIncident->description);
        $this->assertNotSame('Synthetic assessment.', $rawIncident->breach_assessment);

        $this->assertSame('Synthetic Requestor', $privacy->fresh()->requestor_name);
        $this->assertSame('synthetic@example.test', $privacy->fresh()->requestor_email);
        $this->assertSame(['profile' => ['department' => 'Testing']], $privacy->fresh()->export_payload);
        $this->assertSame('Synthetic restricted incident description.', $incident->fresh()->description);
        $this->assertSame(['source' => 'automated-test'], $incident->fresh()->metadata);
    }

    public function test_sensitive_field_backfill_encrypts_legacy_values_without_double_encryption(): void
    {
        $user = User::factory()->create();
        $privacy = PrivacyRequest::create([
            'requestor_name' => 'Encrypted Initially',
            'requestor_email' => 'initial@example.test',
            'request_type' => PrivacyRequest::TYPE_ACCESS,
            'details' => 'Encrypted initially.',
        ]);
        $incident = SecurityIncident::create([
            'title' => 'Legacy row',
            'category' => SecurityIncident::CATEGORY_DATA_LEAKAGE_RISK,
            'severity' => SecurityIncident::SEVERITY_HIGH,
            'description' => 'Encrypted initially.',
            'affected_system_or_data' => 'Encrypted initially.',
        ]);

        DB::table('users')->where('id', $user->id)->update(['phone' => '09175550123', 'phone_blind_index' => null]);
        DB::table('privacy_requests')->where('id', $privacy->id)->update([
            'requestor_name' => 'Legacy Requestor',
            'requestor_email' => 'legacy@example.test',
            'details' => 'Legacy request details.',
            'export_payload' => json_encode(['legacy' => true], JSON_THROW_ON_ERROR),
        ]);
        DB::table('security_incidents')->where('id', $incident->id)->update([
            'description' => 'Legacy incident details.',
            'affected_system_or_data' => 'Legacy affected records.',
            'metadata' => json_encode(['legacy' => true], JSON_THROW_ON_ERROR),
        ]);

        $migration = require database_path('migrations/2026_09_27_000004_encrypt_sensitive_fields.php');
        $migration->up();

        $firstCiphertext = DB::table('privacy_requests')->where('id', $privacy->id)->value('details');
        $this->assertSame('09175550123', $user->fresh()->phone);
        $this->assertTrue(User::query()->wherePhoneNumber('09175550123')->whereKey($user->id)->exists());
        $this->assertSame('Legacy request details.', $privacy->fresh()->details);
        $this->assertSame(['legacy' => true], $privacy->fresh()->export_payload);
        $this->assertSame('Legacy incident details.', $incident->fresh()->description);
        $this->assertSame(['legacy' => true], $incident->fresh()->metadata);

        $migration->up();

        $this->assertSame(
            $firstCiphertext,
            DB::table('privacy_requests')->where('id', $privacy->id)->value('details'),
        );
    }

    public function test_legacy_plaintext_sms_mfa_phone_backfill_is_idempotent(): void
    {
        $user = User::factory()->create();
        $phone = '09171234567';

        DB::table('users')->where('id', $user->id)->update([
            'sms_mfa_phone' => $phone,
        ]);

        $migration = require database_path('migrations/2026_09_27_000002_encrypt_sms_mfa_phone.php');
        $migration->up();

        $ciphertext = DB::table('users')->where('id', $user->id)->value('sms_mfa_phone');
        $this->assertNotSame($phone, $ciphertext);
        $this->assertSame($phone, Crypt::decryptString($ciphertext));
        $this->assertSame($phone, $user->fresh()->sms_mfa_phone);

        $migration->up();

        $this->assertSame(
            $ciphertext,
            DB::table('users')->where('id', $user->id)->value('sms_mfa_phone'),
        );
    }

    public function test_backfill_rejects_unrecognized_values_without_modifying_them(): void
    {
        $user = User::factory()->create();
        $invalidValue = 'unrecognized-value';

        DB::table('users')->where('id', $user->id)->update([
            'sms_mfa_phone' => $invalidValue,
        ]);

        $migration = require database_path('migrations/2026_09_27_000002_encrypt_sms_mfa_phone.php');

        try {
            $migration->up();
            $this->fail('The migration accepted an unsafe legacy value.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString((string) $user->id, $exception->getMessage());
            $this->assertStringNotContainsString($invalidValue, $exception->getMessage());
        }

        $this->assertSame(
            $invalidValue,
            DB::table('users')->where('id', $user->id)->value('sms_mfa_phone'),
        );
    }
}
