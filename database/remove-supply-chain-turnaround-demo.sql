-- SupplyChainTurnaroundDemoSeeder cleanup for MySQL/TiDB.
-- This changes data only; it does not alter the database schema.

START TRANSACTION;

DELETE FROM `inspection_acceptance_reports`
WHERE (`iar_number` LIKE 'IAR-REV-2026-Q3-%' OR `iar_number` LIKE 'IAR-REV-2026-Q4-%')
  AND `notes` = 'Completed inspection and custodial acceptance evidence.';

DELETE `lines`
FROM `grn_line_items` AS `lines`
INNER JOIN `goods_receipt_notes` AS `notes`
  ON `notes`.`id` = `lines`.`goods_receipt_note_id`
WHERE (`notes`.`grn_number` LIKE 'GRN-REV-2026-Q3-%' OR `notes`.`grn_number` LIKE 'GRN-REV-2026-Q4-%')
  AND `notes`.`notes` = 'Completed receipt supporting lifecycle turnaround evidence.';

DELETE FROM `goods_receipt_notes`
WHERE (`grn_number` LIKE 'GRN-REV-2026-Q3-%' OR `grn_number` LIKE 'GRN-REV-2026-Q4-%')
  AND `notes` = 'Completed receipt supporting lifecycle turnaround evidence.';

DELETE `lines`
FROM `po_line_items` AS `lines`
INNER JOIN `purchase_orders` AS `orders`
  ON `orders`.`id` = `lines`.`purchase_order_id`
WHERE (`orders`.`po_number` LIKE 'PO-REV-2026-Q3-%' OR `orders`.`po_number` LIKE 'PO-REV-2026-Q4-%')
  AND `orders`.`notes` = 'Persisted end-to-end evidence for supply chain turnaround reporting.';

DELETE FROM `purchase_orders`
WHERE (`po_number` LIKE 'PO-REV-2026-Q3-%' OR `po_number` LIKE 'PO-REV-2026-Q4-%')
  AND `notes` = 'Persisted end-to-end evidence for supply chain turnaround reporting.';

DELETE `lines`
FROM `rfq_line_items` AS `lines`
INNER JOIN `sourcing_rfqs` AS `rfqs`
  ON `rfqs`.`id` = `lines`.`sourcing_rfq_id`
WHERE (`rfqs`.`rfq_number` LIKE 'RFQ-REV-2026-Q3-%' OR `rfqs`.`rfq_number` LIKE 'RFQ-REV-2026-Q4-%')
  AND `rfqs`.`description` = 'Awarded sourcing event retained as process review evidence.';

DELETE FROM `sourcing_rfqs`
WHERE (`rfq_number` LIKE 'RFQ-REV-2026-Q3-%' OR `rfq_number` LIKE 'RFQ-REV-2026-Q4-%')
  AND `description` = 'Awarded sourcing event retained as process review evidence.';

DELETE `lines`
FROM `pr_line_items` AS `lines`
INNER JOIN `purchase_requests` AS `requests`
  ON `requests`.`id` = `lines`.`purchase_request_id`
WHERE (`requests`.`pr_number` LIKE 'PR-REV-2026-Q3-%' OR `requests`.`pr_number` LIKE 'PR-REV-2026-Q4-%')
  AND `requests`.`description` = 'Persisted workflow evidence for process turnaround analytics.';

DELETE FROM `purchase_requests`
WHERE (`pr_number` LIKE 'PR-REV-2026-Q3-%' OR `pr_number` LIKE 'PR-REV-2026-Q4-%')
  AND `description` = 'Persisted workflow evidence for process turnaround analytics.';

DELETE FROM `cost_centers`
WHERE `code` = 'CC-SCM-OPS'
  AND `name` = 'Supply Chain Operations'
  AND `department` = 'Supply Chain Management'
  AND NOT EXISTS (SELECT 1 FROM `purchase_orders` WHERE `purchase_orders`.`cost_center_id` = `cost_centers`.`id`)
  AND NOT EXISTS (SELECT 1 FROM `cost_center_budgets` WHERE `cost_center_budgets`.`cost_center_id` = `cost_centers`.`id`)
  AND NOT EXISTS (SELECT 1 FROM `purchase_requests` WHERE `purchase_requests`.`cost_center_id` = `cost_centers`.`id`)
  AND NOT EXISTS (SELECT 1 FROM `material_requisitions` WHERE `material_requisitions`.`cost_center_id` = `cost_centers`.`id`);

DELETE `categories`
FROM `procurement_categories` AS `categories`
LEFT JOIN `purchase_requests` AS `requests`
  ON `requests`.`procurement_category_id` = `categories`.`id`
LEFT JOIN `procurement_categories` AS `child_categories`
  ON `child_categories`.`parent_id` = `categories`.`id`
WHERE `categories`.`code` = 'SCM-OPS'
  AND `categories`.`name` = 'Supply Chain Review Evidence'
  AND `categories`.`description` = 'Completed supply-chain transactions used for process velocity analytics.'
  AND `requests`.`id` IS NULL
  AND `child_categories`.`id` IS NULL;

COMMIT;
