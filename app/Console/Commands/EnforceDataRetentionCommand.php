<?php

namespace App\Console\Commands;

use App\Services\Privacy\DataRetentionService;
use Illuminate\Console\Command;

class EnforceDataRetentionCommand extends Command
{
    protected $signature = 'privacy:enforce-retention {--dry-run : Simulate retention sweep without pruning ephemeral records}';

    protected $description = 'Enforce ISO 27001 / DPA data retention policies on ephemeral files and logs';

    public function handle(DataRetentionService $retentionService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? 'Running Data Retention Sweep in DRY-RUN mode (no deletions will occur)...'
            : 'Enforcing Data Retention Policies across ephemeral storage and records...');

        $results = $retentionService->sweepEphemeralData(dryRun: $dryRun);

        $this->table(
            ['Retention Category', 'Count Processed / Flagged'],
            [
                ['Temporary Export & Scratch Files Pruned', $results['temporary_files_cleared']],
                ['Read Notifications Purged', $results['notifications_purged']],
                ['Resolved System Failure Records Archived', $results['resolved_recovery_records_purged']],
                ['Expired AI Conversations Purged', $results['ai_chat_conversations_purged']],
                ['AI Chat Attachments Purged', $results['ai_chat_attachments_purged']],
                ['Expired DSAR Packages Disposed', $results['expired_dsar_packages_disposed']],
                ['NAP Logistics Records Flagged for Review', $results['expired_documents_flagged']],
                ['Permanent Audit Trail Preserved', 'ALWAYS PRESERVED (0 Pruned)'],
            ]
        );

        $this->info('Retention sweep execution complete.');

        return self::SUCCESS;
    }
}
