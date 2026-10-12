-- Permanently purge SUP-30002 and its linked supplier portal account.
-- Target: J & M General Trading / J&M Trading.
-- Engine: MySQL 8 / TiDB 8.5.
--
-- Verified before this script was prepared:
--   1 linked user, 1 supplier document, 1 accreditation, 1 invitation,
--   1 activation challenge, 1 active-session row, 1 consent,
--   1 password-history row, and 8 actor-linked audit rows.
-- No procurement, inventory, receipt, shipment, quote, invoice, or product
-- rows were linked to supplier 30002 at inspection time.
--
-- WARNING: This is irreversible after COMMIT. It intentionally purges matching
-- audit rows because the requested removal includes every database copy of the
-- linked email address. Back up the database before execution.
--
-- SQL cannot delete files from Laravel storage. If the preflight result shows
-- a document path or logo path, remove those physical files separately after
-- this transaction commits successfully.

START TRANSACTION;

SET @requested_supplier_id := 30002;
SET @target_legal_name := 'J & M General Trading';
SET @target_trade_name := 'J&M Trading';

-- Resolve the target only when all three identity checks match. Every mutation
-- below uses this verified ID, so a mismatch turns the script into a no-op.
SET @target_supplier_id := (
    SELECT `id`
    FROM `suppliers`
    WHERE `id` = @requested_supplier_id
      AND BINARY `name` = BINARY @target_legal_name
      AND BINARY `trade_name` = BINARY @target_trade_name
    LIMIT 1
);

-- The live inspection found exactly one linked portal user.
SET @target_user_id := (
    SELECT `id`
    FROM `users`
    WHERE `supplier_id` = @target_supplier_id
    LIMIT 1
);

SET @target_email := (
    SELECT `email`
    FROM `users`
    WHERE `id` = @target_user_id
    LIMIT 1
);

-- Lock the exact supplier and linked account while the purge is running.
SELECT `id`, `name`, `trade_name`, `status`, `accreditation_status`, `logo_path`
FROM `suppliers`
WHERE `id` = @target_supplier_id
FOR UPDATE;

SELECT `id`, `employee_id`, `role`, `status`
FROM `users`
WHERE `supplier_id` = @target_supplier_id
FOR UPDATE;

-- Capture storage locations before their database rows are removed.
SELECT `disk`, `path`, `original_name`
FROM `supplier_documents`
WHERE `supplier_id` = @target_supplier_id;

-- Exceptional purge of audit history associated with the supplier, linked
-- account, supplier name, or linked email. This intentionally bypasses the
-- application's normal append-only retention policy.
DELETE `logs`
FROM `audit_logs` AS `logs`
WHERE `logs`.`user_id` = @target_user_id
   OR (
       `logs`.`target_type` = 'App\\Models\\Supplier'
       AND `logs`.`target_id` = CAST(@target_supplier_id AS CHAR)
   )
   OR (
       `logs`.`target_type` = 'App\\Models\\User'
       AND `logs`.`target_id` = CAST(@target_user_id AS CHAR)
   )
   OR `logs`.`target_name` = @target_legal_name
   OR `logs`.`description` LIKE CONCAT('%', @target_legal_name, '%')
   OR CAST(`logs`.`old_values` AS CHAR) LIKE CONCAT('%', @target_email, '%')
   OR CAST(`logs`.`new_values` AS CHAR) LIKE CONCAT('%', @target_email, '%');

SET @deleted_audit_rows := ROW_COUNT();

DELETE `logs`
FROM `procurement_audit_logs` AS `logs`
WHERE `logs`.`user_id` = @target_user_id
   OR (
       LOWER(`logs`.`entity_name`) IN ('supplier', 'suppliers', 'app\\models\\supplier')
       AND `logs`.`entity_id` = @target_supplier_id
   )
   OR CAST(`logs`.`old_values` AS CHAR) LIKE CONCAT('%', @target_email, '%')
   OR CAST(`logs`.`new_values` AS CHAR) LIKE CONCAT('%', @target_email, '%');

SET @deleted_procurement_audit_rows := ROW_COUNT();

-- Remove non-FK authentication records that can retain the account or email.
DELETE `tokens`
FROM `password_reset_tokens` AS `tokens`
WHERE BINARY `tokens`.`email` = BINARY @target_email;

DELETE FROM `sessions` WHERE `user_id` = @target_user_id;
DELETE FROM `password_histories` WHERE `user_id` = @target_user_id;

-- Remove account-owned rows explicitly. Some would cascade from users, but the
-- explicit order makes the intended full purge reviewable.
DELETE FROM `account_activation_challenges` WHERE `user_id` = @target_user_id;
DELETE FROM `device_login_cooldowns` WHERE `user_id` = @target_user_id;
DELETE FROM `login_approval_requests` WHERE `user_id` = @target_user_id;
DELETE FROM `user_active_sessions` WHERE `user_id` = @target_user_id;
DELETE FROM `trusted_devices` WHERE `user_id` = @target_user_id;
DELETE FROM `user_consents` WHERE `user_id` = @target_user_id;
DELETE FROM `user_avatars` WHERE `user_id` = @target_user_id;
DELETE FROM `ai_chat_conversations` WHERE `user_id` = @target_user_id;

-- Remove supplier-owned onboarding/profile records from child to parent.
DELETE FROM `supplier_compliance_alerts`
WHERE `supplier_id` = @target_supplier_id;

DELETE `prices`
FROM `supplier_prices` AS `prices`
LEFT JOIN `supplier_products` AS `products`
    ON `products`.`id` = `prices`.`supplier_product_id`
LEFT JOIN `supplier_contracts` AS `contracts`
    ON `contracts`.`id` = `prices`.`supplier_contract_id`
WHERE `products`.`supplier_id` = @target_supplier_id
   OR `contracts`.`supplier_id` = @target_supplier_id;

DELETE FROM `supplier_invitations` WHERE `supplier_id` = @target_supplier_id;
SET @deleted_invitations := ROW_COUNT();

DELETE FROM `supplier_accreditations` WHERE `supplier_id` = @target_supplier_id;
SET @deleted_accreditations := ROW_COUNT();

DELETE FROM `supplier_documents` WHERE `supplier_id` = @target_supplier_id;
SET @deleted_documents := ROW_COUNT();

DELETE FROM `supplier_contacts` WHERE `supplier_id` = @target_supplier_id;
DELETE FROM `supplier_products` WHERE `supplier_id` = @target_supplier_id;
DELETE FROM `supplier_contracts` WHERE `supplier_id` = @target_supplier_id;

-- Remove the linked portal account, including its Gmail address.
DELETE FROM `users`
WHERE `id` = @target_user_id
  AND `supplier_id` = @target_supplier_id;

SET @deleted_users := ROW_COUNT();

-- The database will reject this DELETE if an unexpected operational/history
-- FK was added after inspection, preventing an unsafe orphaned relationship.
DELETE FROM `suppliers`
WHERE `id` = @target_supplier_id
  AND BINARY `name` = BINARY @target_legal_name
  AND BINARY `trade_name` = BINARY @target_trade_name;

SET @deleted_suppliers := ROW_COUNT();

-- Expected result: deleted_suppliers = 1, deleted_users = 1, and all remaining
-- counts = 0. A SQL error before COMMIT must be followed by ROLLBACK.
SELECT
    @deleted_suppliers AS `deleted_suppliers`,
    @deleted_users AS `deleted_users`,
    @deleted_documents AS `deleted_documents`,
    @deleted_accreditations AS `deleted_accreditations`,
    @deleted_invitations AS `deleted_invitations`,
    @deleted_audit_rows AS `deleted_audit_rows`,
    @deleted_procurement_audit_rows AS `deleted_procurement_audit_rows`,
    (SELECT COUNT(*) FROM `suppliers` WHERE `id` = @requested_supplier_id) AS `remaining_supplier_rows`,
    (SELECT COUNT(*) FROM `users` WHERE BINARY `email` = BINARY @target_email) AS `remaining_user_email_rows`,
    (SELECT COUNT(*) FROM `supplier_invitations` WHERE BINARY `email` = BINARY @target_email) AS `remaining_invitation_email_rows`,
    (SELECT COUNT(*) FROM `password_reset_tokens` WHERE BINARY `email` = BINARY @target_email) AS `remaining_reset_email_rows`,
    (
        SELECT COUNT(*)
        FROM `audit_logs`
        WHERE CAST(`old_values` AS CHAR) LIKE CONCAT('%', @target_email, '%')
           OR CAST(`new_values` AS CHAR) LIKE CONCAT('%', @target_email, '%')
    ) AS `remaining_audit_email_rows`;

COMMIT;
