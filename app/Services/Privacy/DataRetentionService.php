<?php

namespace App\Services\Privacy;

use App\Enums\AuditAction;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\LogisticsDocument;
use App\Models\PrivacyRequest;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DataRetentionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Evaluate and optionally sweep expired ephemeral records according to policy.
     * Audit logs and inventory ledgers are NEVER purged.
     *
     * @return array{
     *     dry_run: bool,
     *     notifications_purged: int,
     *     resolved_recovery_records_purged: int,
     *     ai_chat_conversations_purged: int,
     *     ai_chat_attachments_purged: int,
     *     expired_dsar_packages_disposed: int,
     *     expired_documents_flagged: int,
     *     temporary_files_cleared: int
     * }
     */
    public function sweepEphemeralData(bool $dryRun = false, ?User $actor = null): array
    {
        $notificationCutoffDays = max(30, (int) config('privacy.retention.expired_notifications_days', 90));
        $recoveryCutoffDays = max(90, (int) config('privacy.retention.resolved_recovery_records_days', 180));
        $chatTempCutoffDays = max(7, (int) config('privacy.retention.temporary_chat_attachments_days', 30));
        $chatHistoryCutoffDays = max(1, (int) config('privacy.retention.ai_chat_history_days', 30));

        $notificationCutoff = now()->subDays($notificationCutoffDays);
        $recoveryCutoff = now()->subDays($recoveryCutoffDays);
        $tempCutoff = now()->subDays($chatTempCutoffDays);
        $chatHistoryCutoff = now()->subDays($chatHistoryCutoffDays);

        // 1. Expired notifications query
        $notificationsQuery = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', $notificationCutoff);
        $notificationsCount = $notificationsQuery->count();

        // 2. Old resolved recovery records
        $recoveryQuery = SystemRecoveryRecord::query()
            ->where('status', 'resolved')
            ->where('resolved_at', '<', $recoveryCutoff);
        $recoveryCount = $recoveryQuery->count();

        // 3. Logistics documents past NAP retention date (flagged, not deleted)
        $expiredDocsQuery = LogisticsDocument::query()
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', now()->toDateString())
            ->where('status', '!=', 'archived');
        $expiredDocsCount = $expiredDocsQuery->count();

        // 4. AI conversations and their private attachments after the defined retention window
        $expiredConversationIds = AiChatConversation::query()
            ->where('updated_at', '<', $chatHistoryCutoff)
            ->pluck('id');
        $chatConversationCount = $expiredConversationIds->count();
        $chatAttachmentPaths = $chatConversationCount > 0
            ? AiChatMessage::query()
                ->whereIn('conversation_id', $expiredConversationIds)
                ->whereNotNull('attachment_path')
                ->pluck('attachment_path')
                ->filter(fn ($path) => is_string($path) && $path !== '' && ! str_contains($path, '..'))
                ->unique()
                ->values()
            : collect();
        $chatAttachmentCount = $chatAttachmentPaths->count();

        // 5. Time-limited DSAR export archives. The case record remains for accountability.
        $expiredPackages = PrivacyRequest::query()
            ->whereNotNull('package_path')
            ->whereNotNull('package_expires_at')
            ->where('package_expires_at', '<=', now())
            ->get();
        $expiredPackageCount = $expiredPackages->count();

        // 6. Temporary files/scratch in storage
        $tempFilesCleared = 0;
        $tempDirs = [
            storage_path('app/temp'),
            storage_path('app/public/temp'),
        ];

        foreach ($tempDirs as $dir) {
            if (File::isDirectory($dir)) {
                $files = File::files($dir);
                foreach ($files as $file) {
                    if (File::lastModified($file->getPathname()) < $tempCutoff->timestamp) {
                        $tempFilesCleared++;
                        if (! $dryRun) {
                            File::delete($file->getPathname());
                        }
                    }
                }
            }
        }

        if (! $dryRun) {
            foreach ($chatAttachmentPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            foreach ($expiredPackages as $privacyRequest) {
                $packagePath = (string) $privacyRequest->package_path;
                if ($packagePath !== '' && ! str_contains($packagePath, '..')) {
                    Storage::disk('local')->delete($packagePath);
                }

                $privacyRequest->update([
                    'status' => PrivacyRequest::STATUS_EXPIRED,
                    'export_payload' => null,
                    'package_filename' => null,
                    'package_path' => null,
                    'package_hash' => null,
                    'package_size_bytes' => null,
                    'package_manifest' => null,
                ]);
            }

            if ($notificationsCount > 0) {
                $notificationsQuery->delete();
            }

            if ($recoveryCount > 0) {
                $recoveryQuery->delete();
            }

            if ($chatConversationCount > 0) {
                AiChatConversation::query()->whereIn('id', $expiredConversationIds->all())->delete();
            }

            $this->auditLogger->record(
                action: AuditAction::ExecutedDataRetention,
                actor: $actor,
                description: "Executed data retention sweep: {$notificationsCount} notifications, {$recoveryCount} resolved recovery records, {$chatConversationCount} AI conversations, and {$expiredPackageCount} expired DSAR packages processed; {$expiredDocsCount} NAP documents flagged for disposal review.",
                newValues: [
                    'notifications_purged' => $notificationsCount,
                    'recovery_purged' => $recoveryCount,
                    'ai_chat_conversations_purged' => $chatConversationCount,
                    'ai_chat_attachments_purged' => $chatAttachmentCount,
                    'expired_dsar_packages_disposed' => $expiredPackageCount,
                    'expired_documents_flagged' => $expiredDocsCount,
                    'temp_files_cleared' => $tempFilesCleared,
                ]
            );
        }

        return [
            'dry_run' => $dryRun,
            'notifications_purged' => $notificationsCount,
            'resolved_recovery_records_purged' => $recoveryCount,
            'ai_chat_conversations_purged' => $chatConversationCount,
            'ai_chat_attachments_purged' => $chatAttachmentCount,
            'expired_dsar_packages_disposed' => $expiredPackageCount,
            'expired_documents_flagged' => $expiredDocsCount,
            'temporary_files_cleared' => $tempFilesCleared,
        ];
    }
}
