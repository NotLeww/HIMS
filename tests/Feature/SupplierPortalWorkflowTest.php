<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteLine;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
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

        $this->get(route('inventory.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Resend Activation')
            ->assertSee('data-confirm-title="Resend activation email?"', false)
            ->assertSee(route('admin.users.edit', $invitedUser), false);

        $this->post(route('admin.users.verification.send', $invitedUser), [
            'return_to_supplier' => '1',
        ])->assertRedirect(route('inventory.suppliers.show', $supplier).'#supplier-portal-access')
            ->assertSessionHas('success', 'A new activation email was sent to ana@supplier.test.');

        Notification::assertSentToTimes($invitedUser, AccountCreated::class, 2);
    }

    public function test_supplier_workflow_is_persisted_and_tenant_isolated(): void
    {
        [$supplierA, $operationsA] = $this->supplierWithUser('Supplier A', UserRole::VendorOperations);
        [$supplierB, $operationsB] = $this->supplierWithUser('Supplier B', UserRole::VendorOperations);
        $item = InventoryItem::create(['name' => 'Sterile Gauze', 'sku' => 'GAUZE-PORTAL', 'unit' => 'box', 'status' => 'active']);
        $po = PurchaseOrder::create(['po_number' => 'PO-PORTAL-001', 'supplier_id' => $supplierA->id, 'quantity' => 10, 'unit_cost' => 100, 'total_amount' => 1000, 'status' => PurchaseOrderStatus::Approved, 'requested_at' => now()]);
        $line = PurchaseOrderLine::create(['purchase_order_id' => $po->id, 'item_id' => $item->id, 'line_number' => 1, 'ordered_quantity' => 10, 'unit_price' => 100, 'total_line_amount' => 1000]);

        $this->actingAs($operationsA)->get(route('supplier.dashboard'))->assertOk()->assertSee('PO-PORTAL-001');
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
        $supplier = Supplier::create(['name' => $name, 'business_structure' => 'corporation', 'address' => 'Manila', 'email' => str($name)->slug()."@example.test", 'status' => SupplierStatus::Active, 'accreditation_status' => SupplierAccreditationStatus::Approved]);
        $user = User::factory()->role($role)->create(['supplier_id' => $supplier->id, 'department' => 'External Supplier']);
        return [$supplier, $user];
    }
}
