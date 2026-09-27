<?php

namespace App\Jobs;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Import\DataImportExecutor;
use App\Services\Import\ImportStagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class ProcessDataImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public readonly string $token,
        public readonly int $userId,
        public readonly string $target,
    ) {}

    public function handle(
        ImportStagingService $staging,
        DataImportExecutor $executor,
        AuditLogger $auditLogger,
    ): void {
        $staged = $staging->retrieve($this->token, $this->userId);
        if ($staged === null) {
            return;
        }

        $user = User::find($this->userId);
        $permission = match ($this->target) {
            'items' => Permission::ManageItems->value,
            'locations' => Permission::ManageLocations->value,
            'suppliers' => Permission::ManageSuppliers->value,
            default => null,
        };

        try {
            if ($user === null || $user->status !== UserStatus::Active || $permission === null || ! $user->can($permission)) {
                throw new RuntimeException('The import owner is no longer authorized to complete this import.');
            }

            $result = $executor->execute(
                $this->target,
                $staged['records'],
                $user,
                fn (int $processed) => $staging->progress($this->token, $this->userId, $processed),
                $this->token,
            );

            $staging->complete($this->token, $this->userId, $result);
            $staging->forget($this->token);

        } catch (Throwable $exception) {
            $staging->fail($this->token, $this->userId);

            if ($user !== null) {
                $auditLogger->log(
                    action: AuditAction::BulkImportFailed,
                    actor: $user,
                    description: "Failed {$this->target} bulk import; no records were committed",
                    newValues: ['target' => $this->target, 'total' => count($staged['records'])],
                    module: 'Imports',
                    outcome: 'failure',
                    correlationId: $this->token,
                );
            }

            throw $exception;
        }
    }
}
