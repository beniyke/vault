<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Vault Static Facade.
 * Provides convenient static access to the VaultManagerService service.
 *
 * @method static VaultManagerService   forAccount(string $accountId)                                           Select an account for fluent operations
 * @method static void                  allocate(?string $accountId = null, ?int $quotaMb = null)               Allocate storage space for an account
 * @method static array                 getUsage(?string $accountId = null)                                     Get storage usage statistics
 * @method static bool                  canUpload(int $fileSize, ?string $accountId = null)                     Check if a file can be uploaded
 * @method static int                   recalculateUsage(?string $accountId = null)                             Recalculate usage from actual disk usage
 * @method static void                  trackUpload(string $filePath, int $fileSize, ?string $accountId = null) Track a new file upload
 * @method static void                  trackDeletion(string $filePath, ?string $accountId = null)              Track a file deletion
 * @method static string                getStoragePath(?string $accountId = null)                               Get the storage path for an account
 * @method static string                calculateHash(string $filePath)                                         Calculate SHA256 hash of a file
 * @method static array                 findDuplicates(string $hash)                                            Find duplicate files by hash
 * @method static VaultAnalyticsService analytics()                                                             Get the analytics service
 *
 * @see VaultManagerService
 */

namespace Vault;

use Vault\Services\VaultManagerService;

class Vault
{
    public static function __callStatic(string $method, array $arguments)
    {
        return resolve(VaultManagerService::class)->$method(...$arguments);
    }
}
