<?php

namespace App\Jobs;

use App\Enums\Permission;
use App\Mail\ScheduledReportMail;
use App\Models\ScheduledReportExecution;
use App\Services\InventoryReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class GenerateScheduledReport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 240;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $executionId) {}

    public function uniqueId(): string
    {
        return (string) $this->executionId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('scheduled-report:'.$this->executionId))->expireAfter(300)];
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(InventoryReportService $reports): void
    {
        $execution = ScheduledReportExecution::with(['scheduledReport', 'requester', 'recipient'])->find($this->executionId);

        if ($execution === null || $execution->status === 'sent') {
            return;
        }

        $requester = $execution->requester;
        $recipient = $execution->recipient;

        if (! $requester?->isActive() || ! $requester->can(Permission::ManageScheduledReports->value) || ! $requester->can(Permission::ViewReports->value)) {
            $this->skip($execution, 'The schedule owner is no longer authorized to generate reports.');

            return;
        }

        if (! $recipient?->isActive() || ! $recipient->can(Permission::ViewReports->value)) {
            $this->skip($execution, 'The recipient is no longer authorized to receive reports.');

            return;
        }

        $financial = in_array($execution->report_type, ['procurement_expense', 'spend_by_supplier'], true);
        if ($financial && (! $requester->can(Permission::ViewProcurementSensitiveData->value) || ! $recipient->can(Permission::ViewProcurementSensitiveData->value))) {
            $this->skip($execution, 'The report requires procurement financial-data permission.');

            return;
        }

        $execution->forceFill([
            'status' => 'processing',
            'mail_status' => 'pending',
            'failure_stage' => null,
            'error_summary' => null,
            'started_at' => now(),
        ])->save();

        $stage = 'generation';

        try {
            $report = $reports->generateReport([
                ...($execution->filters ?? []),
                'report_type' => $execution->report_type,
                'format' => $execution->report_format,
            ], $recipient);
            $report['meta']['generated_by'] = 'HIMS Scheduler (configured by '.$requester->name.')';

            [$content, $extension, $mime] = match ($execution->report_format) {
                'pdf' => [$reports->exportPdf($report)->getContent(), 'pdf', 'application/pdf'],
                'csv' => [$reports->csvContent($report), 'csv', 'text/csv'],
                'excel' => [view('inventory.reports.export-excel', compact('report'))->render(), 'xls', 'application/vnd.ms-excel'],
                default => throw new RuntimeException('Unsupported scheduled report format.'),
            };

            $filename = 'hims-'.$execution->report_type.'-'.$execution->scheduled_for->format('Ymd_His').'.'.$extension;
            $stage = 'email';

            Mail::to($recipient)->send(new ScheduledReportMail(
                reportTitle: $report['meta']['report_title'] ?? 'HIMS Report',
                scheduledFor: $execution->scheduled_for,
                filterSummary: $report['meta']['filter_labels'] ?? [],
                attachmentData: $content,
                attachmentName: $filename,
                attachmentMime: $mime,
            ));

            $recordCount = ($execution->report_type === 'all')
                ? collect($report['sections'] ?? [])->sum(fn (array $section) => count($section['rows'] ?? []))
                : count($report['data'] ?? []);

            $execution->forceFill([
                'status' => 'sent',
                'mail_status' => 'accepted',
                'record_count' => $recordCount,
                'mail_sent_at' => now(),
                'completed_at' => now(),
            ])->save();

            $execution->scheduledReport?->forceFill([
                'last_run_at' => now(),
                'last_status' => 'sent',
            ])->save();
        } catch (Throwable $exception) {
            $execution->forceFill([
                'status' => 'failed',
                'mail_status' => $stage === 'email' ? 'failed' : 'not_sent',
                'failure_stage' => $stage,
                'error_summary' => $stage === 'email'
                    ? 'The report was generated, but the email could not be accepted by the configured mailer.'
                    : 'The report could not be generated.',
                'completed_at' => now(),
            ])->save();

            $execution->scheduledReport?->forceFill([
                'last_run_at' => now(),
                'last_status' => 'failed',
            ])->save();

            Log::error('Scheduled report execution failed.', [
                'execution_id' => $execution->id,
                'stage' => $stage,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    private function skip(ScheduledReportExecution $execution, string $reason): void
    {
        $execution->forceFill([
            'status' => 'skipped',
            'mail_status' => 'not_sent',
            'failure_stage' => 'authorization',
            'error_summary' => $reason,
            'completed_at' => now(),
        ])->save();

        $execution->scheduledReport?->forceFill([
            'last_run_at' => now(),
            'last_status' => 'skipped',
        ])->save();
    }
}
