<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ItemCategory;
use App\Models\ScheduledReport;
use App\Models\ScheduledReportExecution;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScheduledReportController extends Controller implements HasMiddleware
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ManageScheduledReports->value),
        ];
    }

    public function index(Request $request): View
    {
        $editing = null;
        if ($request->filled('edit')) {
            $editing = ScheduledReport::findOrFail($request->integer('edit'));
        }

        $recipients = User::active()->orderBy('name')->get(['id', 'name', 'email', 'role', 'status'])
            ->filter(fn (User $user) => $user->can(Permission::ViewReports->value))
            ->values();

        return view('inventory.reports.schedules', [
            'schedules' => ScheduledReport::with(['recipient', 'creator'])->latest()->get(),
            'executions' => ScheduledReportExecution::with(['scheduledReport', 'recipient'])->latest()->limit(50)->get(),
            'editing' => $editing,
            'recipients' => $recipients,
            'reportTypes' => InventoryReportService::REPORT_TYPES,
            'formats' => collect(InventoryReportService::EXPORT_FORMATS)->only(['pdf', 'excel', 'csv'])->all(),
            'frequencies' => ScheduledReport::FREQUENCIES,
            'daysOfWeek' => ScheduledReport::DAYS_OF_WEEK,
            'periods' => collect(InventoryReportService::PERIOD_OPTIONS)->except('custom')->all(),
            'categories' => ItemCategory::orderBy('name')->get(['id', 'name']),
            'locations' => StorageLocation::orderBy('name')->get(['id', 'name', 'code']),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'movementTypes' => MovementType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $this->authorizeConfiguration($request->user(), $validated);

        $schedule = new ScheduledReport($this->attributes($validated, $request->user()->id));
        $schedule->next_run_at = $schedule->nextRunAfter(now());
        $schedule->save();

        $this->audit(AuditAction::CreatedScheduledReport, $request->user(), $schedule, 'Created a scheduled report.');

        return redirect()->route('inventory.reports.schedules')->with('success', 'Scheduled report created.');
    }

    public function update(Request $request, ScheduledReport $scheduledReport): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $this->authorizeConfiguration($request->user(), $validated);
        $old = $this->auditValues($scheduledReport);

        $scheduledReport->fill($this->attributes($validated, $scheduledReport->created_by_user_id, $scheduledReport->is_active));
        $scheduledReport->next_run_at = $scheduledReport->is_active
            ? $scheduledReport->nextRunAfter(now())
            : null;
        $scheduledReport->save();

        $this->auditLogger->log(
            action: AuditAction::UpdatedScheduledReport,
            actor: $request->user(),
            description: 'Updated a scheduled report.',
            target: $scheduledReport,
            targetName: InventoryReportService::REPORT_TYPES[$scheduledReport->report_type] ?? $scheduledReport->report_type,
            oldValues: $old,
            newValues: $this->auditValues($scheduledReport),
            module: 'Reports & Analytics',
        );

        return redirect()->route('inventory.reports.schedules')->with('success', 'Scheduled report updated.');
    }

    public function toggle(Request $request, ScheduledReport $scheduledReport): RedirectResponse
    {
        $scheduledReport->is_active = ! $scheduledReport->is_active;
        $scheduledReport->next_run_at = $scheduledReport->is_active
            ? $scheduledReport->nextRunAfter(now())
            : null;
        $scheduledReport->save();

        $this->audit(
            AuditAction::ToggledScheduledReport,
            $request->user(),
            $scheduledReport,
            $scheduledReport->is_active ? 'Enabled a scheduled report.' : 'Disabled a scheduled report.'
        );

        return back()->with('success', $scheduledReport->is_active ? 'Scheduled report enabled.' : 'Scheduled report disabled.');
    }

    public function destroy(Request $request, ScheduledReport $scheduledReport): RedirectResponse
    {
        $name = InventoryReportService::REPORT_TYPES[$scheduledReport->report_type] ?? $scheduledReport->report_type;
        $id = $scheduledReport->id;
        $scheduledReport->delete();

        $this->auditLogger->log(
            action: AuditAction::DeletedScheduledReport,
            actor: $request->user(),
            description: 'Deleted a scheduled report while preserving its execution history.',
            targetName: $name,
            newValues: ['scheduled_report_id' => $id],
            module: 'Reports & Analytics',
            targetType: ScheduledReport::class,
            targetId: $id,
        );

        return redirect()->route('inventory.reports.schedules')->with('success', 'Scheduled report deleted; execution history was preserved.');
    }

    private function rules(): array
    {
        return [
            'report_type' => ['required', Rule::in(array_keys(InventoryReportService::REPORT_TYPES))],
            'report_format' => ['required', Rule::in(['pdf', 'excel', 'csv'])],
            'frequency' => ['required', Rule::in(array_keys(ScheduledReport::FREQUENCIES))],
            'day_of_week' => ['nullable', 'required_if:frequency,weekly', 'integer', 'between:0,6'],
            'day_of_month' => ['nullable', 'required_if:frequency,monthly', 'integer', 'between:1,31'],
            'run_at' => ['required', 'date_format:H:i'],
            'recipient_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'period' => ['required', Rule::in(['1', '7', '30', '90', '365', 'all'])],
            'category_id' => ['nullable', 'integer', 'exists:item_categories,id'],
            'storage_location_id' => ['nullable', 'integer', 'exists:storage_locations,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'movement_type' => ['nullable', Rule::enum(MovementType::class)],
            'status' => ['nullable', Rule::in(['all', 'in_stock', 'low_stock', 'out_of_stock'])],
        ];
    }

    private function authorizeConfiguration(User $actor, array $validated): void
    {
        abort_unless($actor->can(Permission::ViewReports->value), 403);

        $recipient = User::findOrFail($validated['recipient_user_id']);
        abort_unless($recipient->isActive() && $recipient->can(Permission::ViewReports->value), 403);

        if (in_array($validated['report_type'], ['procurement_expense', 'spend_by_supplier'], true)) {
            abort_unless(
                $actor->can(Permission::ViewProcurementSensitiveData->value)
                && $recipient->can(Permission::ViewProcurementSensitiveData->value),
                403
            );
        }
    }

    private function attributes(array $validated, ?int $creatorId, bool $isActive = true): array
    {
        $filters = collect($validated)
            ->only(['period', 'category_id', 'storage_location_id', 'supplier_id', 'movement_type', 'status'])
            ->reject(fn ($value) => $value === null || $value === '' || $value === 'all')
            ->all();

        return [
            'report_type' => $validated['report_type'],
            'report_format' => $validated['report_format'],
            'frequency' => $validated['frequency'],
            'day_of_week' => $validated['frequency'] === 'weekly' ? $validated['day_of_week'] : null,
            'day_of_month' => $validated['frequency'] === 'monthly' ? $validated['day_of_month'] : null,
            'run_at' => $validated['run_at'],
            'filters' => $filters,
            'recipient_user_id' => $validated['recipient_user_id'],
            'created_by_user_id' => $creatorId,
            'is_active' => $isActive,
        ];
    }

    private function audit(AuditAction $action, User $actor, ScheduledReport $schedule, string $description): void
    {
        $this->auditLogger->log(
            action: $action,
            actor: $actor,
            description: $description,
            target: $schedule,
            targetName: InventoryReportService::REPORT_TYPES[$schedule->report_type] ?? $schedule->report_type,
            newValues: $this->auditValues($schedule),
            module: 'Reports & Analytics',
        );
    }

    private function auditValues(ScheduledReport $schedule): array
    {
        return [
            'report_type' => $schedule->report_type,
            'report_format' => $schedule->report_format,
            'frequency' => $schedule->frequency,
            'recipient_user_id' => $schedule->recipient_user_id,
            'is_active' => $schedule->is_active,
            'next_run_at' => $schedule->next_run_at?->toDateTimeString(),
        ];
    }
}
