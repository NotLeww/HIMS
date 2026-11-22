<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\InventoryItem;
use App\Models\MaterialRequisition;
use App\Models\MaterialRequisitionLine;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class FleetIntegrationStagingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('staging')) {
            throw new LogicException('Fleet sample deliveries may only be seeded in the staging environment.');
        }

        DB::transaction(function (): void {
            $requester = User::query()->firstOrCreate(
                ['email' => 'fleet-test-contact@example.invalid'],
                User::factory()->make([
                    'name' => 'Synthetic Fleet Test Contact',
                    'role' => UserRole::Viewer,
                    'status' => UserStatus::Active,
                    'department' => 'Supply Chain Staging',
                    'employee_id' => 'STG-FLEET-001',
                    'phone' => '0000000000',
                ])->getAttributes(),
            );

            $warehouse = StorageLocation::query()->firstOrCreate(
                ['code' => 'STG-FLEET-WH'],
                ['name' => 'Synthetic Staging Warehouse', 'type' => 'warehouse', 'status' => 'active'],
            );
            $dispatchArea = StorageLocation::query()->firstOrCreate(
                ['code' => 'STG-FLEET-DISPATCH'],
                ['name' => 'Synthetic Dispatch Area', 'parent_id' => $warehouse->id, 'type' => 'zone', 'status' => 'active'],
            );
            $pickupLocation = StorageLocation::query()->firstOrCreate(
                ['code' => 'STG-FLEET-PICKUP'],
                ['name' => 'Synthetic Pickup Shelf', 'parent_id' => $dispatchArea->id, 'type' => 'bin', 'status' => 'active'],
            );

            $item = InventoryItem::query()->firstOrCreate(
                ['sku' => 'STG-FLEET-SUPPLY-001'],
                [
                    'name' => 'Synthetic Medical Supply for Fleet Testing',
                    'unit' => 'piece',
                    'quantity_on_hand' => 100,
                    'status' => 'active',
                ],
            );

            foreach ([
                ['number' => 1, 'department' => 'Emergency', 'quantity' => 12, 'acknowledged' => false],
                ['number' => 2, 'department' => 'Radiology', 'quantity' => 6, 'acknowledged' => false],
                ['number' => 3, 'department' => 'Laboratory', 'quantity' => 9, 'acknowledged' => false],
                ['number' => 4, 'department' => 'Pharmacy', 'quantity' => 4, 'acknowledged' => true],
            ] as $sample) {
                $deliveryId = sprintf('MR-STG-FLEET-%03d', $sample['number']);
                $issuedAt = now()->subMinutes($sample['number']);
                $acknowledgedAt = $sample['acknowledged'] ? now() : null;

                $requisition = MaterialRequisition::query()->updateOrCreate(
                    ['requisition_number' => $deliveryId],
                    [
                        'requesting_user_id' => $requester->id,
                        'department' => $sample['department'],
                        'required_date' => now()->addDays($sample['number'])->toDateString(),
                        'status' => $sample['acknowledged'] ? 'acknowledged' : 'issued',
                        'urgency' => $sample['number'] === 1 ? 'urgent' : 'routine',
                        'approved_by_id' => $requester->id,
                        'approved_at' => $issuedAt,
                        'issued_by_id' => $requester->id,
                        'issued_at' => $issuedAt,
                        'acknowledged_by_id' => $sample['acknowledged'] ? $requester->id : null,
                        'acknowledged_at' => $acknowledgedAt,
                    ],
                );

                MaterialRequisitionLine::query()->updateOrCreate(
                    [
                        'material_requisition_id' => $requisition->id,
                        'item_id' => $item->id,
                    ],
                    [
                        'requested_quantity' => $sample['quantity'],
                        'reserved_quantity' => $sample['quantity'],
                        'issued_quantity' => $sample['quantity'],
                        'allocation_strategy' => 'FIFO',
                        'storage_location_id' => $pickupLocation->id,
                        'line_status' => 'issued',
                    ],
                );
            }
        });
    }
}
