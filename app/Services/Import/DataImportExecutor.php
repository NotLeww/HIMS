<?php

namespace App\Services\Import;

use App\Enums\AuditAction;
use App\Models\InventoryItem;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DataImportExecutor
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Execute transactional import of validated records.
     *
     * @param  string  $target  'items' | 'locations' | 'suppliers'
     * @param  array<int, array<string, mixed>>  $records
     * @return array{created: int, updated: int, total: int}
     */
    public function execute(
        string $target,
        array $records,
        User $user,
        ?callable $progress = null,
        ?string $correlationId = null,
    ): array {
        return DB::transaction(function () use ($target, $records, $user, $progress, $correlationId) {
            $result = ['created' => 0, 'updated' => 0, 'total' => 0];
            $chunkSize = max(1, (int) config('imports.chunk_size', 250));
            $recordCount = count($records);
            $auditRows = $recordCount < (int) config('imports.background_threshold', 1_000);

            for ($offset = 0; $offset < $recordCount; $offset += $chunkSize) {
                $chunk = array_slice($records, $offset, $chunkSize);
                $chunkResult = match ($target) {
                    'items' => $this->executeItems($chunk, $user, $auditRows),
                    'locations' => $this->executeLocations($chunk, $user, $auditRows),
                    'suppliers' => $this->executeSuppliers($chunk, $user, $auditRows),
                    default => throw new \InvalidArgumentException("Unsupported import target [{$target}]."),
                };

                foreach ($result as $key => $value) {
                    $result[$key] = $value + $chunkResult[$key];
                }

                if ($progress !== null) {
                    $progress($result['total'], $recordCount);
                }
                unset($chunk);
            }

            $this->auditLogger->log(
                action: AuditAction::BulkImportCompleted,
                actor: $user,
                description: "Completed {$target} import: {$result['total']} records processed",
                newValues: [...$result, 'target' => $target],
                module: 'Imports',
                correlationId: $correlationId,
            );

            return $result;
        });
    }

    /**
     * Import Inventory Items.
     */
    protected function executeItems(array $records, User $user, bool $auditRows): array
    {
        $bulkCreates = [];
        if (! $auditRows) {
            foreach ($records as $key => $data) {
                if (($data['_mode'] ?? 'create') !== 'update') {
                    unset($data['_mode'], $data['_existing_id']);
                    $bulkCreates[] = [...$data, 'quantity_on_hand' => 0];
                    unset($records[$key]);
                }
            }
        }

        $created = $this->insertModels(InventoryItem::class, $bulkCreates);
        $updated = 0;
        $existing = InventoryItem::query()
            ->whereKey(array_filter(array_column($records, '_existing_id')))
            ->get()
            ->keyBy('id');

        foreach ($records as $data) {
            $mode = $data['_mode'] ?? 'create';
            $existingId = $data['_existing_id'] ?? null;
            unset($data['_mode'], $data['_existing_id']);

            if ($mode === 'update' && $existingId) {
                $item = $existing->get($existingId) ?? InventoryItem::findOrFail($existingId);
                if (($data['unit'] ?? null) === null) {
                    unset($data['unit']);
                }
                $oldValues = $item->only(array_keys($data));
                $item->update($data);
                $updated++;

                if ($auditRows) {
                    $this->auditLogger->log(
                        action: AuditAction::UpdatedInventoryItem,
                        actor: $user,
                        description: "Updated item {$item->sku} ({$item->name}) via data import",
                        target: $item,
                        targetName: $item->name,
                        oldValues: $oldValues,
                        newValues: $data,
                        module: 'Inventory'
                    );
                }
            } else {
                // Ensure starting quantity is 0; actual stock balances are managed by ItemStockLevel rows
                $data['quantity_on_hand'] = 0;
                $item = InventoryItem::create($data);
                $created++;

                $this->auditLogger->log(
                    action: AuditAction::CreatedInventoryItem,
                    actor: $user,
                    description: "Imported new inventory item {$item->sku} ({$item->name})",
                    target: $item,
                    targetName: $item->name,
                    newValues: $data,
                    module: 'Inventory'
                );
            }
        }

        return ['created' => $created, 'updated' => $updated, 'total' => $created + $updated];
    }

    /**
     * Import Storage Locations.
     */
    protected function executeLocations(array $records, User $user, bool $auditRows): array
    {
        $bulkCreates = [];
        if (! $auditRows) {
            foreach ($records as $key => $data) {
                if (($data['_mode'] ?? 'create') !== 'update') {
                    unset($data['_mode'], $data['_existing_id']);
                    $bulkCreates[] = $data;
                    unset($records[$key]);
                }
            }
        }

        $created = $this->insertModels(StorageLocation::class, $bulkCreates);
        $updated = 0;
        $existing = StorageLocation::query()
            ->whereKey(array_filter(array_column($records, '_existing_id')))
            ->get()
            ->keyBy('id');

        foreach ($records as $data) {
            $mode = $data['_mode'] ?? 'create';
            $existingId = $data['_existing_id'] ?? null;
            unset($data['_mode'], $data['_existing_id']);

            if ($mode === 'update' && $existingId) {
                $location = $existing->get($existingId) ?? StorageLocation::findOrFail($existingId);
                $oldValues = $location->only(array_keys($data));
                $location->update($data);
                $updated++;

                if ($auditRows) {
                    $this->auditLogger->log(
                        action: AuditAction::UpdatedStorageLocationStatus,
                        actor: $user,
                        description: "Updated storage location {$location->code} ({$location->name}) via data import",
                        target: $location,
                        targetName: $location->name,
                        oldValues: $oldValues,
                        newValues: $data,
                        module: 'Warehousing'
                    );
                }
            } else {
                $location = StorageLocation::create($data);
                $created++;

                $this->auditLogger->log(
                    action: AuditAction::CreatedStorageLocation,
                    actor: $user,
                    description: "Imported new storage location {$location->code} ({$location->name})",
                    target: $location,
                    targetName: $location->name,
                    newValues: $data,
                    module: 'Warehousing'
                );
            }
        }

        return ['created' => $created, 'updated' => $updated, 'total' => $created + $updated];
    }

    /**
     * Import Suppliers.
     */
    protected function executeSuppliers(array $records, User $user, bool $auditRows): array
    {
        $bulkCreates = [];
        if (! $auditRows) {
            foreach ($records as $key => $data) {
                if (($data['_mode'] ?? 'create') !== 'update') {
                    unset($data['_mode'], $data['_existing_id']);
                    $bulkCreates[] = $data;
                    unset($records[$key]);
                }
            }
        }

        $created = $this->insertModels(Supplier::class, $bulkCreates);
        $updated = 0;
        $existing = Supplier::query()
            ->whereKey(array_filter(array_column($records, '_existing_id')))
            ->get()
            ->keyBy('id');

        foreach ($records as $data) {
            $mode = $data['_mode'] ?? 'create';
            $existingId = $data['_existing_id'] ?? null;
            unset($data['_mode'], $data['_existing_id']);

            if ($mode === 'update' && $existingId) {
                $supplier = $existing->get($existingId) ?? Supplier::findOrFail($existingId);
                $oldValues = $supplier->only(array_keys($data));
                $supplier->update($data);
                $updated++;

                if ($auditRows) {
                    $this->auditLogger->log(
                        action: AuditAction::UpdatedSupplier,
                        actor: $user,
                        description: "Updated supplier {$supplier->name} via data import",
                        target: $supplier,
                        targetName: $supplier->name,
                        oldValues: $oldValues,
                        newValues: $data,
                        module: 'Procurement'
                    );
                }
            } else {
                $supplier = Supplier::create($data);
                $created++;

                $this->auditLogger->log(
                    action: AuditAction::CreatedSupplier,
                    actor: $user,
                    description: "Imported new supplier {$supplier->name}",
                    target: $supplier,
                    targetName: $supplier->name,
                    newValues: $data,
                    module: 'Procurement'
                );
            }
        }

        return ['created' => $created, 'updated' => $updated, 'total' => $created + $updated];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insertModels(string $modelClass, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = now();
        $payload = array_map(function (array $row) use ($modelClass, $now): array {
            $model = new $modelClass;
            $model->fill($row);
            $attributes = $model->getAttributes();
            if ($model->usesTimestamps()) {
                $attributes[$model->getCreatedAtColumn()] = $now;
                $attributes[$model->getUpdatedAtColumn()] = $now;
            }

            return $attributes;
        }, $rows);

        $modelClass::query()->insert($payload);

        return count($payload);
    }
}
