<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Backup Service.
 * Creates and manages ZIP backups of account storage.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Vault\Services;

use Database\DB;
use Helpers\DateTimeHelper;
use Helpers\File\Adapters\Interfaces\FileManipulationInterface;
use Helpers\File\Adapters\Interfaces\FileMetaInterface;
use Helpers\File\Adapters\Interfaces\PathResolverInterface;
use Helpers\String\Str;
use RuntimeException;
use Throwable;
use Vault\Exceptions\StorageNotFoundException;
use ZipArchive;

class BackupService
{
    private const BACKUP_TABLE = 'vault_backup';
    private const STORAGE_PATH = 'App/storage/vault-backups';

    public function __construct(
        private readonly PathResolverInterface $paths,
        private readonly FileMetaInterface $fileMeta,
        private readonly FileManipulationInterface $fileManipulation,
        private readonly VaultManagerService $vaultManager
    ) {
    }

    /**
     * Create a ZIP backup of account storage
     */
    public function create(string $accountId): string
    {
        $storagePath = $this->vaultManager->getStoragePath($accountId);

        if (! $this->fileMeta->isDir($storagePath)) {
            throw new StorageNotFoundException($accountId);
        }

        $backupDir = $this->getBackupDirectory();

        if (! $this->fileMeta->isDir($backupDir)) {
            $this->fileManipulation->mkdir($backupDir, 0755, true);
        }

        $timestamp = DateTimeHelper::now()->format('Y-m-d_His');
        $backupFilename = "{$accountId}_{$timestamp}.zip";
        $backupPath = $backupDir . DIRECTORY_SEPARATOR . $backupFilename;

        $zip = new ZipArchive();
        $result = $zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new RuntimeException("Failed to create ZIP archive: {$backupPath}");
        }

        try {
            $this->addDirectoryToZip($zip, $storagePath, '');

            if (config('vault.enable_compression', true)) {
                $zip->setCompressionIndex(0, ZipArchive::CM_DEFLATE);
            }

            $zip->close();

            $backupSize = $this->fileMeta->size($backupPath);

            DB::table(self::BACKUP_TABLE)->insert([
                'account_id' => $accountId,
                'refid' => Str::random('secure'),
                'backup_path' => $backupFilename,
                'backup_size' => $backupSize,
                'created_at' => DateTimeHelper::now()->format('Y-m-d H:i:s'),
                'expires_at' => $this->calculateExpiryDate(),
            ]);

            return $backupPath;
        } catch (Throwable $e) {
            $zip->close();

            if ($this->fileMeta->exists($backupPath)) {
                $this->fileManipulation->delete($backupPath);
            }

            throw new RuntimeException("Backup creation failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function restore(string $accountId, string $backupPath): void
    {
        if (! $this->fileMeta->exists($backupPath)) {
            throw new RuntimeException("Backup file not found: {$backupPath}");
        }

        $storagePath = $this->vaultManager->getStoragePath($accountId);

        if (! $this->fileMeta->isDir($storagePath)) {
            $this->fileManipulation->mkdir($storagePath, 0755, true);
        }

        $zip = new ZipArchive();
        $result = $zip->open($backupPath);

        if ($result !== true) {
            throw new RuntimeException("Failed to open backup file: {$backupPath}");
        }

        try {
            $zip->extractTo($storagePath);
            $zip->close();

            $this->vaultManager->recalculateUsage($accountId);
        } catch (Throwable $e) {
            $zip->close();
            throw new RuntimeException("Restore failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * List all backups for an account
     */
    public function list(string $accountId): array
    {
        return DB::table(self::BACKUP_TABLE)
            ->where('account_id', $accountId)
            ->latest()
            ->get();
    }

    public function delete(int $backupId): bool
    {
        $backup = DB::table(self::BACKUP_TABLE)
            ->where('id', $backupId)
            ->first();

        if (! $backup) {
            return false;
        }

        $backupDir = $this->getBackupDirectory();
        $backupPath = $backupDir . DIRECTORY_SEPARATOR . $backup['backup_path'];

        if ($this->fileMeta->exists($backupPath)) {
            $this->fileManipulation->delete($backupPath);
        }

        DB::table(self::BACKUP_TABLE)
            ->where('id', $backupId)
            ->delete();

        return true;
    }

    /**
     * Clean up old backups
     *
     * @return int Number of backups deleted
     */
    public function cleanup(?int $olderThanDays = null): int
    {
        $olderThanDays = $olderThanDays ?? config('vault.backup_retention_days', 30);
        $cutoffDate = DateTimeHelper::now()->subDays($olderThanDays)->format('Y-m-d H:i:s');

        $oldBackups = DB::table(self::BACKUP_TABLE)
            ->whereLessThan('created_at', $cutoffDate)
            ->orWhere(function ($query) {
                $query->whereNotNull('expires_at')
                    ->whereLessThan('expires_at', DateTimeHelper::now()->format('Y-m-d H:i:s'));
            })
            ->get();

        $deleted = 0;
        $backupDir = $this->getBackupDirectory();

        foreach ($oldBackups as $backup) {
            $backupPath = $backupDir . DIRECTORY_SEPARATOR . $backup['backup_path'];

            if ($this->fileMeta->exists($backupPath)) {
                $this->fileManipulation->delete($backupPath);
            }

            DB::table(self::BACKUP_TABLE)
                ->where('id', $backup['id'])
                ->delete();

            $deleted++;
        }

        return $deleted;
    }

    private function getBackupDirectory(): string
    {
        return $this->paths->basePath(config('vault.backup_path', self::STORAGE_PATH));
    }

    private function calculateExpiryDate(): ?string
    {
        $retentionDays = config('vault.backup_retention_days', 30);

        if ($retentionDays <= 0) {
            return null; // Never expire
        }

        return DateTimeHelper::now()->addDays($retentionDays)->format('Y-m-d H:i:s');
    }

    /**
     * Recursively add directory contents to ZIP
     */
    private function addDirectoryToZip(ZipArchive $zip, string $sourcePath, string $zipPath): void
    {
        $files = scandir($sourcePath);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $sourcePath . DIRECTORY_SEPARATOR . $file;
            $relativePath = $zipPath ? $zipPath . '/' . $file : $file;

            if (is_dir($fullPath)) {
                $zip->addEmptyDir($relativePath);
                $this->addDirectoryToZip($zip, $fullPath, $relativePath);
            } else {
                $zip->addFile($fullPath, $relativePath);
            }
        }
    }
}
