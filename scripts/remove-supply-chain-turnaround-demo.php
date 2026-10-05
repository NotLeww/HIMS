<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$execute = in_array('--execute', $argv, true);
$references = [];

foreach (['Q3', 'Q4'] as $quarter) {
    foreach (range(1, 6) as $sequence) {
        $references[] = sprintf('REV-2026-%s-%02d', $quarter, $sequence);
    }
}

$targets = [
    'inspection_acceptance_reports' => ['column' => 'iar_number', 'values' => array_map(fn (string $reference): string => "IAR-{$reference}", $references)],
    'goods_receipt_notes' => ['column' => 'grn_number', 'values' => array_map(fn (string $reference): string => "GRN-{$reference}", $references)],
    'purchase_orders' => ['column' => 'po_number', 'values' => array_map(fn (string $reference): string => "PO-{$reference}", $references)],
    'sourcing_rfqs' => ['column' => 'rfq_number', 'values' => array_map(fn (string $reference): string => "RFQ-{$reference}", $references)],
    'purchase_requests' => ['column' => 'pr_number', 'values' => array_map(fn (string $reference): string => "PR-{$reference}", $references)],
];

$markers = [
    'inspection_acceptance_reports' => ['notes', 'Completed inspection and custodial acceptance evidence.'],
    'goods_receipt_notes' => ['notes', 'Completed receipt supporting lifecycle turnaround evidence.'],
    'purchase_orders' => ['notes', 'Persisted end-to-end evidence for supply chain turnaround reporting.'],
    'sourcing_rfqs' => ['description', 'Awarded sourcing event retained as process review evidence.'],
    'purchase_requests' => ['description', 'Persisted workflow evidence for process turnaround analytics.'],
];

$orphanedCostCenters = fn () => DB::table('cost_centers')
    ->where('code', 'CC-SCM-OPS')
    ->where('name', 'Supply Chain Operations')
    ->where('department', 'Supply Chain Management')
    ->whereNotExists(fn ($query) => $query
        ->selectRaw('1')->from('purchase_orders')
        ->whereColumn('purchase_orders.cost_center_id', 'cost_centers.id'))
    ->whereNotExists(fn ($query) => $query
        ->selectRaw('1')->from('cost_center_budgets')
        ->whereColumn('cost_center_budgets.cost_center_id', 'cost_centers.id'))
    ->whereNotExists(fn ($query) => $query
        ->selectRaw('1')->from('purchase_requests')
        ->whereColumn('purchase_requests.cost_center_id', 'cost_centers.id'))
    ->whereNotExists(fn ($query) => $query
        ->selectRaw('1')->from('material_requisitions')
        ->whereColumn('material_requisitions.cost_center_id', 'cost_centers.id'));

$orphanedCategories = fn () => DB::table('procurement_categories')
    ->where('code', 'SCM-OPS')
    ->where('name', 'Supply Chain Review Evidence')
    ->where('description', 'Completed supply-chain transactions used for process velocity analytics.')
    ->whereNotExists(fn ($query) => $query
        ->selectRaw('1')->from('purchase_requests')
        ->whereColumn('purchase_requests.procurement_category_id', 'procurement_categories.id'))
    ->whereNotExists(fn ($query) => $query
        ->selectRaw('1')->from('procurement_categories as child_categories')
        ->whereColumn('child_categories.parent_id', 'procurement_categories.id'));

$counts = [];

foreach ($targets as $table => $target) {
    $rows = DB::table($table)
        ->whereIn($target['column'], $target['values'])
        ->get([$target['column'], $markers[$table][0]]);

    foreach ($rows as $row) {
        $markerColumn = $markers[$table][0];
        if ($row->{$markerColumn} !== $markers[$table][1]) {
            fwrite(STDERR, "Refusing cleanup: {$table} contains a target identifier without the expected demo marker.\n");
            exit(1);
        }
    }

    $counts[$table] = $rows->count();
}

$counts['orphaned_cost_centers'] = $orphanedCostCenters()->count();
$counts['orphaned_procurement_categories'] = $orphanedCategories()->count();

echo ($execute ? 'Cleanup target' : 'Dry-run target')." counts:\n";
foreach ($counts as $table => $count) {
    echo "  {$table}: {$count}\n";
}

if (! $execute) {
    echo "No records changed. Re-run with --execute to remove only the verified demo chains.\n";
    exit(0);
}

DB::transaction(function () use ($targets, $orphanedCostCenters, $orphanedCategories): void {
    foreach ($targets as $table => $target) {
        DB::table($table)->whereIn($target['column'], $target['values'])->delete();
    }

    $orphanedCostCenters()->delete();
    $orphanedCategories()->delete();
});

echo "Verified supply-chain turnaround demo chains removed.\n";
