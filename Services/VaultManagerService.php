<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Vault Manager - Core storage quota management service.
 * Handles quota allocation, usage tracking, and storage operations with
 * comprehensive edge case handling and transaction safety.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Vault\Services;

use Database\ConnectionInterface;
use Database\DB;
use Helpers\DateTimeHelper;
use Helpers\File\Adapters\Interfaces\FileManipulationInterface;
use Helpers\File\Adapters\Interfaces\FileMetaInterface;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use Helpers\String\Str;
use InvalidArgumentException;
use RuntimeException;
use Vault\Exceptions\QuotaExceededException;
use Vault\Exceptions\StorageNotFoundException;

class VaultManagerService
{
    private const MB_TO_BYTES = 1048576;
    private const VAULT_TABLE = 'vault_quota';
    private const STORAGE_PATH = 'App/storage/vault';

    private ?string $accountId = null;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly PathResolverInterface $paths,
        private readonly FileMetaInterface $fileMeta,
        private readonly FileManipulationInterface $fileManipulation,
        private readonly FileTrackerService $fileTracker
    ) {
    }

    public function forAccount(string $accountId): self
    {
        $this->accountId = $accountId;

        return $this;
    }

    public function analytics(): VaultAnalyticsService
    {
        return resolve(VaultAnalyticsService::class);
    }

    public function getFileTracker(): FileTrackerService
    {
        return $this->fileTracker;
    }

    /**
     * @return array{used: int, quota: int, remaining: int, percentage: float}
     *
     * @throws StorageNotFoundException
     */
    public function getUsage(?string $accountId = null): array
    {
        $accountId = $accountId ?: $this->accountId;
        if (! $accountId) {
            throw new RuntimeException('No account ID provided.');
        }

        $quota = $this->getQuotaRecord($accountId);

        $used = (int) $quota->used_bytes;
        $total = (int) $quota->quota_bytes;
        $remaining = max(0, $total - $used);
        $percentage = $total > 0 ? round(($used / $total) * 100, 2) : 0;

        return [
            'used' => $used,
            'quota' => $total,
            'remaining' => $remaining,
            'percentage' => $percentage,
        ];
    }

    /**
     * @throws QuotaExceededException
     * @throws StorageNotFoundException
     */
    public function trackUpload(string $filePath, int $bytes, ?string $accountId = null): void
    {
        $accountId = $accountId ?: $this->accountId;
        if (! $accountId) {
            throw new RuntimeException('No account ID provided.');
        }

        if ($bytes <= 0) {
            throw new InvalidArgumentException('File size must be greater than zero');
        }

        DB::transaction(function () use ($accountId, $filePath, $bytes) {
            $quota = DB::table(self::VAULT_TABLE)
                ->where('account_id', $accountId)
                ->lockForUpdate()
                ->first();

            if (! $quota) {
                throw new StorageNotFoundException($accountId);
            }

            $newUsed = (int) $quota->used_bytes + $bytes;
            $quotaLimit = (int) $quota->quota_bytes;

            if ($newUsed > $quotaLimit) {
                throw new QuotaExceededException(
                    $accountId,
                    $bytes,
                    $quotaLimit - (int) $quota->used_bytes
                );
            }

            // Update usage
            DB::table(self::VAULT_TABLE)
                ->where('account_id', $accountId)
                ->update([
                    'used_bytes' => $newUsed,
                    'updated_at' => DateTimeHelper::now()->format('Y-m-d H:i:s'),
                ]);

            // Track file if enabled
            if (config('vault.enable_file_tracking', true)) {
                $this->fileTracker->track($accountId, $filePath, $bytes);
            }
        });
    }

    public function allocate(?string $accountId = null, ?int $quotaMb = null): void
    {
        $accountId = $accountId ?: $this->accountId;
        if (! $accountId) {
            throw new RuntimeException('No account ID provided.');
        }

        $quotaMb = $quotaMb ?? config('vault.default_quota_mb', 1024);
        $quotaBytes = $quotaMb * self::MB_TO_BYTES;

        DB::table(self::VAULT_TABLE)->updateOrInsert(
            ['account_id' => $accountId],
            [
                'refid' => Str::random('secure'),
                'quota_bytes' => $quotaBytes,
                'updated_at' => DateTimeHelper::now()->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function isFull(?string $accountId = null): bool
    {
        $usage = $this->getUsage($accountId);

        return $usage['used'] >= $usage['quota'];
    }

    public function canUpload(int $fileSize, ?string $accountId = null): bool
    {
        $usage = $this->getUsage($accountId);

        return ($usage['used'] + $fileSize) <= $usage['quota'];
    }

    public function getRemainingSpace(?string $accountId = null): int
    {
        $usage = $this->getUsage($accountId);

        return $usage['remaining'];
    }

    public function trackDeletion(string $filePath, ?string $accountId = null): void
    {
        $accountId = $accountId ?: $this->accountId;
        if (! $accountId) {
            throw new RuntimeException('No account ID provided.');
        }

        DB::transaction(function () use ($accountId, $filePath) {
            $file = DB::table('vault_file')
                ->where('account_id', $accountId)
                ->where('file_path', $filePath)
                ->whereNull('deleted_at')
                ->first();

            if (! $file) {
                return;
            }

            $bytes = (int) $file->file_size;

            DB::table(self::VAULT_TABLE)
                ->where('account_id', $accountId)
                ->decrement('used_bytes', $bytes, [
                    'updated_at' => DateTimeHelper::now()->format('Y-m-d H:i:s'),
                ]);

            $this->fileTracker->untrack($accountId, $filePath);
        });
    }

    public function recalculateUsage(?string $accountId = null): int
    {
        $accountId = $accountId ?: $this->accountId;
        if (! $accountId) {
            throw new RuntimeException('No account ID provided.');
        }

        $storagePath = $this->getStoragePath($accountId);
        $totalSize = 0;

        if ($this->fileMeta->isDir($storagePath)) {
            $totalSize = $this->getDirectorySize($storagePath);
        }

        DB::table(self::VAULT_TABLE)
            ->where('account_id', $accountId)
            ->update([
                'used_bytes' => $totalSize,
                'updated_at' => DateTimeHelper::now()->format('Y-m-d H:i:s'),
            ]);

        return $totalSize;
    }

    public function getStoragePath(?string $accountId = null): string
    {
        $accountId = $accountId ?: $this->accountId;
        if (! $accountId) {
            throw new RuntimeException('No account ID provided.');
        }

        $basePath = config('vault.storage_path', self::STORAGE_PATH);

        return $this->paths->basePath($basePath . DIRECTORY_SEPARATOR . $accountId);
    }

    public function calculateHash(string $filePath): string
    {
        return $this->fileTracker->calculateHash($filePath);
    }

    public function findDuplicates(string $hash): array
    {
        return $this->fileTracker->findDuplicates($hash);
    }

    private function getDirectorySize(string $path): int
    {
        $size = 0;
        foreach (scandir($path) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $file;

            if (is_dir($fullPath)) {
                $size += $this->getDirectorySize($fullPath);
            } else {
                $size += filesize($fullPath);
            }
        }

        return $size;
    }

    private function getQuotaRecord(string $accountId): object
    {
        $quota = DB::table(self::VAULT_TABLE)
            ->where('account_id', $accountId)
            ->first();

        if (! $quota) {
            throw new StorageNotFoundException($accountId);
        }

        return $quota;
    }
}
