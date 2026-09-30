<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Jobs\GenerateScheduledReport;
use App\Mail\ScheduledReportMail;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\ScheduledReport;
use App\Models\ScheduledReportExecution;
use App\Models\User;
use App\Services\InventoryReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ScheduledReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_authorized_user_can_create_a_persisted_schedule_and_view_management_ui(): void
    {
        Carbon::setTestNow('2026-09-28 09:00:00');
        $manager = User::factory()->inventoryManager()->create();

        $this->actingAs($manager)->get(route('inventory.reports.schedules'))
            ->assertOk()
            ->assertSee('Scheduled Reports')
            ->assertSee('Create Schedule')
            ->assertSee('schedule-form-modal')
            ->assertSee('Execution history and email log');

        $this->actingAs($manager)->post(route('inventory.reports.schedules.store'), $this->payload($manager, [
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'run_at' => '08:30',
        ]))->assertRedirect(route('inventory.reports.schedules'));

        $schedule = ScheduledReport::sole();
        $this->assertSame('weekly', $schedule->frequency);
        $this->assertSame('2026-10-05 08:30:00', $schedule->next_run_at->toDateTimeString());
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CreatedScheduledReport->value,
            'user_id' => $manager->id,
            'target_id' => (string) $schedule->id,
        ]);
    }

    public function test_unauthorized_user_cannot_manage_schedules_or_use_an_unauthorized_financial_recipient(): void
    {
        $viewer = User::factory()->viewer()->create();
        $manager = User::factory()->inventoryManager()->create();

        $this->actingAs($viewer)->get(route('inventory.reports.schedules'))->assertForbidden();
        $this->actingAs($viewer)->post(route('inventory.reports.schedules.store'), $this->payload($viewer))->assertForbidden();

        $this->actingAs($manager)->post(route('inventory.reports.schedules.store'), $this->payload($viewer, [
            'report_type' => 'procurement_expense',
        ]))->assertForbidden();

        $this->assertDatabaseCount('scheduled_reports', 0);
    }

    public function test_due_schedule_command_generates_current_filtered_data_and_email_attachment(): void
    {
        Carbon::setTestNow('2026-09-28 10:00:00');
        Mail::fake();
        $manager = User::factory()->inventoryManager()->create();
        $included = ItemCategory::create(['name' => 'Medicines', 'code' => 'MED']);
        $excluded = ItemCategory::create(['name' => 'Supplies', 'code' => 'SUP']);
        $schedule = $this->schedule($manager, [
            'filters' => ['period' => '30', 'category_id' => $included->id],
            'next_run_at' => now()->subMinute(),
        ]);

        // Created after the schedule definition: execution must use current data.
        InventoryItem::create([
            'name' => 'Current Paracetamol',
            'sku' => 'CUR-MED',
            'category_id' => $included->id,
            'quantity_on_hand' => 25,
            'reorder_level' => 5,
            'unit_cost' => 1.5,
        ]);
        InventoryItem::create([
            'name' => 'Excluded Gauze',
            'sku' => 'EXC-SUP',
            'category_id' => $excluded->id,
            'quantity_on_hand' => 25,
            'reorder_level' => 5,
            'unit_cost' => 2,
        ]);

        $this->artisan('reports:run-scheduled')->assertExitCode(0);

        $execution = ScheduledReportExecution::sole();
        $this->assertSame('sent', $execution->status);
        $this->assertSame('accepted', $execution->mail_status);
        $this->assertSame(1, $execution->record_count);
        $this->assertNotNull($execution->mail_sent_at);
        $this->assertTrue($schedule->fresh()->next_run_at->isFuture());
        $this->assertSame('sent', $schedule->fresh()->last_status);

        Mail::assertSent(ScheduledReportMail::class, function (ScheduledReportMail $mail) use ($manager): bool {
            return $mail->hasTo($manager->email)
                && $mail->attachmentName === 'hims-stock_status-20260928_095900.csv'
                && str_contains($mail->attachmentData, 'Current Paracetamol')
                && ! str_contains($mail->attachmentData, 'Excluded Gauze');
        });
    }

    public function test_duplicate_scheduler_runs_and_job_retries_do_not_repeat_successful_delivery(): void
    {
        Carbon::setTestNow('2026-09-28 10:00:00');
        Mail::fake();
        $manager = User::factory()->inventoryManager()->create();
        $this->schedule($manager, ['next_run_at' => now()->subMinute()]);

        $this->artisan('reports:run-scheduled')->assertExitCode(0);
        $execution = ScheduledReportExecution::sole();
        $this->artisan('reports:run-scheduled')->assertExitCode(0);
        (new GenerateScheduledReport($execution->id))->handle(app(InventoryReportService::class));

        $this->assertDatabaseCount('scheduled_report_executions', 1);
        Mail::assertSentCount(1);
    }

    public function test_scheduled_pdf_and_excel_exports_are_attached_in_the_expected_format(): void
    {
        Mail::fake();
        $manager = User::factory()->inventoryManager()->create();
        $schedule = $this->schedule($manager);

        foreach ([
            'pdf' => ['.pdf', 'application/pdf', fn (string $data): bool => str_starts_with($data, '%PDF')],
            'excel' => ['.xls', 'application/vnd.ms-excel', fn (string $data): bool => str_contains($data, '<table')],
        ] as $format => [$extension, $mime, $contentMatches]) {
            $execution = $this->execution($schedule, $manager, [
                'scheduled_for' => now()->addMinutes($format === 'pdf' ? 1 : 2),
                'report_format' => $format,
            ]);

            (new GenerateScheduledReport($execution->id))->handle(app(InventoryReportService::class));

            $this->assertSame('sent', $execution->fresh()->status);
            Mail::assertSent(ScheduledReportMail::class, fn (ScheduledReportMail $mail): bool => str_ends_with($mail->attachmentName, $extension)
                && $mail->attachmentMime === $mime
                && $contentMatches($mail->attachmentData)
            );
        }

        Mail::assertSentCount(2);
    }

    public function test_disabled_schedule_is_not_executed_and_reenable_calculates_a_future_run(): void
    {
        Carbon::setTestNow('2026-09-28 10:00:00');
        Mail::fake();
        $manager = User::factory()->inventoryManager()->create();
        $schedule = $this->schedule($manager, [
            'is_active' => false,
            'next_run_at' => now()->subMinute(),
        ]);

        $this->artisan('reports:run-scheduled')->assertExitCode(0);
        $this->assertDatabaseCount('scheduled_report_executions', 0);

        $this->actingAs($manager)->patch(route('inventory.reports.schedules.toggle', $schedule))->assertRedirect();
        $this->assertTrue($schedule->fresh()->is_active);
        $this->assertTrue($schedule->fresh()->next_run_at->isFuture());
    }

    public function test_edit_recalculates_next_run_and_preserves_history_then_delete_preserves_history(): void
    {
        Carbon::setTestNow('2026-09-28 10:00:00');
        $manager = User::factory()->inventoryManager()->create();
        $schedule = $this->schedule($manager);
        $execution = $this->execution($schedule, $manager, ['status' => 'sent', 'mail_status' => 'accepted']);

        $this->actingAs($manager)
            ->get(route('inventory.reports.schedules', ['edit' => $schedule->id]))
            ->assertOk()
            ->assertSee('Edit scheduled report')
            ->assertSee('schedule-form-modal');

        $this->actingAs($manager)->patch(route('inventory.reports.schedules.update', $schedule), $this->payload($manager, [
            'frequency' => 'monthly',
            'day_of_week' => null,
            'day_of_month' => 31,
            'run_at' => '07:15',
        ]))->assertRedirect(route('inventory.reports.schedules'));

        $this->assertSame('2026-09-30 07:15:00', $schedule->fresh()->next_run_at->toDateTimeString());
        $this->assertDatabaseHas('scheduled_report_executions', ['id' => $execution->id]);

        $this->actingAs($manager)->delete(route('inventory.reports.schedules.destroy', $schedule))
            ->assertRedirect(route('inventory.reports.schedules'));

        $this->assertDatabaseMissing('scheduled_reports', ['id' => $schedule->id]);
        $this->assertDatabaseHas('scheduled_report_executions', [
            'id' => $execution->id,
            'scheduled_report_id' => null,
        ]);
        $this->assertSame(1, AuditLog::where('action', AuditAction::DeletedScheduledReport)->count());
    }

    public function test_monthly_day_31_clamps_to_shorter_months(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $schedule = $this->schedule($manager, [
            'frequency' => 'monthly',
            'day_of_month' => 31,
            'run_at' => '08:00',
        ]);

        $next = $schedule->nextRunAfter(Carbon::parse('2027-02-01 09:00:00'));
        $this->assertSame('2027-02-28 08:00:00', $next->toDateTimeString());
    }

    public function test_authorization_is_rechecked_at_execution_time(): void
    {
        Mail::fake();
        $manager = User::factory()->inventoryManager()->create();
        $schedule = $this->schedule($manager);
        $execution = $this->execution($schedule, $manager);
        $manager->forceFill(['status' => 'inactive'])->save();

        (new GenerateScheduledReport($execution->id))->handle(app(InventoryReportService::class));

        $this->assertSame('skipped', $execution->fresh()->status);
        $this->assertSame('authorization', $execution->fresh()->failure_stage);
        Mail::assertNothingSent();
    }

    public function test_generated_report_is_scoped_to_the_recipient_permissions(): void
    {
        Mail::fake();
        $manager = User::factory()->inventoryManager()->create();
        $viewer = User::factory()->viewer()->create();
        InventoryItem::create([
            'name' => 'Scoped Item',
            'sku' => 'SCOPE-001',
            'quantity_on_hand' => 10,
            'reorder_level' => 2,
            'unit_cost' => 99.50,
        ]);
        $schedule = $this->schedule($manager, ['recipient_user_id' => $viewer->id]);
        $execution = ScheduledReportExecution::create([
            'scheduled_report_id' => $schedule->id,
            'requested_by_user_id' => $manager->id,
            'recipient_user_id' => $viewer->id,
            'scheduled_for' => now(),
            'report_type' => 'stock_status',
            'report_format' => 'csv',
            'filters' => ['period' => '30'],
            'recipient_email' => $viewer->email,
            'status' => 'pending',
            'mail_status' => 'pending',
        ]);

        (new GenerateScheduledReport($execution->id))->handle(app(InventoryReportService::class));

        Mail::assertSent(ScheduledReportMail::class, function (ScheduledReportMail $mail): bool {
            return str_contains($mail->attachmentData, 'Scoped Item')
                && ! str_contains($mail->attachmentData, 'Unit Cost')
                && ! str_contains($mail->attachmentData, 'Total Value');
        });
    }

    public function test_report_generation_and_email_failures_are_recorded_safely(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $schedule = $this->schedule($manager);

        $generationExecution = $this->execution($schedule, $manager);
        $brokenReports = Mockery::mock(InventoryReportService::class);
        $brokenReports->shouldReceive('generateReport')->once()->andThrow(new RuntimeException('database detail'));

        try {
            (new GenerateScheduledReport($generationExecution->id))->handle($brokenReports);
            $this->fail('Expected report generation to fail.');
        } catch (RuntimeException) {
            $this->assertSame('generation', $generationExecution->fresh()->failure_stage);
            $this->assertSame('The report could not be generated.', $generationExecution->fresh()->error_summary);
        }

        $emailExecution = $this->execution($schedule, $manager, [
            'scheduled_for' => now()->addMinute(),
        ]);
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP secret detail'));

        try {
            (new GenerateScheduledReport($emailExecution->id))->handle(app(InventoryReportService::class));
            $this->fail('Expected email delivery to fail.');
        } catch (RuntimeException) {
            $this->assertSame('email', $emailExecution->fresh()->failure_stage);
            $this->assertSame('failed', $emailExecution->fresh()->mail_status);
            $this->assertStringNotContainsString('SMTP secret detail', $emailExecution->fresh()->error_summary);
        }
    }

    public function test_existing_manual_report_export_still_works(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        $this->actingAs($manager)
            ->get('/inventory/reports/generate?report_type=stock_status&format=csv&period=30')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    private function payload(User $recipient, array $overrides = []): array
    {
        return array_replace([
            'report_type' => 'stock_status',
            'report_format' => 'csv',
            'frequency' => 'daily',
            'day_of_week' => null,
            'day_of_month' => null,
            'run_at' => '08:00',
            'recipient_user_id' => $recipient->id,
            'period' => '30',
            'category_id' => null,
            'storage_location_id' => null,
            'supplier_id' => null,
            'movement_type' => null,
            'status' => 'all',
        ], $overrides);
    }

    private function schedule(User $manager, array $overrides = []): ScheduledReport
    {
        return ScheduledReport::create(array_replace([
            'report_type' => 'stock_status',
            'report_format' => 'csv',
            'frequency' => 'daily',
            'run_at' => '08:00',
            'filters' => ['period' => '30'],
            'recipient_user_id' => $manager->id,
            'created_by_user_id' => $manager->id,
            'is_active' => true,
            'next_run_at' => now()->addDay(),
        ], $overrides));
    }

    private function execution(ScheduledReport $schedule, User $manager, array $overrides = []): ScheduledReportExecution
    {
        return ScheduledReportExecution::create(array_replace([
            'scheduled_report_id' => $schedule->id,
            'requested_by_user_id' => $manager->id,
            'recipient_user_id' => $manager->id,
            'scheduled_for' => now(),
            'report_type' => $schedule->report_type,
            'report_format' => $schedule->report_format,
            'filters' => $schedule->filters,
            'recipient_email' => $manager->email,
            'status' => 'pending',
            'mail_status' => 'pending',
        ], $overrides));
    }
}
