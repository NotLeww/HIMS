<?php

namespace App\Console\Commands;

use App\Jobs\GenerateScheduledReport;
use App\Models\ScheduledReport;
use App\Models\ScheduledReportExecution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessScheduledReports extends Command
{
    protected $signature = 'reports:run-scheduled';

    protected $description = 'Queue due scheduled reports for generation and email delivery';

    public function handle(): int
    {
        ScheduledReportExecution::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinute())
            ->pluck('id')
            ->each(fn (int $executionId) => $this->queue($executionId));

        $dueIds = ScheduledReport::query()
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        foreach ($dueIds as $scheduleId) {
            $executionId = DB::transaction(function () use ($scheduleId): ?int {
                $schedule = ScheduledReport::query()->lockForUpdate()->with('recipient')->find($scheduleId);

                if (! $schedule?->is_active || $schedule->next_run_at === null || $schedule->next_run_at->isFuture()) {
                    return null;
                }

                $scheduledFor = $schedule->next_run_at->copy();
                $execution = ScheduledReportExecution::firstOrCreate([
                    'scheduled_report_id' => $schedule->id,
                    'scheduled_for' => $scheduledFor,
                ], [
                    'requested_by_user_id' => $schedule->created_by_user_id,
                    'recipient_user_id' => $schedule->recipient_user_id,
                    'report_type' => $schedule->report_type,
                    'report_format' => $schedule->report_format,
                    'filters' => $schedule->filters,
                    'recipient_email' => $schedule->recipient?->email ?? '',
                    'status' => 'pending',
                    'mail_status' => 'pending',
                ]);

                $schedule->forceFill([
                    'next_run_at' => $schedule->nextRunAfter(now()),
                    'last_status' => 'pending',
                ])->save();

                return $execution->wasRecentlyCreated ? $execution->id : null;
            });

            if ($executionId === null) {
                continue;
            }

            $this->queue($executionId);
        }

        return self::SUCCESS;
    }

    private function queue(int $executionId): void
    {
        try {
            GenerateScheduledReport::dispatch($executionId);
        } catch (Throwable $exception) {
            ScheduledReportExecution::whereKey($executionId)->where('status', 'pending')->update([
                'status' => 'failed',
                'failure_stage' => 'queue',
                'error_summary' => 'The report could not be queued for processing.',
                'completed_at' => now(),
            ]);
            report($exception);
        }
    }
}
