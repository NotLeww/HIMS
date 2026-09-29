<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\AuditAction;
use App\Enums\NotificationDestination;
use App\Enums\NotificationPriority;
use App\Enums\Permission;
use App\Enums\RecoveryFailureType;
use App\Enums\RecoveryRetryHandler;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessDataImport;
use App\Models\InventoryItem;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Services\FileContentValidator;
use App\Services\HimsNotificationService;
use App\Services\Import\DataImportExecutor;
use App\Services\Import\DataImportReader;
use App\Services\Import\DataImportValidator;
use App\Services\Import\ImportStagingService;
use App\Services\Import\ImportTemplateGenerator;
use App\Services\Recovery\SafeExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ImportController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly DataImportReader $reader,
        private readonly DataImportValidator $validator,
        private readonly DataImportExecutor $executor,
        private readonly ImportTemplateGenerator $templates,
        private readonly ImportStagingService $staging,
        private readonly HimsNotificationService $notifications,
        private readonly SafeExecutionService $recovery,
        private readonly FileContentValidator $fileContentValidator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
        ];
    }

    /**
     * Show the Import Data main management screen.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $canItems = $user->can(Permission::ManageItems->value);
        $canLocations = $user->can(Permission::ManageLocations->value);
        $canSuppliers = $user->can(Permission::ManageSuppliers->value);

        if (! $canItems && ! $canLocations && ! $canSuppliers) {
            abort(403, 'You do not have permission to import inventory or organizational records.');
        }

        return view('inventory.import.index', [
            'canItems' => $canItems,
            'canLocations' => $canLocations,
            'canSuppliers' => $canSuppliers,
            'totalItems' => InventoryItem::count(),
            'totalLocations' => StorageLocation::count(),
            'totalSuppliers' => Supplier::count(),
        ]);
    }

    /**
     * Download template files in CSV, JSON, or Excel-compatible (.xls) formats.
     */
    public function downloadTemplate(Request $request): StreamedResponse
    {
        $target = $request->input('target', 'items');
        if (! in_array($target, ['items', 'locations', 'suppliers'], true)) {
            $target = 'items';
        }

        $this->authorizeTarget($request->user(), $target);

        $format = strtolower($request->input('format', 'csv'));

        return match ($format) {
            'json' => $this->templates->downloadJson($target),
            'xls', 'xlsx', 'excel' => $this->templates->downloadXls($target),
            default => $this->templates->downloadCsv($target),
        };
    }

    /**
     * Upload, parse, and validate an import file without saving to database.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB max
            'target' => ['required', 'string', 'in:items,locations,suppliers'],
            'mode' => ['required', 'string', 'in:create_only,update_or_create'],
        ], [
            'file.required' => 'Please select a file to import.',
            'file.max' => 'The file size cannot exceed 10 MB.',
            'target.in' => 'Invalid target module selected.',
            'mode.in' => 'Invalid import mode selected.',
        ]);

        $user = $request->user();
        $target = $request->input('target');
        $mode = $request->input('mode');
        $file = $request->file('file');

        // Check target permission
        $this->authorizeTarget($user, $target);

        // Verify supported file extension
        $clientExt = strtolower($file->getClientOriginalExtension());
        if (! in_array($clientExt, ['csv', 'txt', 'json', 'xlsx', 'xls'], true)) {
            $extLabel = $clientExt !== '' ? "[{$clientExt}]" : '[no extension]';

            return response()->json([
                'is_valid' => false,
                'total_rows' => 0,
                'valid_count' => 0,
                'invalid_count' => 1,
                'create_count' => 0,
                'update_count' => 0,
                'message' => "Unsupported file format {$extLabel}. Please upload a CSV (.csv), Excel (.xlsx / .xls), or JSON (.json) file.",
                'errors' => [
                    [
                        'row' => 1,
                        'field' => 'file',
                        'value' => $file->getClientOriginalName(),
                        'type' => 'invalid_structure',
                        'message' => "Unsupported file format {$extLabel}. Allowed formats: CSV (.csv), Excel (.xlsx / .xls), JSON (.json).",
                    ],
                ],
                'preview_rows' => [],
            ], 422);
        }

        try {
            $this->fileContentValidator->validate($file, ['csv', 'txt', 'json', 'xlsx', 'xls']);
            $tableData = $this->reader->read($file, $clientExt, $target);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'is_valid' => false,
                'total_rows' => 0,
                'valid_count' => 0,
                'invalid_count' => 1,
                'create_count' => 0,
                'update_count' => 0,
                'message' => 'Failed to parse file: '.$e->getMessage(),
                'errors' => [
                    [
                        'row' => 1,
                        'field' => 'file',
                        'value' => $file->getClientOriginalName(),
                        'type' => 'invalid_structure',
                        'message' => $e->getMessage(),
                    ],
                ],
                'preview_rows' => [],
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'is_valid' => false,
                'total_rows' => 0,
                'valid_count' => 0,
                'invalid_count' => 1,
                'create_count' => 0,
                'update_count' => 0,
                'message' => 'The uploaded file could not be parsed. Verify the file and try again.',
                'errors' => [[
                    'row' => 1,
                    'field' => 'file',
                    'value' => $file->getClientOriginalName(),
                    'type' => 'invalid_structure',
                    'message' => 'The uploaded file could not be parsed. Verify the file and try again.',
                ]],
                'preview_rows' => [],
            ], 422);
        }

        $validationResult = $this->validator->validate($target, $tableData, $mode);

        $importToken = null;
        if ($validationResult['is_valid'] && ! empty($validationResult['validated_payload'])) {
            $importToken = $this->staging->stage(
                $target,
                $mode,
                $validationResult['validated_payload'],
                $user->id,
                [
                    'file_name' => $file->getClientOriginalName(),
                    'format' => $clientExt,
                    'file_size' => $file->getSize(),
                ],
            );
        }

        return response()->json([
            ...$validationResult,
            'import_token' => $importToken,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'target' => $target,
            'mode' => $mode,
        ]);
    }

    /**
     * Execute transactional commit of previously validated staged records.
     */
    public function commit(Request $request): JsonResponse
    {
        $request->validate([
            'import_token' => ['required', 'string'],
            'target' => ['required', 'string', 'in:items,locations,suppliers'],
        ], [
            'import_token.required' => 'Missing import validation token.',
            'target.in' => 'Invalid target module.',
        ]);

        $user = $request->user();
        $target = $request->input('target');
        $token = $request->input('import_token');

        $this->authorizeTarget($user, $target);

        $staged = $this->staging->retrieve($token, $user->id);
        if (! $staged || $staged['target'] !== $target) {
            return response()->json([
                'success' => false,
                'message' => 'Import session has expired or is invalid. Please re-upload and validate your file.',
            ], 422);
        }

        if (! $this->staging->claim($token, $user->id)) {
            $status = $this->staging->status($token, $user->id);

            return response()->json([
                'success' => true,
                'async' => true,
                'message' => $status['message'] ?? 'This import is already processing.',
                'status' => Arr::except($status ?? [], ['user_id']),
                'status_url' => route('inventory.import.status', ['token' => $token]),
            ], 202);
        }

        $recordCount = count($staged['records']);
        $this->auditLogger->log(
            action: AuditAction::BulkImportStarted,
            actor: $user,
            description: "Started {$target} bulk import: {$recordCount} records validated",
            newValues: ['target' => $target, 'total' => $recordCount],
            module: 'Imports',
            correlationId: $token,
        );

        if ($recordCount >= (int) config('imports.background_threshold', 1_000)) {
            try {
                ProcessDataImport::dispatch($token, $user->id, $target);
            } catch (Throwable $e) {
                report($e);
                $this->staging->fail($token, $user->id);

                return response()->json([
                    'success' => false,
                    'message' => 'The bulk import could not be started. No records were committed. You may retry it.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'async' => true,
                'message' => 'Bulk import started. You may leave this page and return to check its status.',
                'status' => Arr::except($this->staging->status($token, $user->id) ?? [], ['user_id']),
                'status_url' => route('inventory.import.status', ['token' => $token]),
            ], 202);
        }

        try {
            $result = $this->executor->execute(
                $target,
                $staged['records'],
                $user,
                fn (int $processed) => $this->staging->progress($token, $user->id, $processed),
                $token,
            );
            $this->staging->complete($token, $user->id, $result);
            $this->staging->forget($token);

            $targetName = match ($target) {
                'items' => 'inventory items',
                'locations' => 'storage locations',
                'suppliers' => 'suppliers',
                default => 'records',
            };

            return response()->json([
                'success' => true,
                'message' => "Successfully imported {$result['total']} {$targetName} ({$result['created']} created, {$result['updated']} updated).",
                'created' => $result['created'],
                'updated' => $result['updated'],
                'total' => $result['total'],
                'target' => $target,
            ]);
        } catch (Throwable $e) {
            report($e);
            $this->staging->fail($token, $user->id);
            try {
                $this->auditLogger->log(
                    action: AuditAction::BulkImportFailed,
                    actor: $user,
                    description: "Failed {$target} import; no records were committed",
                    newValues: ['target' => $target, 'total' => count($staged['records'])],
                    module: 'Imports',
                    outcome: 'failure',
                    correlationId: $token,
                );
            } catch (Throwable $auditException) {
                report($auditException);
            }

            $targetName = match ($target) {
                'items' => 'inventory items',
                'locations' => 'storage locations',
                'suppliers' => 'suppliers',
                default => 'records',
            };

            // The staging payload is deliberately kept: the executor rolled the
            // whole batch back, so the exact validated rows are still on hand and
            // the recovery service can genuinely replay them. The token is stored
            // as the reference ID so the incident is traceable back to the import
            // session that produced it.
            try {
                $this->recovery->recordFailure(
                    exception: $e,
                    module: 'Imports',
                    operation: 'data_import',
                    context: ['target' => $target, 'staged_rows' => count($staged['records'])],
                    isRetryable: true,
                    retryHandler: RecoveryRetryHandler::Import,
                    retryPayload: ['import_token' => $token, 'target' => $target],
                    strategy: 'automatic_rollback',
                    failureType: RecoveryFailureType::Import,
                    affectedResource: $targetName,
                    referenceId: $token,
                );
            } catch (Throwable $recoveryException) {
                report($recoveryException);
            }

            try {
                $this->notifications->sendToUser(
                    $user,
                    'import-failed:'.hash('sha256', $token),
                    'Data import failed',
                    "The {$targetName} import failed and no records were committed. Review the file and try again.",
                    NotificationPriority::Warning,
                    NotificationDestination::Import,
                );
            } catch (Throwable $notificationException) {
                report($notificationException);
            }

            return response()->json([
                'success' => false,
                'message' => 'The import could not be completed. No records were committed. Review the file and try again.',
            ], 500);
        }
    }

    public function status(Request $request, string $token): JsonResponse
    {
        $status = $this->staging->status($token, $request->user()->id);
        if ($status === null) {
            return response()->json(['message' => 'Import status was not found.'], 404);
        }

        $this->authorizeTarget($request->user(), (string) $status['target']);

        return response()->json(Arr::except($status, ['user_id']));
    }

    /**
     * Ensure the user holds permission for the target module.
     */
    protected function authorizeTarget($user, string $target): void
    {
        $permission = match ($target) {
            'items' => Permission::ManageItems->value,
            'locations' => Permission::ManageLocations->value,
            'suppliers' => Permission::ManageSuppliers->value,
            default => null,
        };

        if ($permission && ! $user->can($permission)) {
            abort(403, "You do not have permission to manage and import {$target}.");
        }
    }
}
