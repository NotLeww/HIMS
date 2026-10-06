<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $usedIdentifiers = DB::table('users')
                ->whereNotNull('employee_id')
                ->pluck('employee_id')
                ->flip()
                ->all();

            $sequence = DB::table('employee_id_sequences')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw new RuntimeException('The account ID sequence has not been initialized.');
            }

            $nextValue = (int) $sequence->next_value;

            DB::table('users')
                ->whereNotNull('supplier_id')
                ->whereIn('role', ['vendor_administrator', 'vendor_operations', 'vendor_finance'])
                ->where('employee_id', 'like', 'EMP-%')
                ->orderBy('id')
                ->get(['id', 'employee_id'])
                ->each(function (object $user) use (&$nextValue, &$usedIdentifiers): void {
                    $candidate = preg_replace('/^EMP-/', 'SUP-', $user->employee_id);

                    while (isset($usedIdentifiers[$candidate])) {
                        $candidate = 'SUP-'.str_pad((string) $nextValue, 4, '0', STR_PAD_LEFT);
                        $nextValue++;
                    }

                    DB::table('users')->where('id', $user->id)->update([
                        'employee_id' => $candidate,
                    ]);

                    unset($usedIdentifiers[$user->employee_id]);
                    $usedIdentifiers[$candidate] = true;
                });

            DB::table('employee_id_sequences')->where('id', 1)->update([
                'next_value' => $nextValue,
            ]);
        });
    }

    public function down(): void
    {
        // Intentionally irreversible: converting SUP identifiers back could
        // collide with employee accounts created after this migration.
    }
};
