<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RfqBiddingType;
use App\Enums\RfqStatus;
use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteLine;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\RfqLineItem;
use App\Models\RfqSupplierInvitation;
use App\Models\SourcingRfq;
use App\Models\Supplier;
use App\Models\SupplierDiscrepancy;
use App\Models\User;
use App\Notifications\AccountCreated;
use App\Support\DemoPdfBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierPortalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_hospital_invitation_creates_a_pending_supplier_account(): void
    {
        Notification::fake();
        $admin = User::factory()->administrator()->create();
        $supplier = Supplier::create(['name' => 'Invited Supplier', 'business_structure' => 'corporation', 'address' => 'Manila', 'email' => 'company@supplier.test', 'status' => SupplierStatus::Active, 'accreditation_status' => SupplierAccreditationStatus::Approved]);

        $this->actingAs($admin, 'admin')->get(route('inventory.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('id="supplier-portal-access"', false)
            ->assertSee('Invite supplier user')
            ->assertSee("open-modal', 'invite-supplier-user", false)
            ->assertSee('id="supplier-invitation-form"', false)
            ->assertSee('No supplier portal users yet.')
            ->assertSee('data-confirm-title="Send supplier invitation?"', false)
            ->assertSee('data-confirm-label="Send Invitation"', false);

        $this->post(route('inventory.suppliers.portal-users.store', $supplier), [
            'first_name' => 'Ana', 'surname' => 'Vendor', 'email' => 'ana@supplier.test', 'role' => UserRole::VendorAdministrator->value,
        ])->assertRedirect(route('inventory.suppliers.show', $supplier).'#supplier-portal-access')
            ->assertSessionHas('success', 'Invitation created for ana@supplier.test. The activation email was submitted for delivery; ask the recipient to check their inbox and spam folder.');

        $this->assertDatabaseHas('users', [
            'supplier_id' => $supplier->id,
            'email' => 'ana@supplier.test',
            'role' => UserRole::VendorAdministrator->value,
            'status' => 'pending_activation',
            'email_verified_at' => null,
        ]);

        $invitedUser = User::where('email', 'ana@supplier.test')->firstOrFail();
        $this->assertStringStartsWith('SUP-', $invitedUser->employee_id);
        $this->assertSame('Supplier User ID', $invitedUser->accountIdentifierLabel());

        $this->get(route('admin.users.edit', $invitedUser))
            ->assertOk()
            ->assertSee('Edit Supplier User')
            ->assertSee('Supplier User ID')
            ->assertSee('Enter the supplier user&#039;s basic details.', false);

        $this->get(route('inventory.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Resend Activation')
            ->assertSee('Edit Invitation')
            ->assertSee('Manage supplier account')
            ->assertSee('Advanced settings')
            ->assertSee('data-confirm-title="Resend activation email?"', false)
            ->assertSee('data-confirm-title="Confirm supplier account changes"', false)
            ->assertSee('x-bind:disabled="!hasAccountChanges()"', false);

        $this->post(route('admin.users.verification.send', $invitedUser), [
            'return_to_supplier' => '1',
        ])->assertRedirect(route('inventory.suppliers.show', $supplier).'#supplier-portal-access')
            ->assertSessionHas('success', 'A new activation email was sent to ana@supplier.test.');

        Notification::assertSentToTimes($invitedUser, AccountCreated::class, 2);
    }

    public function test_existing_supplier_employee_identifiers_are_backfilled_without_changing_staff_ids(): void
    {
        $supplier = Supplier::create([
            'name' => 'Legacy Supplier',
            'business_structure' => 'corporation',
            'address' => 'Manila',
            'email' => 'legacy@supplier.test',
            'status' => SupplierStatus::Active,
            'accreditation_status' => SupplierAccreditationStatus::Approved,
        ]);
        $supplierUser = User::factory()->role(UserRole::VendorAdministrator)->create([
            'supplier_id' => $supplier->id,
            'employee_id' => 'EMP-0042',
            'department' => 'External Supplier',
        ]);
        $staff = User::factory()->warehouseStaff()->create(['employee_id' => 'EMP-0043']);

        $migration = require database_path('migrations/2026_10_07_000001_assign_supplier_user_identifiers.php');
        $migration->up();

        $this->assertSame('SUP-0042', $supplierUser->fresh()->employee_id);
        $this->assertSame('EMP-0043', $staff->fresh()->employee_id);
    }

    public function test_supplier_account_management_stays_supplier_scoped_and_preserves_security_controls(): void
    {
        [$supplierA, $portalUser] = $this->supplierWithUser('Managed Supplier', UserRole::VendorAdministrator);
        [$supplierB] = $this->supplierWithUser('Other Supplier', UserRole::VendorOperations);
        $admin = User::factory()->administrator()->create();
        $superAdmin = User::factory()->superAdministrator()->create();
        $inventoryManager = User::factory()->inventoryManager()->create();
        $route = route('inventory.suppliers.portal-users.update', [$supplierA, $portalUser]);
        $payload = [
            'portal_user_id' => $portalUser->id,
            'form_context' => 'supplier_account',
            'account_first_name' => 'Maria',
            'account_surname' => 'Vendor',
            'account_email' => 'maria.vendor@supplier.test',
            'account_role' => UserRole::VendorFinance->value,
            'account_status' => UserStatus::Inactive->value,
        ];

        $this->actingAs($inventoryManager)->patch($route, $payload)->assertForbidden();
        $this->actingAs($superAdmin)->patch($route, $payload)->assertSessionHasErrors('current_password');
        $this->assertSame(UserRole::VendorAdministrator, $portalUser->fresh()->role);

        $this->actingAs($admin, 'admin')->patch($route, $payload)
            ->assertRedirect(route('inventory.suppliers.show', $supplierA).'#supplier-portal-access')
            ->assertSessionHas('success', 'Maria Vendor\'s supplier portal account was updated.');

        $this->assertDatabaseHas('users', [
            'id' => $portalUser->id,
            'supplier_id' => $supplierA->id,
            'email' => 'maria.vendor@supplier.test',
            'role' => UserRole::VendorFinance->value,
            'status' => UserStatus::Inactive->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::UpdatedUser->value,
            'user_id' => $admin->id,
            'target_id' => (string) $portalUser->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('inventory.suppliers.portal-users.update', [$supplierB, $portalUser]), $payload)
            ->assertNotFound();
        $this->assertSame($supplierA->id, $portalUser->fresh()->supplier_id);
    }

    public function test_supplier_workflow_is_persisted_and_tenant_isolated(): void
    {
        [$supplierA, $operationsA] = $this->supplierWithUser('Supplier A', UserRole::VendorOperations);
        [$supplierB, $operationsB] = $this->supplierWithUser('Supplier B', UserRole::VendorOperations);
        $category = ItemCategory::create(['name' => 'Personal Protective Equipment', 'code' => 'PPE', 'is_active' => true]);
        $item = InventoryItem::create(['name' => 'Gloves M', 'sku' => 'GLOVE-PORTAL', 'unit' => 'box', 'status' => 'active', 'category_id' => $category->id]);
        $po = PurchaseOrder::create(['po_number' => 'PO-PORTAL-001', 'supplier_id' => $supplierA->id, 'quantity' => 10, 'unit_cost' => 100, 'total_amount' => 1000, 'status' => PurchaseOrderStatus::Approved, 'requested_at' => now()]);
        $line = PurchaseOrderLine::create(['purchase_order_id' => $po->id, 'item_id' => $item->id, 'line_number' => 1, 'ordered_quantity' => 10, 'unit_price' => 100, 'total_line_amount' => 1000]);

        $this->actingAs($operationsA)->get(route('supplier.dashboard'))
            ->assertOk()
            ->assertSee('PO-PORTAL-001')
            ->assertSee('class="hims-app-shell', false)
            ->assertSee('aria-label="Supplier portal navigation"', false)
            ->assertSee('Supplier workspace')
            ->assertSee('hims-supplier-dashboard-hero-light.png', false)
            ->assertSee('hims-supplier-dashboard-hero.png', false)
            ->assertDontSee('Procurement &amp; Sourcing', false)
            ->assertDontSee('Administration');
        $this->actingAs($operationsA)->get(route('supplier.orders.index'))
            ->assertOk()
            ->assertSee('Quick view')
            ->assertSee("supplier-order-quick-view-{$po->id}")
            ->assertSee('Open full order')
            ->assertSee('hims-supplier-po-items-day.png', false)
            ->assertSee('hims-supplier-po-items-night.png', false)
            ->assertSee('picklist-ppe.png', false)
            ->assertSee('Gloves M');
        $this->actingAs($operationsA)->get(route('supplier.orders.show', $po))
            ->assertOk()
            ->assertSee('PO-PORTAL-001')
            ->assertSee('Back to Purchase Orders')
            ->assertSee('Order date')
            ->assertSee('Scheduled delivery')
            ->assertSee('picklist-ppe.png', false)
            ->assertSee('Gloves M')
            ->assertSee('data-item-icon="hand-raised"', false);
        $item->update(['name' => 'Paracetamol 500 mg Tablet', 'sku' => 'MED-PARA-500']);
        $this->actingAs($operationsA)->get(route('supplier.orders.show', $po))
            ->assertOk()
            ->assertSee('Paracetamol 500 mg Tablet')
            ->assertSee('data-item-icon="capsule"', false);
        $deviceCategory = ItemCategory::create(['name' => 'Medical Devices', 'code' => 'DEVICE', 'is_active' => true]);
        $orthopedicItem = InventoryItem::create(['name' => 'Titanium Locking Reconstruction Plate 3.5mm', 'sku' => 'ORTHO-PLATE-35', 'unit' => 'piece', 'status' => 'active', 'category_id' => $deviceCategory->id]);
        $rfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-PORTAL-001',
            'title' => 'Orthopedic Reconstruction Plates',
            'description' => 'Competitive sealed tender for hospital implants.',
            'created_by_user_id' => $operationsA->id,
            'bidding_type' => RfqBiddingType::Sealed,
            'status' => RfqStatus::Published,
            'submission_deadline' => now()->addDay(),
            'currency' => 'PHP',
        ]);
        $rfqLine = RfqLineItem::create([
            'sourcing_rfq_id' => $rfq->id,
            'item_id' => $orthopedicItem->id,
            'line_number' => 1,
            'item_description' => 'Titanium Locking Reconstruction Plate 3.5mm',
            'target_quantity' => 50,
            'uom' => 'piece',
        ]);
        $invitation = RfqSupplierInvitation::create([
            'sourcing_rfq_id' => $rfq->id,
            'supplier_id' => $supplierA->id,
            'portal_token' => 'supplier-rfq-portal-token',
            'status' => 'invited',
            'invited_at' => now(),
        ]);
        $bidderA = User::factory()->role(UserRole::VendorAdministrator)->create([
            'supplier_id' => $supplierA->id,
            'department' => 'External Supplier',
        ]);
        $this->actingAs($operationsA)->get(route('supplier.rfqs.index'))
            ->assertOk()
            ->assertSee('RFQs &amp; Bids', false)
            ->assertSee('hims-supplier-rfq-hero-day.png', false)
            ->assertSee('hims-supplier-rfq-hero-night.png', false)
            ->assertSee('RFQ invitations')
            ->assertSee('1 invitation issued to your supplier.')
            ->assertSee('picklist-devices.png', false)
            ->assertSee('data-rfq-bid-card', false)
            ->assertDontSee('data-rfq-bid-form', false);
        $this->actingAs($bidderA)->get(route('supplier.rfqs.index'))
            ->assertOk()
            ->assertSee('Prepare bid')
            ->assertDontSee('View 1 line item')
            ->assertDontSee('rfq-items-'.$invitation->id, false)
            ->assertSee('rfq-bid-'.$invitation->id, false)
            ->assertSee('role="dialog"', false)
            ->assertSee('Prepare bid — '.$rfq->rfq_number)
            ->assertSee('data-rfq-bid-form', false)
            ->assertSee('novalidate', false)
            ->assertSee('x-bind:disabled="!canSubmit"', false)
            ->assertSee('Titanium Locking Reconstruction Plate 3.5mm')
            ->assertSee('value="50"', false)
            ->assertSee('Select payment terms')
            ->assertSee('Other / Custom terms')
            ->assertSee('Submit sealed bid');
        $this->actingAs($bidderA)->from(route('supplier.rfqs.index'))->post(route('supplier.rfqs.bid', $invitation), [
            '_invitation_id' => $invitation->id,
            'quote_number' => 'QUOTE-PORTAL-001',
            'payment_terms' => 'other',
            'lines' => [[
                'rfq_line_item_id' => $rfqLine->id,
                'offered_unit_price' => 2500,
                'offered_quantity' => 50,
                'lead_time_days' => 14,
            ]],
        ])->assertRedirect(route('supplier.rfqs.index'))->assertSessionHasErrors('payment_terms_custom');
        $this->get(route('supplier.rfqs.index'))
            ->assertOk()
            ->assertDontSee('Please correct the form')
            ->assertSee('rfq-bid-'.$invitation->id, false)
            ->assertSee('id="payment-terms-custom-'.$invitation->id.'-error"', false)
            ->assertSee('aria-invalid="true"', false);
        $this->actingAs($bidderA)->post(route('supplier.rfqs.bid', $invitation), [
            'quote_number' => 'QUOTE-PORTAL-001',
            'payment_terms' => 'other',
            'payment_terms_custom' => '40% advance, balance on delivery',
            'notes' => 'Pricing includes sterile packaging.',
            'lines' => [[
                'rfq_line_item_id' => $rfqLine->id,
                'offered_unit_price' => 2500,
                'offered_quantity' => 50,
                'lead_time_days' => 14,
            ]],
        ])->assertRedirect();
        $this->assertDatabaseHas('supplier_quotes', [
            'sourcing_rfq_id' => $rfq->id,
            'supplier_id' => $supplierA->id,
            'payment_terms' => '40% advance, balance on delivery',
        ]);
        $this->actingAs($operationsA)->get('/dashboard')->assertForbidden();
        $this->actingAs($operationsB)->get(route('supplier.orders.show', $po))->assertForbidden();

        $this->actingAs($operationsA)->post(route('supplier.orders.acknowledge', $po), ['response' => 'accepted'])->assertRedirect();
        $this->assertSame(PurchaseOrderStatus::Acknowledged->value, $po->refresh()->status);
        $this->assertDatabaseHas('purchase_order_acknowledgements', ['purchase_order_id' => $po->id, 'supplier_id' => $supplierA->id, 'response' => 'accepted']);

        $this->actingAs($operationsA)->post(route('supplier.orders.asns.store', $po), [
            'shipment_number' => 'ASN-PORTAL-001', 'carrier_name' => 'Internal Carrier', 'dispatch_date' => today()->toDateString(),
            'estimated_delivery_date' => today()->addDay()->toDateString(), 'sscc' => '123456789012345678',
            'lines' => [['po_line_id' => $line->id, 'quantity' => 8, 'lot_number' => 'LOT-A']],
        ])->assertRedirect();
        $this->assertDatabaseHas('shipment_line_items', ['po_line_id' => $line->id, 'quantity' => 8]);

        $receiver = User::factory()->warehouseStaff()->create();
        $grn = GoodsReceiptNote::create(['grn_number' => 'GRN-PORTAL-001', 'purchase_order_id' => $po->id, 'supplier_id' => $supplierA->id, 'received_by_id' => $receiver->id, 'receipt_status' => 'under_inspection', 'received_at' => now()]);
        $grnLine = GoodsReceiptNoteLine::create(['goods_receipt_note_id' => $grn->id, 'po_line_id' => $line->id, 'item_id' => $item->id, 'ordered_quantity' => 10, 'shipped_quantity' => 8, 'received_quantity' => 8, 'accepted_quantity' => 8, 'rejected_quantity' => 0, 'quarantined_quantity' => 0, 'unit_cost' => 100, 'status' => 'accepted', 'item_condition' => 'good', 'discrepancy_type' => 'shortage']);
        $discrepancy = SupplierDiscrepancy::create(['supplier_id' => $supplierA->id, 'grn_line_item_id' => $grnLine->id, 'status' => 'open']);

        $this->actingAs($operationsB)->post(route('supplier.discrepancies.respond', $discrepancy), ['supplier_response_type' => 'supplemental_delivery', 'supplier_response' => 'Sending two boxes.'])->assertForbidden();
        $this->actingAs($operationsA)->post(route('supplier.discrepancies.respond', $discrepancy), ['supplier_response_type' => 'supplemental_delivery', 'supplier_response' => 'Sending two boxes.'])->assertRedirect();
        $this->assertDatabaseHas('supplier_discrepancies', ['id' => $discrepancy->id, 'status' => 'supplier_responded', 'responded_by' => $operationsA->id]);

        $this->assertDatabaseMissing('purchase_order_acknowledgements', ['supplier_id' => $supplierB->id]);
    }

    public function test_finance_role_can_submit_three_way_match_but_cannot_fulfill_orders(): void
    {
        [$supplier, $finance] = $this->supplierWithUser('Finance Supplier', UserRole::VendorFinance);
        $item = InventoryItem::create(['name' => 'Syringe', 'sku' => 'SYR-PORTAL', 'unit' => 'box', 'status' => 'active']);
        $po = PurchaseOrder::create(['po_number' => 'PO-FIN-001', 'supplier_id' => $supplier->id, 'quantity' => 5, 'unit_cost' => 20, 'total_amount' => 100, 'status' => PurchaseOrderStatus::Fulfilled, 'requested_at' => now()]);
        $line = PurchaseOrderLine::create(['purchase_order_id' => $po->id, 'item_id' => $item->id, 'line_number' => 1, 'ordered_quantity' => 5, 'received_quantity' => 5, 'accepted_quantity' => 5, 'unit_price' => 20, 'total_line_amount' => 100]);

        $this->actingAs($finance)->get(route('supplier.invoices.index'))->assertOk();
        $this->actingAs($finance)->post(route('supplier.orders.acknowledge', $po), ['response' => 'accepted'])->assertForbidden();
        $this->actingAs($finance)->post(route('supplier.invoices.store'), ['purchase_order_id' => $po->id, 'invoice_number' => 'INV-001', 'invoice_date' => today()->toDateString(), 'lines' => [['po_line_id' => $line->id, 'quantity' => 5, 'unit_price' => 20]]])->assertRedirect();
        $this->assertDatabaseHas('supplier_invoices', ['supplier_id' => $supplier->id, 'invoice_number' => 'INV-001', 'status' => 'matched', 'total_amount' => 100]);
    }

    public function test_supplier_compliance_documents_are_private_and_await_hospital_review(): void
    {
        Storage::fake('local');
        [$supplierA, $administratorA] = $this->supplierWithUser('Compliance Supplier A', UserRole::VendorAdministrator);
        [, $administratorB] = $this->supplierWithUser('Compliance Supplier B', UserRole::VendorAdministrator);

        $this->actingAs($administratorA)->post(route('supplier.compliance.store'), [
            'document_type' => 'Business Permit',
            'document_number' => 'BP-001',
            'issued_at' => today()->subMonth()->toDateString(),
            'expires_at' => today()->addYear()->toDateString(),
            'file' => UploadedFile::fake()->createWithContent('permit.pdf', DemoPdfBuilder::create('Business Permit', [['heading' => 'PERMIT', 'lines' => ['Valid supplier evidence.']]])),
        ])->assertRedirect();

        $document = $supplierA->documents()->firstOrFail();
        $this->assertSame('pending', $document->verification_status->value);
        Storage::disk('local')->assertExists($document->path);
        $this->actingAs($administratorB)->get(route('supplier.compliance.download', $document))->assertForbidden();
        $this->actingAs($administratorA)->get(route('supplier.compliance.download', $document))->assertDownload('permit.pdf');
    }

    private function supplierWithUser(string $name, UserRole $role): array
    {
        $supplier = Supplier::create(['name' => $name, 'business_structure' => 'corporation', 'address' => 'Manila', 'email' => str($name)->slug().'@example.test', 'status' => SupplierStatus::Active, 'accreditation_status' => SupplierAccreditationStatus::Approved]);
        $user = User::factory()->role($role)->create(['supplier_id' => $supplier->id, 'department' => 'External Supplier']);

        return [$supplier, $user];
    }
}
