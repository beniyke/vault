<!-- This file is auto-generated from docs/vault.md -->

# Vault

Vault provides comprehensive storage quota management for SaaS applications. It enables you to allocate storage quotas to accounts, track usage, enforce limits, and manage backups with atomic precision and transaction safety.

## Architecture

Vault operates on an account-based isolation model:

- **Quota Management**: Real-time tracking of used vs allocated space.
- **File Tracking**: Optional detailed metadata tracking for every file stored.
- **Atomic Updates**: Uses database transactions and row-level locking to ensure quota accuracy during concurrent uploads.
- **Middleware Enforcement**: Automatic request rejection before expensive file processing begins.

## Installation

Vault is a **package** that requires installation before use.

### Install the Package

```bash
php dock package:install Vault --packages
```

This will automatically:

- Publish configuration to `App/Config/`
- Run core migrations for `vault_*`
- Register `VaultServiceProvider` and associated facades.

## Configuration

Configuration is located in `App/Config/vault.php`.

```php
return [
    // Default quota for new accounts (MB)
    'default_quota_mb' => 1024,

    // Maximum allowed quota (MB)
    'max_quota_mb' => 102400,

    // Storage paths (relative to base path)
    'storage_path' => 'App/storage/vault',
    'backup_path' => 'App/storage/vault-backups',

    // Callback to resolve account ID from request
    'account_resolver' => fn($request) => $request->header('X-Account-ID'),

    // Backup retention (days)
    'backup_retention_days' => 30,

    // Features
    'enable_file_tracking' => true,
];
```

## Basic Usage

### Vault Facade

The `Vault` facade provides a static interface to the underlying `VaultManager` service.

#### Allocate Quota

```php
use Vault\Vault;

// Allocate 5GB to an account
Vault::allocate('acc_12345', 5120); // MB
```

#### Check Usage

```php
use Vault\Vault;

$usage = Vault::getUsage('acc_12345');
```

### Allocate Quota

```php
use Vault\Vault;

// Allocate 5GB to an account (Fluent)
Vault::forAccount('acc_12345')->allocate(5120);

// Traditional style still supported
Vault::allocate('acc_12345', 5120);
```

### Check Usage

```php
use Vault\Vault;

$usage = Vault::forAccount('acc_12345')->getUsage();

echo "Used: " . $usage['used'];           // bytes
echo "Percentage: " . $usage['percentage']; // e.g. 20.5
```

### Track Uploads

```php
use Vault\Vault;
use Vault\Exceptions\QuotaExceededException;

try {
    // Record an upload fluently
    Vault::forAccount('acc_12345')->trackUpload('avatars/user_1.png', 204800);
} catch (QuotaExceededException $e) {
    // Handle rejection logic...
}
```

### File Tracking & Deduplication

Optimize storage by detecting duplicates before uploading.

```php
use Vault\Vault;

// Calculate hash of a local file
$hash = Vault::calculateHash($localFilePath);

// Find existing files with the same hash
$duplicates = Vault::findDuplicates($hash);

if (!empty($duplicates)) {
    // Link to existing file instead of re-uploading
}
```

## Use Case

#### Tiered Storage Action

In the Anchor Framework, business logic is best encapsulated in **Actions**. Here is how you might implement a storage-aware upload action.

### The Action

```php
namespace App\Actions;

use App\Models\User;
use App\Requests\UploadRequest;
use Vault\Vault;
use Vault\Exceptions\QuotaExceededException;

class UploadDocumentAction
{
    public function execute(User $user, UploadRequest $request)
    {
        $file = $request->file;
        $accountId = $user->account_id;

        return DB::transaction(function() use ($user, $file, $accountId) {
            // 1. Check & Track Quota
            // This throws QuotaExceededException if user is over their limit
            Vault::forAccount($accountId)->trackUpload(
                "docs/{$file->getName()}",
                $file->getSize()
            );

            // 2. Persist File
            $path = $file->move(Vault::getStoragePath($accountId), $file->getName());

            // 3. Log Activity
            activity("uploaded a document", ['file' => $file->getName()]);

            return $path;
        });
    }
}
```

### Usage in Controller

```php
public function store(UploadRequest $request, UploadDocumentAction $action)
{
    try {
        $path = $action->execute($this->auth->user(), $request);
        return response()->json(['status' => 'success', 'path' => $path]);
    } catch (QuotaExceededException $e) {
        return response()->json([
            'error' => 'Storage limit reached',
            'available' => $e->getAvailableBytes()
        ], 403);
    }
}
```

## Analytics

The `Vault` package provides a comprehensive analytics service to track storage usage, trends, and account health.

```php
use Vault\Analytics;

// Get platform-wide storage overview
$overview = Analytics::getPlatformOverview();

// Get top accounts by storage usage
$topAccounts = Analytics::getTopAccounts(10);

// Get storage distribution by size ranges
$distribution = Analytics::getUsageDistribution();

// Get upload trends for the last 30 days
$trends = Analytics::getUploadTrends(30);

// Identify accounts at risk of exceeding their quota
$atRisk = Analytics::getAtRiskAccounts(90); // 90% threshold
```

You can also access the analytics service through the `Vault` facade or `VaultManager`:

```php
Vault::analytics()->getPlatformOverview();
```

## Middleware

The `CheckVaultQuotaMiddleware` protects your application by automatically rejecting requests if the account is full.

```php
// App/Config/vault.php
'account_resolver' => fn($request) => auth()->user()?->account_id,
```

## Console Commands

| Command                    | Description                                  |
| -------------------------- | -------------------------------------------- |
| `vault:allocate <id> <mb>` | Assign storage limits manually               |
| `vault:usage <id>`         | Visualize storage health and percentage      |
| `vault:backup <id>`        | Create a ZIP archive of account storage      |
| `vault:wipe <id>`          | Clear all files for an account (Destructive) |

## Error Handling

| Exception                  | Scenario                                            |
| -------------------------- | --------------------------------------------------- |
| `QuotaExceededException`   | Account has insufficient space for the operation    |
| `StorageNotFoundException` | Account ID is not recognized or not initialized     |
| `InvalidQuotaException`    | Provided quota value is negative or exceeds maximum |

## Service API Reference

### Vault (Facade)

| Method                             | Description                                                    |
| :--------------------------------- | :------------------------------------------------------------- |
| `forAccount(string $id)`           | Switches to fluent mode for the given account.                 |
| `allocate(?string $id, ?int $mb)`  | Grants storage quota to an account.                            |
| `getUsage(?string $id)`            | Returns usage statistics (used, quota, remaining, percentage). |
| `isFull(?string $id)`              | Checks if the account has exceeded its quota.                  |
| `trackUpload($path, $bytes, ?$id)` | Records an upload and increments usage (atomic).               |
| `trackDeletion($path, ?$id)`       | Records a deletion and decrements usage (atomic).              |
| `calculateHash(string $path)`      | Returns SHA256 hash of a file for tracking/deduplication.      |
| `findDuplicates(string $hash)`     | Returns list of tracked files matching the hash.               |
| `getRemainingSpace(?string $id)`   | Returns available bytes for an account.                        |
| `analytics()`                      | Returns the `VaultAnalytics` service instance.                 |

### Analytics (Facade)

| Method                        | Description                                          |
| :---------------------------- | :--------------------------------------------------- |
| `getPlatformOverview()`       | Returns global storage metrics for the platform.     |
| `getTopAccounts(int $limit)`  | Returns top accounts by usage.                       |
| `getUsageDistribution()`      | Returns storage distribution by bucket size.         |
| `getUploadTrends(int $days)`  | Returns daily upload volume trends.                  |
| `getAtRiskAccounts(int $pct)` | Returns list of accounts near their quota threshold. |

### Backup (Facade)

| Method                              | Description                                     |
| :---------------------------------- | :---------------------------------------------- |
| `create(string $id)`                | Creates a ZIP backup of account storage.        |
| `restore(string $id, string $path)` | Restores account storage from a backup file.    |
| `list(string $id)`                  | Retrieves all backup records for an account.    |
| `delete(int $backupId)`             | Removes a specific backup file and record.      |
| `cleanup(?int $days = null)`        | Purges old backups based on retention settings. |

### VaultManager

| Method                          | Description                                        |
| :------------------------------ | :------------------------------------------------- |
| `recalculateUsage(?string $id)` | Audits actual disk usage and updates the database. |
| `getFileCount(?string $id)`     | Returns total number of files tracked for account. |

## Security & Best Practices

- **Resolution Safety**: Use secure resolvers (session/token) for Account IDs.
- **Path Sanitization**: Vault automatically sanitizes Account IDs to prevent traversal attacks.
- **Drift Correction**: Run `recalculateUsage()` if tracking becomes out of sync.
- **Retention**: Set a reasonable `backup_retention_days` for backups.
