<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ImportStagingService
{
    private const CACHE_PREFIX = 'hims_import_staging_';

    private const STATUS_PREFIX = 'hims_import_status_';

    private const CLAIM_PREFIX = 'hims_import_claim_';

    private const TTL_MINUTES = 60;

    /**
     * Store validated import payload in cache and return a unique token.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    public function stage(string $target, string $mode, array $records, int $userId, array $metadata = []): string
    {
        $token = (string) Str::uuid();
        $key = self::CACHE_PREFIX.$token;

        Cache::put($key, [
            'target' => $target,
            'mode' => $mode,
            'records' => $records,
            'user_id' => $userId,
            'created_at' => now()->timestamp,
        ], now()->addMinutes(self::TTL_MINUTES));

        Cache::put(self::STATUS_PREFIX.$token, [
            'token' => $token,
            'user_id' => $userId,
            'target' => $target,
            'status' => 'validated',
            'total' => count($records),
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'message' => 'File validated and ready to import.',
            'file_name' => $metadata['file_name'] ?? null,
            'format' => $metadata['format'] ?? null,
            'file_size' => $metadata['file_size'] ?? null,
            'started_at' => null,
            'completed_at' => null,
        ], now()->addMinutes((int) config('imports.status_ttl_minutes', 1_440)));

        return $token;
    }

    /**
     * Retrieve staged import payload.
     *
     * @return array{target: string, mode: string, records: array<int, array<string, mixed>>}|null
     */
    public function retrieve(string $token, int $userId): ?array
    {
        $key = self::CACHE_PREFIX.$token;
        $staged = Cache::get($key);

        if (! $staged || ! is_array($staged)) {
            return null;
        }

        // Verify token belongs to the requesting user
        if (($staged['user_id'] ?? null) !== $userId) {
            return null;
        }

        return [
            'target' => $staged['target'],
            'mode' => $staged['mode'],
            'records' => $staged['records'],
        ];
    }

    /**
     * Remove staged payload after completion.
     */
    public function forget(string $token): void
    {
        Cache::forget(self::CACHE_PREFIX.$token);
    }

    public function claim(string $token, int $userId): bool
    {
        if ($this->retrieve($token, $userId) === null) {
            return false;
        }

        $claimed = Cache::add(self::CLAIM_PREFIX.$token, true, now()->addMinutes(self::TTL_MINUTES));
        if ($claimed) {
            $this->updateStatus($token, $userId, [
                'status' => 'processing',
                'message' => 'Import is processing.',
                'started_at' => now()->toIso8601String(),
            ]);
        }

        return $claimed;
    }

    public function progress(string $token, int $userId, int $processed): void
    {
        $this->updateStatus($token, $userId, ['processed' => $processed]);
    }

    /** @param array{created: int, updated: int, total: int} $result */
    public function complete(string $token, int $userId, array $result): void
    {
        $this->updateStatus($token, $userId, [
            'status' => 'completed',
            'processed' => $result['total'],
            'created' => $result['created'],
            'updated' => $result['updated'],
            'message' => "Import completed: {$result['created']} created, {$result['updated']} updated.",
            'completed_at' => now()->toIso8601String(),
        ]);
        Cache::forget(self::CLAIM_PREFIX.$token);
    }

    public function fail(string $token, int $userId): void
    {
        $this->updateStatus($token, $userId, [
            'status' => 'failed',
            'message' => 'The import failed and no records were committed. You may retry it.',
            'completed_at' => now()->toIso8601String(),
        ]);
        Cache::forget(self::CLAIM_PREFIX.$token);
    }

    /** @return array<string, mixed>|null */
    public function status(string $token, int $userId): ?array
    {
        $status = Cache::get(self::STATUS_PREFIX.$token);

        return is_array($status) && ($status['user_id'] ?? null) === $userId ? $status : null;
    }

    /** @param array<string, mixed> $changes */
    private function updateStatus(string $token, int $userId, array $changes): void
    {
        $status = $this->status($token, $userId);
        if ($status === null) {
            return;
        }

        Cache::put(
            self::STATUS_PREFIX.$token,
            [...$status, ...$changes],
            now()->addMinutes((int) config('imports.status_ttl_minutes', 1_440)),
        );
    }
}
