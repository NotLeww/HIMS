# Database Encryption Evidence

## Scope

HIMS encrypts security-sensitive values that the application must recover, while preserving database behavior for fields that must remain searchable, unique, indexed, or relational.

| Database field | Protection | Reason |
| --- | --- | --- |
| `users.authenticator_secret` | Laravel `Crypt` through `EncryptedAuthenticatorSecret` | Recoverable MFA seed; never searchable |
| `users.sms_mfa_phone` | Laravel Eloquent `encrypted` cast | MFA enrollment snapshot; hidden and never searched or indexed |
| `users.phone` | Laravel `Crypt` through `EncryptedPhone` plus keyed HMAC-SHA-256 blind index | Recoverable contact number with exact-match lookup support |
| `users.password` | Laravel `hashed` cast | Passwords require one-way hashing, not reversible encryption |
| DSR requestor identity, details, notes, exports, manifests, and exclusions | Laravel encrypted string/array casts | Confidential personal-data request content; never queried by plaintext |
| Security incident descriptions, affected data, assessments, actions, notes, and metadata | Laravel encrypted string/array casts | Restricted incident-response information; never queried by plaintext |
| OTPs, reset tokens, and trusted-device tokens | Existing one-way hashes | Verification does not require recovering the original secret |

Email, employee ID, supplier contacts/TIN, names, and foreign keys retain their current storage because the application performs partial `LIKE` searches, uniqueness checks, audit searches, or relational joins on them. A single blind index supports exact equality only; encrypting those fields without a separately designed token-index search would regress current behavior. The primary user phone is encrypted because its current lookup requirements can be fully preserved with an exact-match keyed blind index.

## Database configuration evidence

- `config/app.php` configures Laravel encryption with `AES-256-CBC`, using `APP_KEY` and optional `APP_PREVIOUS_KEYS` from the deployment environment.
- `app/Models/User.php` applies the framework `encrypted` cast to `sms_mfa_phone` and the existing `EncryptedAuthenticatorSecret` cast to `authenticator_secret`. Both values are also in the model's hidden attributes.
- `app/Casts/EncryptedPhone.php` encrypts `users.phone` and writes `phone_blind_index`; `app/Support/BlindIndex.php` creates a domain-separated HMAC-SHA-256 index using `DB_BLIND_INDEX_KEY`.
- `app/Models/PrivacyRequest.php` and `app/Models/SecurityIncident.php` apply encrypted string/array casts to confidential and restricted fields.
- `app/Casts/EncryptedAuthenticatorSecret.php` uses Laravel `Crypt::encryptString()` before persistence and `Crypt::decryptString()` when authorized application code reads the value.
- `database/migrations/2026_09_27_000002_encrypt_sms_mfa_phone.php` widens the MFA phone column for authenticated ciphertext and safely backfills legacy plaintext. It validates every non-null value before modification, recognizes already-encrypted rows, and is safe to rerun without double encryption.
- `tests/Feature/DatabaseEncryptionTest.php` verifies raw database values are ciphertext, authorized model reads return the original values, legacy plaintext is migrated once, hidden values are not serialized, and passwords remain one-way hashes.

No certificate, encryption key, or secret value is stored in these files. `APP_KEY` and any `APP_PREVIOUS_KEYS` must be supplied by the deployment secret manager and must not be committed.

## Deployment procedure

1. Back up the database and confirm the backup is restorable.
2. Confirm the production secret manager provides the existing stable `APP_KEY` and a separate random `DB_BLIND_INDEX_KEY` with at least 32 bytes of key material. Do not generate or replace either key during this deployment. If rotating `APP_KEY` separately, retain the old key in `APP_PREVIOUS_KEYS` until all old ciphertext has been re-encrypted. Rotating the blind-index key requires an explicit index rebuild.
3. Put the application in maintenance mode so no MFA enrollment write can race the backfill.
4. Deploy the model and migration together, then run `php artisan migrate --force`.
5. Clear/rebuild the production configuration cache using the project's normal deployment workflow, then bring the application out of maintenance mode.
6. Exercise SMS and authenticator MFA login/enrollment flows. Inspect only whether raw values differ from known test input; never copy production plaintext or ciphertext into screenshots or reports.

The migration deliberately stops before changing data if it encounters a non-null value that is neither a valid legacy Philippine mobile number nor ciphertext decryptable with the configured current/previous keys. Resolve that row and key configuration before retrying; do not bypass the check.

## Evidence capture

For the checklist attachment, capture or export these redacted items:

1. The `cipher`, `key`, and `previous_keys` entries in `config/app.php`, showing environment lookups but no environment values.
2. The `sms_mfa_phone` and `authenticator_secret` cast entries plus hidden attributes in `app/Models/User.php`.
3. The migration status lines for `2026_09_27_000002_encrypt_sms_mfa_phone`, `2026_09_27_000003_prepare_sensitive_field_encryption`, and `2026_09_27_000004_encrypt_sensitive_fields` after deployment.
4. The passing output of `php artisan test tests/Feature/DatabaseEncryptionTest.php`.
5. A database query result from a dedicated non-production test account showing the stored MFA fields do not equal the known test plaintext. Redact the ciphertext, account identifiers, and all other values; show only a boolean comparison such as `is_encrypted = 1`.

Together these items demonstrate AES-256 application-layer encryption before database storage, safe legacy-record handling, environment-managed keys, transparent authorized decryption, and non-reversible password storage.
