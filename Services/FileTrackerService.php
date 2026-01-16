<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * File Tracker Service.
 * Tracks individual files in storage for detailed usage analysis.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Vault\Services;

use Database\DB;
use Helpers\DateTimeHelper;
use Helpers\File\FileSystem;
use Helpers\String\Str;
use RuntimeException;

class FileTrackerService
{
    private const FILE_TABLE = 'vault_file';

    /**
     * Track a new file upload
     */
    public function track(string $accountId, string $filePath, int $bytes, ?string $hash = null): void
    {
        DB::table(self::FILE_TABLE)->insert([
            'account_id' => $accountId,
            'refid' => Str::random('secure'),
            'file_path' => $filePath,
            'file_size' => $bytes,
            'file_hash' => $hash,
            'uploaded_at' => DateTimeHelper::now()->format('Y-m-d H:i:s'),
            'deleted_at' => null,
        ]);
    }

    /**
     * Mark a file as deleted (soft delete)
     */
    public function untrack(string $accountId, string $filePath): void
    {
        DB::table(self::FILE_TABLE)
            ->where('account_id', $accountId)
            ->where('file_path', $filePath)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => DateTimeHelper::now()->format('Y-m-d H:i:s'),
            ]);
    }

    public function getFiles(string $accountId, bool $includeDeleted = false): array
    {
        $query = DB::table(self::FILE_TABLE)
            ->where('account_id', $accountId);

        if (! $includeDeleted) {
            $query->whereNull('deleted_at');
        }

        return $query->latest('uploaded_at')->get();
    }

    public function findDuplicates(string $hash): array
    {
        return DB::table(self::FILE_TABLE)
            ->where('file_hash', $hash)
            ->whereNull('deleted_at')
            ->get();
    }

    public function calculateHash(string $filePath): string
    {
        if (! FileSystem::exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        return hash_file('sha256', $filePath);
    }

    public function getFileCount(string $accountId): int
    {
        return DB::table(self::FILE_TABLE)
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Permanently delete old soft-deleted files from tracking
     */
    public function purgeDeleted(int $olderThanDays = 30): int
    {
        $cutoffDate = DateTimeHelper::now()->subDays($olderThanDays)->format('Y-m-d H:i:s');

        return DB::table(self::FILE_TABLE)
            ->whereNotNull('deleted_at')
            ->whereLessThan('deleted_at', $cutoffDate)
            ->delete();
    }
}
