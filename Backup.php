<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Vault Backup Static Facade.
 * Provides convenient static access to the BackupService service.
 *
 * @method static string create(string $accountId)                      Create a ZIP backup of account storage
 * @method static void   restore(string $accountId, string $backupPath) Restore from a backup
 * @method static array  list(string $accountId)                        List all backups for an account
 * @method static bool   delete(int $backupId)                          Delete a specific backup
 * @method static int    cleanup(?int $olderThanDays = null)            Clean up old backups
 *
 * @see BackupService
 */

namespace Vault;

use Vault\Services\BackupService;

class Backup
{
    public static function __callStatic(string $method, array $arguments)
    {
        return resolve(BackupService::class)->$method(...$arguments);
    }
}
